@extends('layouts.public')

@section('content')
<!-- Hero Section -->
<section class="relative h-[50vh] bg-stone-900 flex items-center justify-center text-center px-4">
    <div class="space-y-4">
        <h6 class="text-red-500 font-bold tracking-[0.2em] uppercase">Excelencia en Madera</h6>
        <h1 class="text-5xl md:text-6xl font-bold text-white uppercase tracking-tight">Industria Santa Cecilia</h1>
        <p class="text-stone-300 italic">Fabricantes directos de muebles de alta calidad para tu hogar.</p>
    </div>
</section>

<!-- Categorías (Shop by Category) -->
<section class="max-w-7xl mx-auto py-16 px-4">
    <h2 class="text-3xl font-bold text-center mb-12 uppercase border-b-2 border-red-600 inline-block mx-auto">Nuestro Catálogo</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-8">
        <!-- Ejemplo: Componente Categoría -->
        <div class="relative h-64 bg-stone-200 group overflow-hidden">
            <div class="absolute inset-0 bg-black/40 group-hover:bg-black/60 transition"></div>
            <div class="absolute inset-0 flex items-center justify-center">
                <h3 class="text-white text-2xl font-bold uppercase">Roperos</h3>
            </div>
        </div>
        <div class="relative h-64 bg-stone-200 group overflow-hidden">
            <div class="absolute inset-0 bg-black/40 group-hover:bg-black/60 transition"></div>
            <div class="absolute inset-0 flex items-center justify-center">
                <h3 class="text-white text-2xl font-bold uppercase">Trincheros</h3>
            </div>
        </div>
        <div class="relative h-64 bg-stone-200 group overflow-hidden">
            <div class="absolute inset-0 bg-black/40 group-hover:bg-black/60 transition"></div>
            <div class="absolute inset-0 flex items-center justify-center">
                <h3 class="text-white text-2xl font-bold uppercase">Bases</h3>
            </div>
        </div>
        <!-- Repite para Trincheros, Bases, etc. -->
    </div>
</section>

<!-- Catálogo / Tarjetas de Producto -->
<section id="catalogo" class="max-w-7xl mx-auto py-16 px-6 bg-stone-50">
    <div class="text-center mb-12">
        <h2 class="text-3xl font-bold uppercase tracking-wide">Nuestros Productos</h2>
        <p class="text-stone-500 text-sm mt-1">Calidad garantizada de fábrica</p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <!-- Tarjeta de Producto 1 -->
        <div class="group bg-white border border-stone-200 flex flex-col justify-between overflow-hidden shadow-sm hover:shadow-md transition">
            <div>
                <div class="aspect-[4/3] bg-stone-100 overflow-hidden relative">
                    <img src="{{ asset('img/ropero-placeholder.jpg') }}" alt="Roperos" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                    <span class="absolute top-3 left-3 bg-black text-white text-[10px] font-bold uppercase px-2.5 py-1">Ropero</span>
                </div>
                <div class="p-6">
                    <h3 class="font-bold text-xl uppercase tracking-tight">Ropero Clásico Santa Cecilia</h3>
                    <p class="text-stone-500 text-sm mt-2">Fabricado en madera de pino y triplay reforzado, amplio espacio interior y cajonera.</p>
                </div>
            </div>
            <div class="p-6 pt-0 flex items-center justify-between border-t border-stone-100 mt-4">
                <span class="font-bold text-lg text-red-600">Cotizar</span>
                <a href="#contacto" class="bg-black text-white text-xs font-bold uppercase px-4 py-2 hover:bg-red-600 transition">Me interesa</a>
            </div>
        </div>

        <!-- Tarjeta de Producto 2 -->
        <div class="group bg-white border border-stone-200 flex flex-col justify-between overflow-hidden shadow-sm hover:shadow-md transition">
            <div>
                <div class="aspect-[4/3] bg-stone-100 overflow-hidden relative">
                    <img src="{{ asset('img/trinchero-placeholder.jpg') }}" alt="Trincheros" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                    <span class="absolute top-3 left-3 bg-black text-white text-[10px] font-bold uppercase px-2.5 py-1">Trinchero</span>
                </div>
                <div class="p-6">
                    <h3 class="font-bold text-xl uppercase tracking-tight">Trinchero Colonial 3 Puertas</h3>
                    <p class="text-stone-500 text-sm mt-2">Diseño elegante con puertas de cristal y compartimentos inferiores de gran capacidad.</p>
                </div>
            </div>
            <div class="p-6 pt-0 flex items-center justify-between border-t border-stone-100 mt-4">
                <span class="font-bold text-lg text-red-600">Cotizar</span>
                <a href="#contacto" class="bg-black text-white text-xs font-bold uppercase px-4 py-2 hover:bg-red-600 transition">Me interesa</a>
            </div>
        </div>

        <!-- Tarjeta de Producto 3 -->
        <div class="group bg-white border border-stone-200 flex flex-col justify-between overflow-hidden shadow-sm hover:shadow-md transition">
            <div>
                <div class="aspect-[4/3] bg-stone-100 overflow-hidden relative">
                    <img src="{{ asset('img/base-placeholder.jpg') }}" alt="Bases" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                    <span class="absolute top-3 left-3 bg-black text-white text-[10px] font-bold uppercase px-2.5 py-1">Base</span>
                </div>
                <div class="p-6">
                    <h3 class="font-bold text-xl uppercase tracking-tight">Base Matrimonial Reforzada</h3>
                    <p class="text-stone-500 text-sm mt-2">Estructura sólida de madera pura, diseñada para máxima durabilidad y soporte.</p>
                </div>
            </div>
            <div class="p-6 pt-0 flex items-center justify-between border-t border-stone-100 mt-4">
                <span class="font-bold text-lg text-red-600">Cotizar</span>
                <a href="#contacto" class="bg-black text-white text-xs font-bold uppercase px-4 py-2 hover:bg-red-600 transition">Me interesa</a>
            </div>
        </div>
    </div>
</section>

<!-- Formulario de Contacto -->
<section id="contacto" class="max-w-3xl mx-auto py-16 px-6">
    <div class="text-center mb-10">
        <h2 class="text-3xl font-bold uppercase tracking-wide">Formulario de Contacto</h2>
        <p class="text-stone-500 text-sm mt-1">¿Buscas un mueble sobre medida o deseas cotizar? Escríbenos.</p>
    </div>
    
    <form action="#" method="POST" class="space-y-6 bg-white p-8 border border-stone-200 shadow-sm">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-stone-700 mb-2">Nombre completo</label>
                <input type="text" class="w-full border border-stone-300 p-3 text-sm focus:outline-none focus:border-red-600" placeholder="Tu nombre">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-stone-700 mb-2">Teléfono / WhatsApp</label>
                <input type="text" class="w-full border border-stone-300 p-3 text-sm focus:outline-none focus:border-red-600" placeholder="Tu número">
            </div>
        </div>
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-stone-700 mb-2">Mensaje o mueble de interés</label>
            <textarea rows="4" class="w-full border border-stone-300 p-3 text-sm focus:outline-none focus:border-red-600" placeholder="Cuéntanos qué necesitas..."></textarea>
        </div>
        <button type="submit" class="w-full bg-red-600 text-white font-bold py-3 text-xs uppercase tracking-widest hover:bg-red-700 transition">Enviar Mensaje</button>
    </form>
</section>

@endsection