<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value'];

    /**
     * Obtener un valor de configuración por su clave.
     * Uso: Setting::getValue('company_name', 'Valor por defecto');
     */
    public static function getValue($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Guardar o actualizar una configuración.
     * Uso: Setting::setValue('company_name', 'Nuevo Nombre');
     */
    public static function setValue($key, $value)
    {
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
    
    /**
     * Obtener todas las configuraciones en un formato simple [clave => valor]
     * Útil para enviar a Inertia/Vue
     */
    public static function getAll()
    {
        return self::all()->pluck('value', 'key');
    }
}
