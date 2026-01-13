<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Sale;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    // 1. Listado de Clientes
    public function index()
    {
        return Inertia::render('Clients/Index', [
            'clients' => Client::latest()->get()
        ]);
    }

    // 2. Formulario de Creación
    public function create()
    {
        return Inertia::render('Clients/Create');
    }

    public function store(Request $request)
    {
        // 1. Validaciones
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'price_tier' => 'required|integer|min:1|max:5', 
            'email' => 'nullable|email|max:255',
            'phones' => 'nullable|string|max:50',           
            'street_address' => 'nullable|string|max:255',  
            'neighborhood' => 'nullable|string|max:255',    
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'delegation' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:10',
            'references' => 'nullable|string|max:500',
        ]);

        // 2. Crear Cliente
        $client = Client::create($validated);

        // 3. RESPUESTA INTELIGENTE (Aquí está el truco)
        // Si la petición espera JSON (viene del POS con axios), devolvemos el objeto cliente.
        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Cliente registrado correctamente',
                'client' => $client
            ], 201);
        }

        // Si es petición normal (desde el módulo de Clientes), redirigimos.
        return redirect()->route('clients.index')->with('success', 'Cliente creado exitosamente.');
    }

    // 4. Formulario de Edición
    public function edit(Client $client)
    {
        return Inertia::render('Clients/Edit', [
            'client' => $client
        ]);
    }

    // 5. Actualizar Cliente
    public function update(Request $request, Client $client)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price_tier' => 'required|integer|min:1|max:5',
            'email' => 'nullable|email',
        ]);

        $client->update($request->all());

        return redirect()->route('clients.index')->with('success', 'Cliente actualizado.');
    }

    public function destroy($id)
    {
        $client = Client::findOrFail($id);

        // 1. EL GUARDIA: Verificar si tiene ventas
        // Asegúrate de importar App\Models\Sale arriba
        $hasSales = \App\Models\Sale::where('client_id', $id)->exists();

        if ($hasSales) {
            return back()->withErrors([
                'error' => 'No se puede eliminar al cliente "' . $client->name . '" porque tiene historial de compras registrada.'
            ]);
        }

        // 2. Si está limpio, borrar
        $client->delete();

        return back()->with('success', 'Cliente eliminado correctamente.');
    }
}
