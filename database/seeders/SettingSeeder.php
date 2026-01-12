<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            'company_name' => 'Mi Mueblería',
            'company_rfc' => 'XAXX010101000',
            'company_address' => 'Calle Conocida #123, Centro',
            'company_phone' => '333-000-0000',
            'company_logo' => null,
            'notification_emails' => 'admin@ejemplo.com',
            'allow_negative_stock' => '0', // 0 = No, 1 = Sí
            'ticket_footer_text' => '¡Gracias por su compra!'
        ];

        foreach ($defaults as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
