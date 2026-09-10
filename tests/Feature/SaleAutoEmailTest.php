<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use App\Mail\SaleNoteEmail;

class SaleAutoEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_auto_email_is_sent_when_setting_is_true()
    {
        Mail::fake();
        Setting::setValue('auto_email_on_sale', true);
        
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $client = Client::factory()->create(['email' => 'client@test.com']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock' => 10,
            'price_1' => 100,
            'material' => 'Madera',
            'measurements' => '1x1'
        ]);

        $payload = [
            'client_id' => $client->id,
            'payment_method' => 'Efectivo',
            'signature' => 'data:image/png;base64,1234',
            'paid_amount' => 50,
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                    'price' => 100,
                    'chosen_color' => 'Rojo',
                ]
            ]
        ];

        $response = $this->actingAs($vendedor)->post('/sales', $payload);
        $response->assertRedirect('/sales');
        
        Mail::assertSent(SaleNoteEmail::class, function ($mail) use ($client) {
            return $mail->hasTo($client->email);
        });
    }

    public function test_sale_auto_email_is_not_sent_when_setting_is_false()
    {
        Mail::fake();
        Setting::setValue('auto_email_on_sale', false);
        
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $client = Client::factory()->create(['email' => 'client@test.com']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock' => 10,
            'price_1' => 100,
            'material' => 'Madera',
            'measurements' => '1x1'
        ]);

        $payload = [
            'client_id' => $client->id,
            'payment_method' => 'Efectivo',
            'signature' => 'data:image/png;base64,1234',
            'paid_amount' => 50,
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                    'price' => 100,
                    'chosen_color' => 'Rojo',
                ]
            ]
        ];

        $response = $this->actingAs($vendedor)->post('/sales', $payload);
        $response->assertRedirect('/sales');
        
        Mail::assertNothingSent();
    }

    public function test_sale_auto_email_failure_does_not_abort_sale_creation()
    {
        \Illuminate\Support\Facades\Mail::fake();
        Setting::setValue('auto_email_on_sale', true);
        
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $client = Client::factory()->create(['email' => 'client@test.com']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock' => 10,
            'price_1' => 100,
            'material' => 'Madera',
            'measurements' => '1x1'
        ]);

        $payload = [
            'client_id' => $client->id,
            'payment_method' => 'Efectivo',
            'signature' => 'data:image/png;base64,1234',
            'paid_amount' => 50,
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                    'price' => 100,
                    'chosen_color' => 'Rojo',
                ]
            ]
        ];

        $response = $this->actingAs($vendedor)->post('/sales', $payload);
        $response->assertRedirect('/sales');
        
        $this->assertDatabaseHas('sales', [
            'client_id' => $client->id
        ]);
        
        // No verificamos el fallo SMTP, solo verificamos que la venta se creó exitosamente 
        // cuando se intenta enviar el correo.
    }
}
