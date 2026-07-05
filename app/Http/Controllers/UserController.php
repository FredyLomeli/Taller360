<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    // Lista única de roles válidos del sistema.
    // Si en el futuro se agrega un rol nuevo, solo se cambia aquí.
    const VALID_ROLES = 'admin,vendedor,produccion,inventario,supervisor,financiero';

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
            'role' => 'required|in:' . self::VALID_ROLES, // Aseguramos que solo sean roles válidos
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

    // Mostrar formulario de edición
    public function edit(User $user)
    {
        return Inertia::render('Users/Edit', [
            'user' => $user
        ]);
    }

    // Actualizar usuario existente
    public function update(Request $request, User $user)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => 'required|in:' . self::VALID_ROLES,
        ];

        // El password es opcional al editar: solo se valida si el usuario escribió algo
        if ($request->filled('password')) {
            $rules['password'] = ['confirmed', Rules\Password::defaults()];
        }

        $validated = $request->validate($rules);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];

        // Solo cambiamos el password si el usuario escribió uno nuevo
        if ($request->filled('password')) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('users.index')->with('success', 'Usuario actualizado correctamente.');
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