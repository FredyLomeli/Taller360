<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

    // 3. Guardar Cliente Nuevo
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price_tier' => 'required|integer|min:1|max:5',
            'email' => 'nullable|email',
            // Agrega más validaciones si lo deseas
        ]);

        Client::create($request->all());

        return redirect()->route('clients.index')->with('success', 'Cliente registrado correctamente.');
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

    // 6. Eliminar Cliente
    public function destroy(Client $client)
    {
        // Opcional: Validar si tiene ventas antes de borrar (try-catch)
        try {
            $client->delete();
            return redirect()->back()->with('success', 'Cliente eliminado.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'No se puede eliminar este cliente porque tiene historial de ventas.']);
        }
    }
}
