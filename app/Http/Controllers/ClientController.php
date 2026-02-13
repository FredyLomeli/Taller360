<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    // 1. Listado de Clientes (Optimizado para Taller 360)
    public function index(Request $request)
    {
        $query = Client::query();

        // Buscador Inteligente: Nombre, Empresa, Email O TELÉFONO
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phones', 'like', "%{$search}%"); // ¡Agregado! Búsqueda por teléfono
            });
        }

        // Ordenamos alfabéticamente por defecto
        $query->orderBy('name', 'asc');

        return Inertia::render('Clients/Index', [
            // Paginamos de 10 en 10 y mantenemos los filtros en la URL
            'clients' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only(['search'])
        ]);
    }

    // 2. Formulario de Creación
    public function create()
    {
        return Inertia::render('Clients/Create');
    }

    // 3. Guardar Cliente
    public function store(Request $request)
    {
        // Reglas de validación
        $rules = [
            'name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'price_tier' => 'required|integer|min:1|max:5', 
            'email' => 'required|email|unique:clients,email|max:255',
            'phones' => 'required|string|max:50', 
            'street_address' => 'nullable|string|max:255',  
            'neighborhood' => 'nullable|string|max:255',    
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'delegation' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:10',
            'references' => 'nullable|string|max:500',
        ];

        $validated = $request->validate($rules);

        $client = Client::create($validated);

        // Respuesta JSON para cuando se crea desde el Modal del POS
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

    // 5. Actualizar Cliente
    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'price_tier' => 'required|integer|min:1|max:5',
            // Ignoramos el email del cliente actual para que no de error de "ya existe"
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

    // 6. Eliminar Cliente
    public function destroy($id)
    {
        $client = Client::findOrFail($id);

        if ($client->sales()->exists()) {
            return back()->withErrors([
                'error' => 'No se puede eliminar a "' . $client->name . '" porque tiene historial de compras.'
            ]);
        }

        $client->delete();

        return back()->with('success', 'Cliente eliminado correctamente.');
    }
}