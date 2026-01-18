<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserController extends Controller
{
    // Mostrar lista de usuarios
    public function index()
    {
        return Inertia::render('Users/Index', [
            'users' => User::all() // Puedes añadir paginación si tienes muchos
        ]);
    }

    // Mostrar formulario de creación
    public function create()
    {
        return Inertia::render('Users/Create');
    }

    // Guardar nuevo usuario
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => 'required|in:admin,vendedor', // Aseguramos que solo sean roles válidos
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role, // Asegúrate que 'role' esté en el $fillable de User.php
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('users.index')->with('success', 'Usuario creado correctamente.');
    }

    // Eliminar usuario (Opcional, pero útil)
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        // Evitar que te borres a ti mismo
        if (auth()->id() == $user->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $user->delete();
        return back()->with('success', 'Usuario eliminado.');
    }
}