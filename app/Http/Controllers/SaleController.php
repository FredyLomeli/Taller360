<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\ProductVariant;
use App\Models\Client;
use App\Models\Setting;
use App\Mail\SaleNoteEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

class SaleController extends Controller
{
    /**
     * Listado de Pedidos con Filtros (V2.0)
     */
    public function index(Request $request)
    {
        // Iniciamos la consulta con las relaciones necesarias
        $query = Sale::with(['client', 'user', 'history'])->latest();

        // Regla de Negocio: Vendedores solo ven lo suyo
        if (Auth::user()->role !== 'admin') {
            $query->where('user_id', Auth::id());
        }

        // Filtro por Etapa (Tabs del Dashboard)
        if ($request->has('stage') && $request->stage !== 'todos') {
            $query->where('stage', $request->stage);
        }

        // --- LÓGICA DEL BUSCADOR ---
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                // Buscar por Folio
                $q->where('id', 'like', "%$search%")
                // O buscar por nombre del cliente
                ->orWhereHas('client', function($clientQ) use ($search) {
                    $clientQ->where('name', 'like', "%$search%");
                });
            });
        }

        return Inertia::render('Sales/Index', [
            'sales' => $query->paginate(15)->withQueryString(),
            'filters' => $request->all(['search', 'stage']),
        ]);
    }

    /**
     * Vista para crear nuevo pedido (POS)
     */
    public function create()
    {
        return Inertia::render('Sales/Create', [
            // CORRECCIÓN: Agregamos 'category' al with()
            'products' => \App\Models\Product::with(['variants', 'category']) 
                ->orderBy('is_favorite', 'desc')
                ->get(),
            'clients' => Client::all(),
        ]);
    }

    /**
     * GUARDAR PEDIDO (V2.0)
     * - Guarda en estado 'pedido'.
     * - NO descuenta stock (eso pasa en 'enviado').
     * - Guarda colores y adicionales.
     */
    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'items' => 'required|array|min:1',
            // Validaciones de partida
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.chosen_color' => 'required|string', // Nuevo v2.0
            
            'payment_method' => 'required|string',
            'paid_amount' => 'required|numeric|min:0', // Anticipo
            'promised_date' => 'nullable|date',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $totalSale = 0;

                // 1. Crear Venta (Estado inicial: pedido)
                $sale = Sale::create([
                    'user_id' => Auth::id(),
                    'client_id' => $request->client_id,
                    'total' => 0, // Se calcula abajo
                    'paid_amount' => $request->paid_amount,
                    'change_amount' => 0, // Se calcula abajo
                    'payment_method' => $request->payment_method,
                    'stage' => 'pedido', // SIEMPRE inicia como pedido
                    'promised_date' => $request->promised_date,
                ]);

                // 2. Guardar Detalles
                foreach ($request->items as $item) {
                    $variant = ProductVariant::with('product')->find($item['variant_id']);
                    
                    // Calculamos subtotal incluyendo costo adicional
                    $additionalCost = $item['additional_cost'] ?? 0;
                    $lineTotal = ($item['price'] * $item['quantity']) + $additionalCost;

                    SaleDetail::create([
                        'sale_id' => $sale->id,
                        'product_variant_id' => $variant->id,
                        // Snapshot del nombre + material
                        'product_name' => $variant->product->name . ' (' . $variant->material . ')',
                        'quantity' => $item['quantity'],
                        
                        // Nuevos campos V2.0
                        'chosen_color' => $item['chosen_color'],
                        'custom_notes' => $item['notes'] ?? null,
                        'additional_cost' => $additionalCost,
                        
                        'unit_price' => $item['price'],
                        'subtotal' => $lineTotal,
                        'discount_percent' => $item['discount_percent'] ?? 0,
                    ]);

                    $totalSale += $lineTotal;
                }

                // 3. Actualizar Totales
                // Si el anticipo cubre el total, podríamos marcarlo como pagado internamente, 
                // pero el stage sigue siendo 'pedido' hasta que se autorice.
                $change = max(0, $request->paid_amount - $totalSale);
                
                $sale->update([
                    'total' => $totalSale,
                    'change_amount' => $change
                ]);
                
                // Si dio anticipo, pasamos a 'confirmado' automáticamente (Opcional)
                if ($request->paid_amount > 0) {
                    $sale->update(['stage' => 'confirmado']);
                }

                return redirect()->route('sales.index')->with('success', 'Pedido registrado correctamente (Folio #' . $sale->id . ')');
            });

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Ver detalle del pedido
     */
    public function show(Sale $sale)
    {
        $sale->load(['details.variant', 'client', 'user', 'history.user']);
        return Inertia::render('Sales/Show', ['sale' => $sale]);
    }

    /**
     * MOTOR DE ESTADOS (V2.0)
     * Maneja el inventario y los cambios de etapa.
     * Reemplaza a la antigua función 'cancel'.
     */
    public function updateStage(Request $request, Sale $sale)
    {
        $request->validate([
            'stage' => 'required|in:pedido,confirmado,produccion,enviado,entregado,cancelado'
        ]);

        $newStage = $request->stage;
        $oldStage = $sale->stage;

        if ($newStage === $oldStage) return back();

        try {
            DB::transaction(function () use ($sale, $newStage, $oldStage) {
                
                // CASO A: Salida de Almacén (Enviado) -> RESTAR STOCK
                if ($newStage === 'enviado' && $oldStage !== 'enviado' && $oldStage !== 'entregado') {
                    $allowNegative = Setting::where('key', 'allow_negative_stock')->value('value');

                    foreach ($sale->details as $detail) {
                        $variant = ProductVariant::lockForUpdate()->find($detail->product_variant_id);
                        
                        if (!$allowNegative && $variant->stock < $detail->quantity) {
                            throw new \Exception("Stock insuficiente de {$detail->product_name} para enviar.");
                        }
                        $variant->decrement('stock', $detail->quantity);
                    }
                }

                // CASO B: Cancelación de un pedido YA enviado -> DEVOLVER STOCK
                if ($newStage === 'cancelado' && ($oldStage === 'enviado' || $oldStage === 'entregado')) {
                    foreach ($sale->details as $detail) {
                        ProductVariant::where('id', $detail->product_variant_id)
                            ->increment('stock', $detail->quantity);
                    }
                }

                // Actualizamos el estado (El Observer guardará el historial)
                $sale->update(['stage' => $newStage]);
            });

            return back()->with('success', "Estado actualizado a: " . ucfirst($newStage));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    // --- FUNCIONES DE IMPRESIÓN Y CORREO (Legacy v1.0 Adaptado) ---

    public function printTicket($id)
    {
        $sale = Sale::with(['details', 'client', 'user'])->findOrFail($id);
        $settings = Setting::all()->pluck('value', 'key');
        
        $company = [
            'name' => $settings['company_name'] ?? 'POS SYSTEM',
            'address' => $settings['company_address'] ?? 'Dirección no configurada',
            'rfc' => $settings['company_rfc'] ?? 'XAXX010101000',
            'phone' => $settings['company_phone'] ?? '',
            'footer_text' => $settings['ticket_footer_text'] ?? '¡Gracias por su preferencia!',
        ];

        // Usamos la misma vista 'pdf.ticket', asegúrate de actualizarla si quieres mostrar el color elegido
        $pdf = Pdf::loadView('pdf.ticket', compact('sale', 'company'));
        $pdf->setPaper([0, 0, 227, 800], 'portrait');

        return $pdf->stream('ticket-'.$sale->id.'.pdf');
    }

    public function printNote($id)
    {
        $sale = Sale::with(['details', 'client'])->findOrFail($id);
        $settings = Setting::all()->pluck('value', 'key');
        
        $company = [
            'name' => $settings['company_name'] ?? 'Mi Empresa',
            'address' => $settings['company_address'] ?? '',
            'rfc' => $settings['company_rfc'] ?? '',
            'phone' => $settings['company_phone'] ?? '',
            'footer_text' => $settings['ticket_footer_text'] ?? ''
        ];

        $logoPath = null;
        if (isset($settings['company_logo']) && $settings['company_logo']) {
            // Fix para rutas en Hostings Compartidos o Local
            $logoPath = public_path('storage/' . $settings['company_logo']);
            // Si usaste el truco del link symbolico personalizado, ajusta aquí si falla.
        }

        $pdf = Pdf::loadView('pdf.sale_note', compact('sale', 'company', 'logoPath'));
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('nota-venta-'.$sale->id.'.pdf');
    }

    public function sendEmail($id)
    {
        $sale = Sale::with(['details', 'client'])->findOrFail($id);
        $settings = Setting::all()->pluck('value', 'key');
        
        // Preparar Datos (Igual que printNote)
        $company = [
            'name' => $settings['company_name'] ?? 'Mi Empresa',
            'address' => $settings['company_address'] ?? '',
            'rfc' => $settings['company_rfc'] ?? '',
            'phone' => $settings['company_phone'] ?? '',
            'footer_text' => $settings['ticket_footer_text'] ?? ''
        ];

        $logoPath = null;
        if (isset($settings['company_logo']) && $settings['company_logo']) {
            $logoPath = public_path('storage/' . $settings['company_logo']);
        }

        // Generar PDF en Memoria
        $pdf = Pdf::loadView('pdf.sale_note', compact('sale', 'company', 'logoPath'));
        $pdf->setPaper('letter', 'portrait');
        $pdfOutput = $pdf->output();

        // Obtener Destinatarios
        $emails = [];
        if ($sale->client && $sale->client->email) {
            $emails[] = $sale->client->email;
        }
        if (!empty($settings['notification_emails'])) {
            $adminEmails = array_map('trim', explode(',', $settings['notification_emails']));
            $emails = array_merge($emails, $adminEmails);
        }
        
        $emails = array_unique(array_filter($emails));

        if (empty($emails)) {
            return back()->withErrors(['error' => 'No hay correos configurados para enviar.']);
        }

        try {
            Mail::to($emails)->send(new SaleNoteEmail($sale, $pdfOutput));
            return back()->with('success', 'Correo enviado correctamente a: ' . implode(', ', $emails));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
    }
}