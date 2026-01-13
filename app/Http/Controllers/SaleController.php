<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Sale;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Mail\SaleNoteEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

class SaleController extends Controller
{

    public function index(Request $request)
    {
        // Iniciamos la consulta con las relaciones necesarias
        $query = Sale::with(['client', 'user'])->latest();

        // --- LÓGICA DEL BUSCADOR ---
        if ($request->filled('search')) {
            $search = $request->input('search');
            
            $query->where(function($q) use ($search) {
                // 1. Buscar por ID exacto (Folio)
                $q->where('id', 'like', "%$search%")
                // 2. O buscar por nombre del cliente
                ->orWhereHas('client', function($clientQ) use ($search) {
                    $clientQ->where('name', 'like', "%$search%");
                });
            });
        }
        // ---------------------------

        return Inertia::render('Sales/Index', [
            // Mantenemos la paginación y adjuntamos el filtro para que no se pierda al cambiar de página
            'sales' => $query->paginate(10)->withQueryString(),
            
            // Devolvemos el filtro actual para que el input no se borre al buscar
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'cart' => 'required|array|min:1',
            // Validaciones nuevas
            'payment_method' => 'required|string',
            'amount_received' => 'required|numeric|min:0',
        ]);

        try {
            DB::transaction(function () use ($request) {
                
                // 1. Calcular totales
                $totalVenta = 0;
                // ... (tu lógica del foreach para calcular total y descontar stock sigue IGUAL aquí) ...
                // (Voy a resumir esta parte para no repetir código, pero mantén tu foreach de stock aquí)
                foreach ($request->cart as $item) {
                    $variant = ProductVariant::lockForUpdate()->find($item['variant_id']);
                    if ($variant->stock < $item['quantity']) throw new \Exception("Stock insuficiente: {$item['product_name']}");
                    
                    // Usamos el precio FINAL que viene del frontend (ya con descuento aplicado)
                    $precioFinal = $item['price']; 
                    $subtotal = $precioFinal * $item['quantity'];
                    
                    $totalVenta += $subtotal;
                    $variant->decrement('stock', $item['quantity']);
                }

                // 2. Determinar Estado (Pagado o Pendiente/Crédito)
                // Si lo recibido es mayor o igual al total, está PAGADO. Si no, es PENDIENTE (Abono).
                $status = ($request->amount_received >= $totalVenta) ? 'pagado' : 'pendiente';

                // 3. Crear Venta
                $sale = Sale::create([
                    'user_id' => Auth::id(),
                    'client_id' => $request->client_id,
                    'total' => $totalVenta,
                    'paid_amount' => $request->amount_received, // Lo que realmente dio
                    'payment_method' => $request->payment_method,
                    'status' => $status,
                    'change_amount' => max(0, $request->amount_received - $totalVenta) // El cambio entregado
                ]);

                // 4. Guardar Detalles
                foreach ($request->cart as $item) {
                    $sale->details()->create([
                        'product_variant_id' => $item['variant_id'],
                        'product_name' => $item['product_name'] . ' (' . $item['material'] . ' ' . $item['color'] . ')',
                        'quantity' => $item['quantity'],
                        
                        // GUARDAMOS EL DESCUENTO AQUÍ
                        'discount_percent' => $item['discount_percent'] ?? 0, 
                        
                        'unit_price' => $item['price'], // Este sigue siendo el precio YA rebajado
                        'subtotal' => $item['price'] * $item['quantity']
                    ]);
                }
            });

            return redirect()->back()->with('success', 'Venta registrada correctamente.');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    // Generar PDF del Ticket
    public function printTicket($id)
    {
        $sale = Sale::with(['details', 'client', 'user'])->findOrFail($id);
        
        // Cargamos la vista que creamos y le pasamos la venta
        $pdf = Pdf::loadView('pdf.ticket', compact('sale'));
        
        // Configuramos el tamaño del papel (ticket térmico aprox 80mm de ancho)
        // array(0, 0, 226.77, 800) -> 80mm ancho x Alto dinámico (suficiente)
        $pdf->setPaper([0, 0, 227, 800], 'portrait');

        // stream() muestra el PDF en el navegador
        // download() lo baja directamente
        return $pdf->stream('ticket-'.$sale->id.'.pdf');
    }

    public function printNote($id)
    {
        $sale = Sale::with(['details', 'client'])->findOrFail($id);
        
        // Obtenemos configuración
        $settings = Setting::getAll(); // El método que creamos antes
        
        // Preparamos datos para la vista
        $company = [
            'name' => $settings['company_name'] ?? 'Mi Empresa',
            'address' => $settings['company_address'] ?? '',
            'rfc' => $settings['company_rfc'] ?? '',
            'phone' => $settings['company_phone'] ?? '',
            'footer_text' => $settings['ticket_footer_text'] ?? ''
        ];

        // LOGO: Dompdf necesita la ruta absoluta del sistema de archivos, no URL http://...
        $logoPath = null;
        if (isset($settings['company_logo']) && $settings['company_logo']) {
            // public_path obtiene C:\xampp\htdocs\...\public
            $logoPath = public_path('storage/' . $settings['company_logo']);
        }

        $pdf = Pdf::loadView('pdf.sale_note', compact('sale', 'company', 'logoPath'));
        
        // Tamaño Carta (Letter) Vertical
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('nota-venta-'.$sale->id.'.pdf');
    }

    public function sendEmail($id)
    {
        $sale = Sale::with(['details', 'client'])->findOrFail($id);

        // 1. Generar el PDF en memoria (Reutilizamos lógica de vista)
        $settings = Setting::getAll();
        
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

        // Generamos el PDF usando DomPDF
        $pdf = Pdf::loadView('pdf.sale_note', compact('sale', 'company', 'logoPath'));
        $pdf->setPaper('letter', 'portrait');
        $pdfOutput = $pdf->output(); // Obtenemos el contenido binario del PDF

        // 2. Obtener Destinatarios
        $emails = [];
        
        // A. Correo del Cliente (si tiene)
        if ($sale->client && $sale->client->email) {
            $emails[] = $sale->client->email;
        }

        // B. Correos Administrativos (desde Configuración)
        if (!empty($settings['notification_emails'])) {
            // Convertimos "correo1@x.com, correo2@x.com" en array
            $adminEmails = array_map('trim', explode(',', $settings['notification_emails']));
            $emails = array_merge($emails, $adminEmails);
        }
        
        // Eliminar duplicados y vacíos
        $emails = array_unique(array_filter($emails));

        if (empty($emails)) {
            return back()->withErrors(['error' => 'No hay correos configurados para enviar.']);
        }

        // 3. Enviar Correo
        try {
            // Enviamos a todos los destinatarios encontrados
            Mail::to($emails)->send(new SaleNoteEmail($sale, $pdfOutput));
            
            return back()->with('success', 'Correo enviado correctamente a: ' . implode(', ', $emails));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
    }

    // Cancelar Venta y Devolver Stock
    public function cancel($id)
    {
        $sale = Sale::with('details')->findOrFail($id);

        if ($sale->status === 'cancelado') {
            return back()->withErrors(['error' => 'Esta venta ya ha sido cancelada anteriormente.']);
        }

        DB::transaction(function () use ($sale) {
            // 1. Devolver Stock al Inventario
            foreach ($sale->details as $detail) {
                // Buscamos la variante exacta (por si cambió algo, aseguramos que exista)
                $variant = ProductVariant::find($detail->product_variant_id);
                
                if ($variant) {
                    // Sumamos la cantidad vendida de regreso al stock
                    $variant->increment('stock', $detail->quantity);
                }
            }

            // 2. Cambiar Estatus de la Venta
            $sale->update(['status' => 'cancelado']);
        });

        return back()->with('success', 'Venta #' . str_pad($sale->id, 6, '0', STR_PAD_LEFT) . ' cancelada y stock restaurado.');
    }
}
