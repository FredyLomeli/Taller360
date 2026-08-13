<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $settings['company_name'] ?? 'Mueblería' }} | Catálogo</title>
    
    <!-- Meta SEO -->
    <meta name="description" content="Catálogo exclusivo de muebles. Descubre nuestros diseños de alta gama y solicita una asesoría personalizada.">
    <meta name="keywords" content="Muebles, Catálogo Digital, Alta Gama, Catálogo">
    
    <!-- Tailwind CSS (Cargado por Vite en Laravel) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        /* Animaciones sutiles adicionales */
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-[#fafafa] text-gray-800 font-sans antialiased min-h-screen flex flex-col">

    <!-- NAVBAR -->
    <nav class="sticky top-0 z-50 w-full bg-white border-b border-gray-100 shadow-sm transition-all duration-300" id="navbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-20">
                <div class="flex items-center">
                    <a href="{{ route('landing') }}" class="flex items-center gap-3 group">
                        @if(!empty($settings['company_logo']))
                            <img src="{{ asset('storage/' . $settings['company_logo']) }}" alt="Logo" class="h-10 w-auto object-contain transition-transform group-hover:scale-105">
                        @else
                            <div class="w-10 h-10 bg-red-600 rounded-lg flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-red-600/30">
                                360
                            </div>
                        @endif
                        <span class="text-xl font-bold tracking-tight text-gray-900 group-hover:text-red-600 transition-colors">
                            {{ $settings['company_name'] ?? 'Taller 360' }}
                        </span>
                    </a>
                </div>
                
                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="{{ route('landing') }}#nosotros" class="text-sm font-medium text-gray-600 hover:text-red-600 transition-colors">Nosotros</a>
                    <a href="{{ route('landing') }}#ventajas" class="text-sm font-medium text-gray-600 hover:text-red-600 transition-colors">Por Qué Elegirnos</a>
                    <a href="{{ route('landing') }}#contacto" class="text-sm font-medium text-gray-600 hover:text-red-600 transition-colors">Contacto</a>
                    @if(isset($settings['company_whatsapp']))
                        @php
                            $waNumber = preg_replace('/[^0-9]/', '', $settings['company_whatsapp']);
                        @endphp
                        <a href="https://wa.me/{{ $waNumber }}?text=Hola,%20me%20gustar%C3%ADa%20recibir%20asesor%C3%ADa." target="_blank" class="bg-black hover:bg-gray-800 text-white px-6 py-2.5 rounded-full text-sm font-semibold transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.274.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.418-.1.824zm-3.423-14.416c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm.029 18.88c-1.161 0-2.305-.292-3.318-.844l-3.677.964.984-3.595c-.607-1.052-.927-2.246-.926-3.468.001-3.825 3.113-6.937 6.937-6.937 3.825.001 6.938 3.113 6.939 6.938-.001 3.825-3.114 6.937-6.939 6.942z"/></svg>
                            Cotizar
                        </a>
                    @endif
                </div>

                <!-- Mobile Menu Button -->
                <div class="flex items-center md:hidden">
                    <button type="button" onclick="toggleMobileMenu()" class="text-gray-600 hover:text-gray-900 focus:outline-none p-2">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>

        <!-- Mobile Menu Panel -->
        <div id="mobileMenu" class="hidden md:hidden bg-white border-t border-gray-100">
            <div class="px-4 pt-2 pb-6 space-y-1 shadow-xl">
                <a href="{{ route('landing') }}#nosotros" class="block px-3 py-3 text-base font-medium text-gray-800 hover:bg-gray-50 rounded-md" onclick="toggleMobileMenu()">Nosotros</a>
                <a href="{{ route('landing') }}#ventajas" class="block px-3 py-3 text-base font-medium text-gray-800 hover:bg-gray-50 rounded-md" onclick="toggleMobileMenu()">Ventajas</a>
                <a href="{{ route('landing') }}#contacto" class="block px-3 py-3 text-base font-medium text-gray-800 hover:bg-gray-50 rounded-md" onclick="toggleMobileMenu()">Contacto</a>
                @if(isset($settings['company_whatsapp']))
                    <a href="https://wa.me/{{ $waNumber ?? '' }}?text=Hola,%20me%20gustar%C3%ADa%20recibir%20asesor%C3%ADa." class="block mt-4 px-3 py-3 text-center text-base font-semibold text-white bg-black rounded-lg shadow-md" onclick="toggleMobileMenu()">
                        Cotizar por WhatsApp
                    </a>
                @endif
            </div>
        </div>
    </nav>

    <!-- Banner Hero Catálogo Centrado y Ajustado -->
    <section class="w-full bg-[#0B1120] text-white py-8 sm:py-10 my-0 relative overflow-hidden">
        <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
                Colección <span class="text-red-600">Exclusiva</span>
            </h1>
            <p class="text-slate-300 text-xs sm:text-sm max-w-2xl mx-auto mt-2 leading-relaxed font-light">
                Descubre la excelencia en madera. Cada pieza es diseñada con atención al detalle y la máxima calidad de fabricación.
            </p>
        </div>
    </section>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full">
        
        <!-- Pestañas de Filtro (JS Nativo) -->
        <div class="flex flex-wrap justify-center gap-3 mb-12">
            <button class="filter-btn active bg-red-600 text-white border-red-600 px-6 py-2 rounded-full text-sm font-semibold transition-all border shadow-sm" data-filter="all">
                Todos
            </button>
            @foreach($categories as $category)
                <button class="filter-btn bg-white text-gray-600 border-gray-300 hover:border-gray-500 hover:text-gray-900 px-6 py-2 rounded-full text-sm font-medium transition-all border shadow-sm" data-filter="category-{{ $category->id }}">
                    {{ $category->name }}
                </button>
            @endforeach
        </div>

        <!-- Grid de Productos -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-8 gap-y-12" id="products-grid">
            @php $globalIndex = 0; @endphp
            @foreach($categories as $category)
                @foreach($category->products as $product)
                    @php
                        $safeImageUrl = $product->image ? asset('storage/products/' . implode('/', array_map('rawurlencode', explode('/', $product->image)))) : null;
                    @endphp
                    <div class="product-card group fade-in cursor-pointer" data-category="category-{{ $category->id }}" onclick="openProductModal({{ $globalIndex }})">
                        @php $globalIndex++; @endphp
                        
                        <div class="relative w-full aspect-square bg-slate-50/80 rounded-2xl overflow-hidden flex items-center justify-center p-3 mb-4 shadow-sm group-hover:shadow-xl transition-all duration-500">
                            @if($product->image)
                                <img src="{{ $safeImageUrl }}" alt="{{ $product->name }}" class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-500">
                            @else
                                <div class="w-full h-full bg-slate-100 flex flex-col items-center justify-center p-6 text-slate-400 group-hover:bg-slate-200/60 transition-colors">
                                    <!-- Ícono SVG elegante de Mueble / Catálogo -->
                                    <svg class="w-12 h-12 mb-2 stroke-1 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                    <span class="text-[11px] font-semibold uppercase tracking-widest text-slate-400 text-center">Imagen no disponible</span>
                                    <span class="text-[10px] text-slate-400/80 text-center mt-0.5">Consulte variantes y medidas</span>
                                </div>
                            @endif

                            @if($product->is_favorite)
                                <div class="absolute top-3 right-3 bg-red-600 text-white px-2.5 py-1 rounded shadow text-xs font-bold tracking-widest uppercase">
                                    Destacado
                                </div>
                            @endif
                        </div>

                        <div class="px-1">
                            <h3 class="text-lg font-bold text-gray-900 mb-1 group-hover:text-red-600 transition-colors">{{ $product->name }}</h3>
                            <span class="text-xs font-semibold text-red-600 uppercase tracking-wider block mt-1 mb-3">{{ $product->category->name ?? 'Mueble' }}</span>
                            <span class="inline-flex items-center text-sm font-semibold text-gray-900 group-hover:text-red-600 group-hover:underline decoration-2 underline-offset-4 transition-colors">
                                Ver Variantes <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            </span>
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>

        <!-- Empty State -->
        <div id="empty-state" class="hidden text-center py-20">
            <p class="text-xl text-gray-500 font-medium">No se encontraron productos en esta categoría.</p>
        </div>
    </main>


    <!-- MODAL PRODUCTO (JavaScript Nativo) -->
    <div id="productModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm transition-opacity opacity-0" id="modalBackdrop" onclick="closeProductModal()"></div>
        
        <!-- Flechas de navegación globales del modal -->
        <button id="modalPrevBtn" onclick="prevProduct(event)" class="fixed left-2 sm:left-6 top-1/2 -translate-y-1/2 z-[60] bg-white/90 hover:bg-white text-gray-900 p-3 sm:p-4 rounded-full shadow-2xl transition-all backdrop-blur-md border border-gray-200 hidden opacity-0 translate-x-4">
            <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <button id="modalNextBtn" onclick="nextProduct(event)" class="fixed right-2 sm:right-6 top-1/2 -translate-y-1/2 z-[60] bg-white/90 hover:bg-white text-gray-900 p-3 sm:p-4 rounded-full shadow-2xl transition-all backdrop-blur-md border border-gray-200 hidden opacity-0 -translate-x-4">
            <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
        </button>

        <div class="fixed inset-0 z-10 overflow-y-auto pointer-events-none">
            <div class="flex min-h-full items-end justify-center p-0 text-center sm:items-center sm:p-4 pointer-events-none">
                <!-- Panel del modal -->
                <div class="relative transform overflow-hidden bg-white text-left shadow-2xl transition-all duration-300 sm:my-8 w-full sm:max-w-4xl rounded-t-3xl sm:rounded-3xl max-h-[90vh] opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 flex flex-col md:flex-row pointer-events-auto" id="modalPanel">
                    
                    <!-- Botón Cerrar (Mobile y Desktop) -->
                    <button type="button" onclick="closeProductModal()" class="absolute top-4 right-4 z-20 text-gray-400 hover:text-gray-900 bg-white/50 hover:bg-white backdrop-blur-md rounded-full p-2 transition-all">
                        <span class="sr-only">Cerrar</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>

                    <!-- Lado Izquierdo: Imagen -->
                    <div class="w-full md:w-1/2 bg-slate-100 h-64 md:h-auto relative flex items-center justify-center shrink-0">
                        <img id="modalImage" src="" alt="" class="w-full h-full object-cover absolute inset-0">
                        <div id="modalNoImage" class="hidden absolute inset-0 flex flex-col items-center justify-center p-6 text-slate-400">
                             <svg class="w-16 h-16 mb-4 stroke-1 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                 <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                             </svg>
                             <span class="text-[12px] font-semibold uppercase tracking-widest text-slate-400 text-center">Imagen no disponible</span>
                             <span class="text-[11px] text-slate-400/80 text-center mt-1">Consulte más detalles de diseño con un asesor</span>
                        </div>
                    </div>

                    <!-- Lado Derecho: Contenido -->
                    <div class="w-full md:w-1/2 p-6 sm:p-8 overflow-y-auto max-h-[90vh] flex flex-col justify-between">
                        <div class="mb-2">
                            <span id="modalCategory" class="text-xs font-bold text-gray-500 uppercase tracking-widest">Categoría</span>
                        </div>
                        <h2 id="modalTitle" class="text-3xl font-black text-gray-900 mb-4 leading-tight">Nombre Mueble</h2>
                        <p id="modalDescription" class="text-gray-600 mb-8 leading-relaxed">Descripción</p>

                        <div class="mb-8 flex-grow space-y-6">
                            <!-- Medidas -->
                            <div>
                                <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3">Medidas Disponibles</h4>
                                <div id="modalMeasurements" class="flex flex-wrap gap-2">
                                    <!-- Llenado por JS -->
                                </div>
                            </div>
                            
                            <!-- Colores -->
                            <div>
                                <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3">Acabados y Colores</h4>
                                <div id="modalColors" class="flex flex-col">
                                    <!-- Llenado por JS -->
                                </div>
                            </div>
                        </div>

                        <!-- Botón WhatsApp Dinámico -->
                        <div class="pt-6 border-t mt-auto">
                            @if(isset($settings['company_whatsapp']))
                                @php
                                    $waNumber = preg_replace('/[^0-9]/', '', $settings['company_whatsapp']);
                                @endphp
                                <a id="modalWhatsAppBtn" href="#" target="_blank" class="flex justify-center items-center w-full bg-emerald-600 hover:bg-emerald-500 text-white px-6 py-4 rounded-xl text-lg font-bold transition-colors shadow-lg hover:shadow-xl gap-3">
                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.274.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.418-.1.824zm-3.423-14.416c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm.029 18.88c-1.161 0-2.305-.292-3.318-.844l-3.677.964.984-3.595c-.607-1.052-.927-2.246-.926-3.468.001-3.825 3.113-6.937 6.937-6.937 3.825.001 6.938 3.113 6.939 6.938-.001 3.825-3.114 6.937-6.939 6.942z"/></svg>
                                    Cotizar este Modelo
                                </a>
                                <p class="text-center text-[11.5px] text-gray-500 mt-2.5 font-medium">Respuesta inmediata de nuestro equipo de ventas</p>
                            @else
                                <div class="bg-gray-100 text-center p-4 rounded-xl text-gray-600 font-medium">
                                    Por favor contacta a nuestro equipo para cotizar este modelo.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CONTACTO Y UBICACIÓN -->
    <section id="contacto" class="bg-gray-900 py-24 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-16">
                <div>
                    <h2 class="text-3xl font-bold mb-6">Visítanos o Contáctanos</h2>
                    <p class="text-gray-400 mb-8">Estamos listos para asesorarte y encontrar el mueble perfecto para ti. Habla directamente con los fabricantes.</p>
                    
                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center shrink-0 mr-4">
                                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg">Ubicación</h4>
                                <p class="text-gray-400">Tepatitlán de Morelos, Jalisco, México.</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center shrink-0 mr-4">
                                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg">Teléfono / WhatsApp</h4>
                                <p class="text-gray-400">{{ $settings['company_whatsapp'] ?? ($settings['company_phone'] ?? 'Próximamente') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white/5 backdrop-blur-md p-8 rounded-2xl border border-white/10">
                    <h3 class="text-2xl font-bold mb-6">Inicia tu Cotización</h3>
                    <p class="text-gray-400 mb-8">Envíanos un mensaje por WhatsApp para consultar disponibilidad, precios de mayoreo o medidas personalizadas.</p>
                    @php
                        $rawPhone = $settings['company_whatsapp'] ?? ($settings['company_phone'] ?? '');
                        $waNumber = preg_replace('/[^0-9]/', '', $rawPhone);
                        if(strlen($waNumber) == 10 && substr($waNumber, 0, 1) != '5') {
                            $waNumber = '52' . $waNumber;
                        }
                        $waUrl = $waNumber ? "https://wa.me/{$waNumber}?text=" . urlencode("Hola, me interesa conocer más sobre sus muebles.") : "#";
                    @endphp
                    <a href="{{ $waUrl }}" target="_blank" class="w-full bg-red-600 hover:bg-red-700 text-white px-6 py-4 rounded-xl text-lg font-bold transition-colors flex items-center justify-center gap-3">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.183-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.765-5.77zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.274.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824z"/></svg>
                        Abrir WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="bg-gray-950 text-gray-500 py-12 text-center border-t border-gray-800 mt-auto">
        <div class="max-w-7xl mx-auto px-4">
            <p>&copy; {{ date('Y') }} {{ $settings['company_name'] ?? 'Taller 360' }}. Todos los derechos reservados.</p>
            <p class="text-sm mt-2">Fabricado en Tepatitlán de Morelos, Jal.</p>
        </div>
    </footer>

    <!-- Scripts Javascript Nativo -->
    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
        }

        // Navbar shadow on scroll
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('navbar');
            if (window.scrollY > 10) {
                nav.classList.add('shadow-md');
                nav.classList.remove('shadow-sm');
            } else {
                nav.classList.remove('shadow-md');
                nav.classList.add('shadow-sm');
            }
        });
        // --- 1. Estado Global de Productos ---
        const allProducts = [
            @foreach($categories as $category)
                @foreach($category->products as $product)
                    @php
                        $safeImageUrl = $product->image ? asset('storage/products/' . implode('/', array_map('rawurlencode', explode('/', $product->image)))) : null;
                    @endphp
                    {
                        id: {{ $product->id }},
                        name: @json($product->name),
                        description: @json($product->description),
                        image: @json($safeImageUrl),
                        category: @json($category->name),
                        category_id: 'category-{{ $category->id }}',
                        variants: @json($product->variants)
                    },
                @endforeach
            @endforeach
        ];

        let currentFilter = 'all';
        let visibleProducts = [];
        let currentIndex = 0;

        function updateVisibleProducts() {
            if (currentFilter === 'all') {
                visibleProducts = allProducts.map((p, i) => i);
            } else {
                visibleProducts = allProducts.map((p, i) => i).filter(i => allProducts[i].category_id === currentFilter);
            }
        }
        updateVisibleProducts(); // inicializar

        // --- 2. Lógica de Filtros ---
        const filterBtns = document.querySelectorAll('.filter-btn');
        const productCards = document.querySelectorAll('.product-card');
        const emptyState = document.getElementById('empty-state');

        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // Actualizar clases de botones
                filterBtns.forEach(b => {
                    b.classList.remove('bg-red-600', 'text-white', 'border-red-600');
                    b.classList.add('bg-white', 'text-gray-600', 'border-gray-300');
                });
                btn.classList.add('bg-red-600', 'text-white', 'border-red-600');
                btn.classList.remove('bg-white', 'text-gray-600', 'border-gray-300');

                currentFilter = btn.getAttribute('data-filter');
                updateVisibleProducts();
                let count = 0;

                productCards.forEach(card => {
                    if (currentFilter === 'all' || card.getAttribute('data-category') === currentFilter) {
                        card.style.display = 'block';
                        // Pequeño hack para reiniciar la animación
                        card.classList.remove('fade-in');
                        void card.offsetWidth; 
                        card.classList.add('fade-in');
                        count++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (count === 0) {
                    emptyState.classList.remove('hidden');
                } else {
                    emptyState.classList.add('hidden');
                }
            });
        });

        // --- 2. Lógica del Modal ---
        const modal = document.getElementById('productModal');
        const backdrop = document.getElementById('modalBackdrop');
        const panel = document.getElementById('modalPanel');
        
        // Elementos internos del modal
        const mImage = document.getElementById('modalImage');
        const mNoImage = document.getElementById('modalNoImage');
        const mTitle = document.getElementById('modalTitle');
        const mCategory = document.getElementById('modalCategory');
        const mDesc = document.getElementById('modalDescription');
        
        const mMeasurements = document.getElementById('modalMeasurements');
        const mColors = document.getElementById('modalColors');
        
        const mWhatsappBtn = document.getElementById('modalWhatsAppBtn');
        
        // --- Matrices de Fabricación ---
        const categoryMaterials = {
            'ropero': ['MDF', 'Madera y MDF enchapado', 'Melamina'],
            'trinchero': ['Madera', 'Madera y MDF enchapado', 'MDF'],
            'cómoda': ['MDF', 'Madera y MDF enchapado', 'Melamina'],
            'loker': ['MDF', 'Madera y MDF enchapado', 'Melamina'],
            'recámara': ['MDF', 'Madera y MDF enchapado', 'Melamina'],
            'comedor': ['MDF', 'Madera y MDF enchapado', 'Melamina'],
            'base': ['MDF', 'Madera']
        };

        const materialColors = {
            'MDF': ['Chocolate', 'Nogal', 'Blanco', 'Gris', 'Cherry', '258'],
            'MADERA': ['Chocolate', 'Caoba', 'Tabaco', 'Cherry'],
            'MELAMINA': ['Fresno Andino', 'Parota', 'Nogal Africano', 'Gris Cenizo', 'Gris Antracita', 'Tzalam', 'Moka']
        };

        const colorHex = {
            'Chocolate': '#3B2314', 'Caoba': '#4A1525', 'Tabaco': '#422A1D', 'Cherry': '#722F37', 'Nogal': '#5C3A21',
            'Blanco': '#FFFFFF', 'Gris': '#9CA3AF', 'Gris Antracita': '#374151', 'Gris Cenizo': '#6B7280',
            'Parota': '#8B5A2B', 'Tzalam': '#6F4E37', 'Moka': '#4A3728', 'Fresno Andino': '#D4C4A8', 'Nogal Africano': '#50382B',
            '258': '#A08A75'
        };

        @if(isset($waNumber))
            const baseWaUrl = "https://wa.me/{{ $waNumber }}?text=";
        @else
            const baseWaUrl = "";
        @endif

        function renderModalData(product) {
            // Llenar datos textuales
            mTitle.textContent = product.name;
            mCategory.textContent = product.category;
            
            if (product.description && product.description.trim() !== '' && product.description !== 'Sin descripción disponible para este modelo.') {
                mDesc.textContent = product.description;
                mDesc.classList.remove('hidden');
                mDesc.classList.add('mb-8');
            } else {
                mDesc.textContent = '';
                mDesc.classList.add('hidden');
                mDesc.classList.remove('mb-8');
            }
            
            // Llenar imagen
            if (product.image) {
                mImage.src = product.image;
                mImage.classList.remove('hidden');
                mNoImage.classList.add('hidden');
            } else {
                mImage.src = '';
                mImage.classList.add('hidden');
                mNoImage.classList.remove('hidden');
            }

            // Limpiar matrices
            mMeasurements.innerHTML = '';
            mColors.innerHTML = '';

            // 1. Extraer medidas únicas de las variantes
            let hasMeasurements = false;
            if (product.variants && product.variants.length > 0) {
                const uniqueMeasurements = [...new Set(product.variants.map(v => v.measurements).filter(m => m))];
                uniqueMeasurements.forEach(m => {
                    hasMeasurements = true;
                    mMeasurements.innerHTML += `<span class="bg-gray-100 text-gray-700 px-3 py-1.5 rounded-lg text-sm border border-gray-200">${m}</span>`;
                });
            }
            if (!hasMeasurements) {
                mMeasurements.innerHTML = `<span class="text-sm text-gray-400 italic">Medidas estándar</span>`;
            }

            // 2. Determinar materiales
            const catName = product.category ? product.category.trim().toLowerCase() : '';
            let materials = [];
            
            for (const key in categoryMaterials) {
                if (catName.includes(key)) {
                    materials = categoryMaterials[key];
                    break;
                }
            }
            if (materials.length === 0) materials = ['MDF', 'Madera']; // Fallback

            // 3. Colores agrupados por material
            let hasAnyColors = false;
            materials.forEach(mat => {
                const upperMat = mat.toUpperCase();
                let colorsForMat = [];
                
                if (materialColors[upperMat]) {
                    colorsForMat = materialColors[upperMat];
                } else if (upperMat.includes('MADERA')) {
                    colorsForMat = materialColors['MADERA'];
                } else if (upperMat.includes('MDF')) {
                    colorsForMat = materialColors['MDF'];
                }

                if (colorsForMat.length > 0) {
                    hasAnyColors = true;
                    let sectionHtml = `<div class="mb-4">
                        <h5 class="text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-2">${mat}</h5>
                        <div class="flex flex-wrap gap-2">`;
                    
                    colorsForMat.forEach(color => {
                        const hex = colorHex[color] || '#CCCCCC';
                        const borderClass = color === 'Blanco' ? 'border border-gray-300' : 'border border-gray-100';
                        sectionHtml += `<span class="inline-flex items-center gap-1.5 bg-white text-gray-700 border border-gray-200 px-3 py-1.5 rounded-full text-sm shadow-sm transition-transform hover:scale-105">
                            <span class="w-3 h-3 rounded-full ${borderClass} shadow-inner" style="background-color: ${hex}"></span>
                            ${color}
                        </span>`;
                    });

                    sectionHtml += `</div></div>`;
                    mColors.innerHTML += sectionHtml;
                }
            });

            if (!hasAnyColors) {
                mColors.innerHTML = `<span class="text-sm text-gray-400 italic">Consultar colores disponibles</span>`;
            }

            // Configurar botón WhatsApp
            if (mWhatsappBtn && baseWaUrl) {
                const text = encodeURIComponent(`Hola, me interesa cotizar el modelo ${product.name} que vi en el catálogo.`);
                mWhatsappBtn.href = baseWaUrl + text;
            }
        }

        function openProductModal(index) {
            currentIndex = index;
            const product = allProducts[index];
            if (!product) return;
            
            renderModalData(product);

            // Mostrar Modal (Remover clase hidden y animar opacidad/transform)
            modal.classList.remove('hidden');
            document.getElementById('modalPrevBtn').classList.remove('hidden');
            document.getElementById('modalNextBtn').classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Evita scroll de fondo
            
            // Timeout pequeño para que Tailwind aplique la transición
            setTimeout(() => {
                backdrop.classList.remove('opacity-0');
                panel.classList.remove('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');
                panel.classList.add('opacity-100', 'translate-y-0', 'sm:scale-100');
                
                document.getElementById('modalPrevBtn').classList.remove('opacity-0', 'translate-x-4');
                document.getElementById('modalNextBtn').classList.remove('opacity-0', '-translate-x-4');
            }, 10);
        }

        function nextProduct(e) {
            if (e) e.stopPropagation();
            if (visibleProducts.length <= 1) return;
            let pos = visibleProducts.indexOf(currentIndex);
            if (pos === -1) pos = 0;
            pos = (pos + 1) % visibleProducts.length;
            currentIndex = visibleProducts[pos];
            animateModalTransition(() => renderModalData(allProducts[currentIndex]));
        }

        function prevProduct(e) {
            if (e) e.stopPropagation();
            if (visibleProducts.length <= 1) return;
            let pos = visibleProducts.indexOf(currentIndex);
            if (pos === -1) pos = 0;
            pos = (pos - 1 + visibleProducts.length) % visibleProducts.length;
            currentIndex = visibleProducts[pos];
            animateModalTransition(() => renderModalData(allProducts[currentIndex]));
        }

        function animateModalTransition(callback) {
            panel.classList.add('opacity-50', 'scale-[0.98]');
            setTimeout(() => {
                callback();
                panel.classList.remove('opacity-50', 'scale-[0.98]');
            }, 150);
        }

        function closeProductModal() {
            // Animar cierre
            backdrop.classList.add('opacity-0');
            panel.classList.remove('opacity-100', 'translate-y-0', 'sm:scale-100');
            panel.classList.add('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');
            
            document.getElementById('modalPrevBtn').classList.add('opacity-0', 'translate-x-4');
            document.getElementById('modalNextBtn').classList.add('opacity-0', '-translate-x-4');
            
            // Esperar a la transición para ocultar el div real
            setTimeout(() => {
                modal.classList.add('hidden');
                document.getElementById('modalPrevBtn').classList.add('hidden');
                document.getElementById('modalNextBtn').classList.add('hidden');
                document.body.style.overflow = ''; // Restaurar scroll
            }, 300);
        }

        // --- 4. Eventos de Navegación (Teclado y Touch) ---
        document.addEventListener('keydown', e => {
            if (!modal.classList.contains('hidden')) {
                if (e.key === 'ArrowLeft') prevProduct();
                if (e.key === 'ArrowRight') nextProduct();
                if (e.key === 'Escape') closeProductModal();
            }
        });

        let touchstartX = 0;
        let touchendX = 0;
        const modalContainer = document.getElementById('productModal');

        modalContainer.addEventListener('touchstart', e => {
            touchstartX = e.changedTouches[0].screenX;
        }, {passive: true});

        modalContainer.addEventListener('touchend', e => {
            touchendX = e.changedTouches[0].screenX;
            handleSwipe();
        }, {passive: true});

        function handleSwipe() {
            if (touchendX < touchstartX - 50) nextProduct(); // Swipe izquierda -> Siguiente
            if (touchendX > touchstartX + 50) prevProduct(); // Swipe derecha -> Anterior
        }
    </script>
</body>
</html>