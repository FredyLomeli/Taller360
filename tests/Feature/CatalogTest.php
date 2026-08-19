<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;

test('el accesor image_url en el modelo Product normaliza correctamente las rutas', function () {
    // 1. Producto sin imagen
    $prodNull = new Product(['image' => null]);
    expect($prodNull->image_url)->toBeNull();

    // 2. Producto con prefijo products/
    $prodWithPrefix = new Product(['image' => 'products/comedor_madera.jpg']);
    expect($prodWithPrefix->image_url)->toBe(asset('storage/products/comedor_madera.jpg'));

    // 3. Producto sin prefijo products/
    $prodWithoutPrefix = new Product(['image' => 'ropero_cedro.jpg']);
    expect($prodWithoutPrefix->image_url)->toBe(asset('storage/products/ropero_cedro.jpg'));

    // 4. Producto con ruta externa
    $prodExternal = new Product(['image' => 'https://images.unsplash.com/photo-sample.jpg']);
    expect($prodExternal->image_url)->toBe('https://images.unsplash.com/photo-sample.jpg');
});

test('el catalogo publico muestra todos los productos si catalog_only_with_images está desactivado', function () {
    Setting::setValue('catalog_only_with_images', '0');

    $category = Category::factory()->create(['name' => 'Salas']);
    
    $prodWithImage = Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Sala Con Foto',
        'image' => 'products/sala.jpg'
    ]);
    ProductVariant::create([
        'product_id' => $prodWithImage->id,
        'material' => 'Lino',
        'measurements' => '2x2',
        'stock' => 5,
        'price_1' => 10000
    ]);

    $prodNoImage = Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Sala Sin Foto',
        'image' => null
    ]);
    ProductVariant::create([
        'product_id' => $prodNoImage->id,
        'material' => 'Piel',
        'measurements' => '3x2',
        'stock' => 2,
        'price_1' => 15000
    ]);

    $response = $this->get(route('catalog.index'));
    $response->assertStatus(200);
    $response->assertSee('Sala Con Foto');
    $response->assertSee('Sala Sin Foto');
});

test('el catalogo publico oculta productos sin imagen cuando catalog_only_with_images está activo', function () {
    Setting::setValue('catalog_only_with_images', '1');

    $category = Category::factory()->create(['name' => 'Recámaras']);
    
    $prodWithImage = Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Cama King Con Foto',
        'image' => 'products/cama.jpg'
    ]);
    ProductVariant::create([
        'product_id' => $prodWithImage->id,
        'material' => 'Parota',
        'measurements' => 'King',
        'stock' => 1,
        'price_1' => 20000
    ]);

    $prodNoImage = Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Buró Sin Foto',
        'image' => null
    ]);
    ProductVariant::create([
        'product_id' => $prodNoImage->id,
        'material' => 'MDF',
        'measurements' => '50x50',
        'stock' => 4,
        'price_1' => 2500
    ]);

    $response = $this->get(route('catalog.index'));
    $response->assertStatus(200);
    $response->assertSee('Cama King Con Foto');
    $response->assertDontSee('Buró Sin Foto');
});

test('la landing page responde correctamente con productos y configuracion de whatsapp', function () {
    Setting::setValue('company_name', 'Mueblería Central');
    Setting::setValue('company_whatsapp', '3311223344');

    $category = Category::factory()->create(['name' => 'Comedores']);
    Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Comedor 6 Sillas',
        'image' => 'products/comedor.jpg',
        'is_favorite' => true
    ]);

    $response = $this->get(route('landing'));
    $response->assertStatus(200);
    $response->assertSee('Mueblería Central');
    $response->assertSee('Comedor 6 Sillas');
    $response->assertSee('wa.me/523311223344', false);
});
