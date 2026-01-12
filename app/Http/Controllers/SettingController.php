<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    // Mostrar formulario
    public function index()
    {
        // Enviamos todas las configuraciones como un objeto simple JS
        return Inertia::render('Settings/Index', [
            'settings' => Setting::getAll()
        ]);
    }

    // Guardar cambios
    public function update(Request $request)
    {
        // Validamos lo básico
        $request->validate([
            'company_name' => 'required|string|max:255',
            'company_logo' => 'nullable|image|max:1024', // Max 1MB
            // Agrega validaciones según necesites
        ]);

        // Lista de claves que permitimos guardar (seguridad)
        $allowedKeys = [
            'company_name', 'company_rfc', 'company_address', 'company_phone',
            'notification_emails', 'allow_negative_stock', 'ticket_footer_text'
        ];

        // 1. Guardar textos
        foreach ($allowedKeys as $key) {
            if ($request->has($key)) {
                Setting::setValue($key, $request->input($key));
            }
        }

        // 2. Manejo especial del Logo (Archivo)
        if ($request->hasFile('company_logo')) {
            // Borrar anterior si existe (opcional)
            $oldLogo = Setting::getValue('company_logo');
            if ($oldLogo) Storage::disk('public')->delete($oldLogo);

            // Guardar nuevo
            $path = $request->file('company_logo')->store('logos', 'public');
            Setting::setValue('company_logo', $path);
        }

        return redirect()->back()->with('success', 'Configuración actualizada correctamente.');
    }
}
