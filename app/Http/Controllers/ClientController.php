<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Sale;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    // 1. Listado de Clientes (Mejorado con Paginación y Buscador)
    public function index(Request $request)
    {
        $query = Client::latest();

        // Buscador simple
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Clients/Index', [
            'clients' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only(['search'])
        ]);
    }

    // 2. Formulario de Creación
    public function create()
    {
        return Inertia::render('Clients/Create');
    }

    // 3. Guardar Cliente (Lógica V2.0)
    public function store(Request $request)
    {
        // Reglas de validación centralizadas
        $rules = [
            'name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'price_tier' => 'required|integer|min:1|max:5', 
            
            // CONTACTO OBLIGATORIO EN V2.0
            'email' => 'required|email|unique:clients,email|max:255',
            'phones' => 'required|string|max:50', // Antes nullable, ahora required
            
            // DIRECCIÓN (Opcional pero validada)
            'street_address' => 'nullable|string|max:255',  
            'neighborhood' => 'nullable|string|max:255',    
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'delegation' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:10',
            'references' => 'nullable|string|max:500',
        ];

        $validated = $request->validate($rules);

        // Crear Cliente
        $client = Client::create($validated);

        // RESPUESTA INTELIGENTE (Para el POS)
        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Cliente registrado correctamente',
                'client' => $client
            ], 201);
        }

        return redirect()->route('clients.index')->with('success', 'Cliente creado exitosamente.');
    }

    // 4. Formulario de Edición
    public function edit(Client $client)
    {
        return Inertia::render('Clients/Edit', [
            'client' => $client
        ]);
    }

    // 5. Actualizar Cliente (Corregido: Actualizaba pocos campos antes)
    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'price_tier' => 'required|integer|min:1|max:5',
            
            // Validación de Email Único (Ignorando al propio cliente actual)
            'email' => ['required', 'email', Rule::unique('clients')->ignore($client->id)],
            'phones' => 'required|string|max:50',
            
            'street_address' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'delegation' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:10',
            'references' => 'nullable|string|max:500',
        ]);

        $client->update($validated);

        return redirect()->route('clients.index')->with('success', 'Cliente actualizado correctamente.');
    }

    // 6. Eliminar Cliente (Con protección)
    public function destroy($id)
    {
        $client = Client::findOrFail($id);

        // Verificar si tiene ventas (Usamos la relación del modelo en lugar de query manual)
        if ($client->sales()->exists()) {
            return back()->withErrors([
                'error' => 'No se puede eliminar a "' . $client->name . '" porque tiene historial de compras.'
            ]);
        }

        $client->delete();

        return back()->with('success', 'Cliente eliminado correctamente.');
    }
}