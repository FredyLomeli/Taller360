<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\ProductVariant;
use App\Models\Client;
use App\Models\Setting;
use App\Mail\SaleNoteEmail;
use App\Models\SaleDelivery;
use App\Models\SaleHistory;
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
        // 1 y 2. Iniciamos la consulta seleccionando SOLO los campos necesarios
        // EXCLUIMOS explícitamente 'signature'
        $query = Sale::select([
            'id', 'user_id', 'client_id', 'total', 'paid_amount', 'change_amount', 
            'payment_method', 'stage', 'promised_date', 'is_partial_shipping', 
            'created_at', 'updated_at'
        ])
        ->with([
            'client:id,name', 
            'user:id,name', 
            'details.variant:id,material,measurements',
            'history.user:id,name'
        ])->latest();

        // Regla de Negocio: Vendedores solo ven lo suyo
        if (Auth::user()->role !== 'admin') {
            $query->where('user_id', Auth::id());
        }

        // Filtro por Etapa (Tabs del Dashboard)
        if ($request->has('stage') && $request->stage !== 'todos') {
            if ($request->stage === 'detallado') {
                $query->whereHas('details.detalladoRecords');
            } else {
                $query->where('stage', $request->stage);
            }
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
            'signature' => 'required|string',
            'paid_amount' => 'required|numeric|min:0', // Anticipo
            'promised_date' => 'nullable|date',
        ]);

        try {
            $sale = DB::transaction(function () use ($request) {
                $totalSale = 0;

                // 1. Crear Venta (Estado inicial: pedido)
                $sale = Sale::create([
                    'user_id' => Auth::id(),
                    'client_id' => $request->client_id,
                    'total' => 0, // Se calcula abajo
                    'paid_amount' => $request->paid_amount,
                    'change_amount' => 0, // Se calcula abajo
                    'payment_method' => $request->payment_method,
                    'signature' => $request->signature,
                    'stage' => 'pedido', // SIEMPRE inicia como pedido
                    'promised_date' => $request->promised_date,
                ]);

                $allowNegative = \App\Models\Setting::where('key', 'allow_negative_stock')->value('value') == 1;

                // 2. Guardar Detalles
                foreach ($request->items as $item) {
                    // Seguimos usando lockForUpdate por integridad transaccional si hiciéramos deducciones de inventario,
                    // aunque en el flujo 'pedido' no descontamos stock, pero es buena práctica para no tener lecturas sucias.
                    $variant = ProductVariant::with('product')->lockForUpdate()->find($item['variant_id']);

                    if (!$allowNegative && $variant->available_stock < $item['quantity']) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'items' => "Stock insuficiente para {$variant->product->name}. Disponible: {$variant->available_stock}, solicitado: {$item['quantity']}."
                        ]);
                    }

                    // Calculamos subtotal incluyendo costo adicional
                    $additionalCost = $item['additional_cost'] ?? 0;
                    $lineTotal = ($item['price'] * $item['quantity']) + $additionalCost;

                    SaleDetail::create([
                        'sale_id' => $sale->id,
                        'product_variant_id' => $variant->id,
                        // Snapshot del nombre + material + medidas al momento de la venta
                        'product_name' => $variant->product->name . ' (' . $variant->material . ' - ' . $variant->measurements . ')',
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

                return $sale;
            });

            try {
                if (Setting::getValue('auto_email_on_sale', true)) {
                    $this->sendSaleNoteMail($sale);
                }
            } catch (\Exception $mailException) {
                \Illuminate\Support\Facades\Log::error('Error enviando correo automático: ' . $mailException->getMessage());
            }

            return redirect()->route('sales.index')->with('success', 'Pedido registrado correctamente (Folio #' . $sale->id . ')');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $sale = Sale::with([
            'client', 
            // Agregamos la suma de las entregas a los detalles
            'details' => function($query) {
                $query->with(['variant.product'])
                    ->withSum(['deliveries as delivered_quantity' => function ($dq) {
                        $dq->whereHas('shipment', fn($sq) => $sq->where('status', '!=', 'cancelado'));
                    }], 'quantity_delivered')
                    ->withSum('detalladoRecords as reserved_quantity', 'quantity');
            }, 
            'history', 
            'payments'
        ])->findOrFail($id);

        $userRole = auth()->user()->role;
        $forceProductionMode = in_array($userRole, ['produccion', 'inventario', 'supervisor']);
        $isProductionMode = $forceProductionMode || request()->boolean('production');

        if ($isProductionMode) {
            $sale->makeHidden(['total', 'paid_amount', 'change_amount']);
            $sale->details->each->makeHidden(['unit_price', 'subtotal', 'additional_cost', 'discount_percent']);
            // Ocultar pagos por completo
            $sale->setRelation('payments', collect([]));
        }

        return Inertia::render('Sales/Show', [
            'sale' => $sale,
            'is_production_mode' => $isProductionMode
        ]);
    }

    public function sendToDetallado(Request $request, SaleDetail $detail)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($request, $detail) {
                $variant = ProductVariant::lockForUpdate()->find($detail->product_variant_id);

                $allowNegative = \App\Models\Setting::where('key', 'allow_negative_stock')->value('value') == 1;

                // 1. Validación de Inventario Físico (available_stock)
                if (!$allowNegative && $variant->available_stock < $request->quantity) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'quantity' => "Stock insuficiente. Disponible real: {$variant->available_stock}."
                    ]);
                }

                // 2. Validación Lógica de Límite Máximo del Pedido
                $detalladoPrevio = $detail->detalladoRecords()->sum('quantity');
                $entregado = $detail->deliveries()
                    ->whereHas('shipment', function($query) { 
                        $query->where('status', '!=', 'cancelado'); 
                    })
                    ->sum('quantity_delivered');

                $maxPermitido = $detail->quantity - $detalladoPrevio - $entregado;

                if ($request->quantity > $maxPermitido) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'quantity' => "Límite excedido. Solo puedes detallar un máximo de {$maxPermitido} piezas para esta partida."
                    ]);
                }

                $variant->increment('reserved_stock', $request->quantity);

                \App\Models\DetalladoRecord::create([
                    'sale_detail_id' => $detail->id,
                    'quantity' => $request->quantity,
                    'user_id' => \Illuminate\Support\Facades\Auth::id(),
                ]);

                \App\Models\SaleHistory::create([
                    'sale_id' => $detail->sale_id,
                    'user_id' => \Illuminate\Support\Facades\Auth::id(),
                    'to_stage' => $detail->sale->stage,
                    'notes' => "Envió {$request->quantity} piezas de {$variant->product->name} a Detallado."
                ]);
            });

            return back()->with('success', 'Piezas enviadas a Detallado exitosamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * MOTOR DE ESTADOS (V2.1)
     * Maneja los cambios de etapa del pedido.
     * promised_date es requerida por validación de Laravel cuando la etapa destino es 'confirmado'.
     */
    public function updateStage(Request $request, Sale $sale)
    {
        // Construir reglas condicionalmente: promised_date solo es required para 'confirmado'
        $rules = [
            'stage'        => 'required|in:pedido,confirmado,produccion,cancelado',
            'promised_date' => 'nullable|date',
        ];

        if ($request->input('stage') === 'confirmado') {
            $rules['promised_date'] = 'required|date';
        }

        $validated = $request->validate($rules);

        $newStage = $validated['stage'];
        $oldStage = $sale->stage;

        if ($newStage === $oldStage) return back();

        try {
            DB::transaction(function () use ($sale, $newStage, $validated) {
                // Construir el payload de actualización
                $updateData = ['stage' => $newStage];

                // Persistir promised_date explícitamente si viene validada en el request
                if (!empty($validated['promised_date'])) {
                    $updateData['promised_date'] = $validated['promised_date'];
                }

                // El SaleObserver registra el historial automáticamente al detectar el cambio de stage
                $sale->update($updateData);
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

        $logoBase64 = null;
        
        if (isset($settings['company_logo']) && $settings['company_logo']) {
            // Intentamos obtener la ruta desde el .env o desde el public_path estándar
            $rootPath = env('FILESYSTEM_PUBLIC_ROOT', public_path('storage'));
            $fullPath = $rootPath . '/' . $settings['company_logo'];

            // Si la ruta del .env no existe, probamos la ruta pública clásica
            if (!file_exists($fullPath)) {
                $fullPath = public_path('storage/' . $settings['company_logo']);
            }

            // Si el archivo existe, lo convertimos a Base64
            if (file_exists($fullPath)) {
                $type = pathinfo($fullPath, PATHINFO_EXTENSION);
                $data = file_get_contents($fullPath);
                $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }
        }

        // Enviamos 'logoBase64' a la vista
        $pdf = Pdf::loadView('pdf.sale_note', compact('sale', 'company', 'logoBase64'));
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('nota-venta-'.$sale->id.'.pdf');
    }

    public function sendEmail($id)
    {
        $sale = Sale::with(['details', 'client'])->findOrFail($id);
        
        try {
            $emails = $this->sendSaleNoteMail($sale);
            if (empty($emails)) {
                return back()->withErrors(['error' => 'No hay correos configurados para enviar.']);
            }
            return back()->with('success', 'Correo enviado correctamente a: ' . implode(', ', $emails));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
    }

    private function sendSaleNoteMail(Sale $sale)
    {
        $settings = Setting::all()->pluck('value', 'key');
        
        // Preparar Datos
        $company = [
            'name' => $settings['company_name'] ?? 'Mi Empresa',
            'address' => $settings['company_address'] ?? '',
            'rfc' => $settings['company_rfc'] ?? '',
            'phone' => $settings['company_phone'] ?? '',
            'footer_text' => $settings['ticket_footer_text'] ?? ''
        ];

        $logoBase64 = null;
        if (isset($settings['company_logo']) && $settings['company_logo']) {
            $rootPath = env('FILESYSTEM_PUBLIC_ROOT', public_path('storage'));
            $logoPath = $rootPath . '/' . $settings['company_logo'];
            if (file_exists($logoPath)) {
                $mime = mime_content_type($logoPath);
                $data = file_get_contents($logoPath);
                $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode($data);
            }
        }

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
            return $emails;
        }

        // 1. Guardar firma Base64 como archivo temporal
        $tempSigPath = null;
        if (!empty($sale->signature)) {
            $sigData = $sale->signature;
            if (strpos($sigData, 'data:image') === 0) {
                $parts = explode(',', $sigData);
                if (count($parts) === 2) {
                    $decodedSignature = base64_decode($parts[1]);
                    if ($decodedSignature) {
                        $tempSigPath = storage_path('app/public/temp_sig_' . $sale->id . '_' . uniqid() . '.png');
                        file_put_contents($tempSigPath, $decodedSignature);
                    }
                }
            }
        }

        // 2. Obtener el ID de la venta
        $saleId = $sale->id;

        // 3. Despachar de forma diferida pasando SOLO TEXTO/ARRAYS al closure
        dispatch(function () use ($saleId, $emails, $tempSigPath, $company, $logoBase64) {
            // 4. Re-consultar la venta
            $sale = Sale::with('details', 'client')->find($saleId);
            
            if (!$sale) {
                if ($tempSigPath && file_exists($tempSigPath)) {
                    unlink($tempSigPath);
                }
                return;
            }

            // Generar el PDF con DOMPDF usando el archivo temporal
            $pdf = Pdf::loadView('pdf.sale_note', [
                'sale' => $sale,
                'company' => $company,
                'logoBase64' => $logoBase64,
                'signaturePath' => $tempSigPath
            ]);
            $pdf->setPaper('letter', 'portrait');
            $pdfOutput = $pdf->output();

            // Despachar el correo síncronamente (pero en background por el afterResponse)
            Mail::to($emails)->send(new SaleNoteEmail($sale, $pdfOutput));

            // Eliminar la firma temporal
            if ($tempSigPath && file_exists($tempSigPath)) {
                unlink($tempSigPath);
            }
        })->afterResponse();

        return $emails;
    }
}