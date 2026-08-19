<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $settings['company_name'] ?? 'Taller 360' }} | Fabricantes Directos en Tepatitlán</title>
    
    <!-- Meta SEO -->
    <meta name="description" content="Muebles de alta calidad fabricados en Tepatitlán. Venta de mayoreo y menudeo. Catálogo digital de roperos, cómodas, bases y más.">
    <meta name="keywords" content="Muebles, Tepatitlán, Fabricantes, Roperos, Cómodas, Mayoreo, Menudeo">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    @vite('resources/css/app.css')

    <style>
        body { font-family: 'Inter', sans-serif; }
        .hero-pattern {
            background-color: #111827;
            background-image: radial-gradient(#374151 1px, transparent 1px);
            background-size: 32px 32px;
        }
    </style>
</head>
<body class="bg-white text-gray-900 antialiased flex flex-col min-h-screen">

    @php
        // Pre-procesar número de WhatsApp
        $rawPhone = $settings['company_whatsapp'] ?? ($settings['company_phone'] ?? '');
        $waNumber = preg_replace('/[^0-9]/', '', $rawPhone);
        if(strlen($waNumber) == 10 && substr($waNumber, 0, 1) != '5') {
            $waNumber = '52' . $waNumber;
        }
        $waUrl = $waNumber ? "https://wa.me/{$waNumber}?text=" . urlencode("Hola, me interesa conocer más sobre sus muebles.") : "#";
    @endphp

    <!-- NAVBAR -->
    <nav class="fixed w-full z-50 bg-white/90 backdrop-blur-md border-b border-gray-100 shadow-sm transition-all duration-300" id="navbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
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
                    <a href="#nosotros" class="text-sm font-medium text-gray-600 hover:text-red-600 transition-colors">Nosotros</a>
                    <a href="#ventajas" class="text-sm font-medium text-gray-600 hover:text-red-600 transition-colors">Por Qué Elegirnos</a>
                    <a href="#contacto" class="text-sm font-medium text-gray-600 hover:text-red-600 transition-colors">Contacto</a>
                    <a href="{{ route('catalog.index') }}" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2.5 rounded-full text-sm font-semibold transition-all shadow-lg shadow-red-600/30 hover:shadow-red-600/50 hover:-translate-y-0.5">
                        Ver Catálogo
                    </a>
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
        </div>

        <!-- Mobile Menu Panel -->
        <div id="mobileMenu" class="hidden md:hidden bg-white border-t border-gray-100">
            <div class="px-4 pt-2 pb-6 space-y-1 shadow-xl">
                <a href="#nosotros" class="block px-3 py-3 text-base font-medium text-gray-800 hover:bg-gray-50 rounded-md" onclick="toggleMobileMenu()">Nosotros</a>
                <a href="#ventajas" class="block px-3 py-3 text-base font-medium text-gray-800 hover:bg-gray-50 rounded-md" onclick="toggleMobileMenu()">Ventajas</a>
                <a href="#contacto" class="block px-3 py-3 text-base font-medium text-gray-800 hover:bg-gray-50 rounded-md" onclick="toggleMobileMenu()">Contacto</a>
                <a href="{{ route('catalog.index') }}" class="block mt-4 px-3 py-3 text-center text-base font-semibold text-white bg-red-600 rounded-lg shadow-md" onclick="toggleMobileMenu()">
                    Catálogo Digital
                </a>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden bg-gray-900 text-white hero-pattern">
        <div class="absolute inset-0 bg-gradient-to-b from-gray-900/50 to-gray-900/90 z-0"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
            <h1 class="text-4xl sm:text-5xl lg:text-7xl font-bold tracking-tight mb-6 leading-tight">
                Fabricantes Directos en <br/>
                <span class="text-red-500">Tepatitlán de Morelos</span>
            </h1>
            <p class="mt-6 text-lg sm:text-xl text-gray-300 max-w-2xl mx-auto mb-10 font-light">
                Diseño, durabilidad y elegancia en cada pieza. Descubre nuestro extenso catálogo de muebles fabricados a medida con la mejor calidad de la región.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                <a href="{{ route('catalog.index') }}" class="w-full sm:w-auto bg-red-600 hover:bg-red-700 text-white px-8 py-4 rounded-full text-lg font-semibold transition-all shadow-lg shadow-red-600/30 hover:shadow-red-600/50 hover:-translate-y-1 flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    Ver Catálogo Completo
                </a>
                <a href="{{ $waUrl }}" target="_blank" class="w-full sm:w-auto bg-white/10 hover:bg-white/20 text-white border border-white/30 px-8 py-4 rounded-full text-lg font-semibold transition-all backdrop-blur-sm flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.183-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.765-5.77zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.274.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824z"/></svg>
                    Cotizar por WhatsApp
                </a>
            </div>
        </div>
    </section>

    <!-- SECCIÓN NOSOTROS -->
    <section id="nosotros" class="py-24 bg-white relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div>
                    <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-6 relative inline-block">
                        Calidad de Origen
                        <div class="absolute -bottom-2 left-0 w-1/3 h-1.5 bg-red-600 rounded-full"></div>
                    </h2>
                    <p class="text-lg text-gray-600 mb-6 leading-relaxed">
                        En <span class="font-semibold text-gray-900">{{ $settings['company_name'] ?? 'Taller 360' }}</span>, no solo vendemos muebles, los fabricamos con orgullo desde Tepatitlán de Morelos. Nuestra pasión es transformar materiales de primera calidad en piezas que dan vida a tu hogar.
                    </p>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <span class="flex-shrink-0 w-6 h-6 rounded-full bg-red-100 text-red-600 flex items-center justify-center mt-1 mr-3">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <span class="text-gray-700"><strong>Fabricación a Medida:</strong> Entendemos tus necesidades y adaptamos nuestros diseños para encajar en tus espacios.</span>
                        </li>
                        <li class="flex items-start">
                            <span class="flex-shrink-0 w-6 h-6 rounded-full bg-red-100 text-red-600 flex items-center justify-center mt-1 mr-3">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <span class="text-gray-700"><strong>Trato Directo:</strong> Sin intermediarios. Al comprarnos, aseguras el mejor precio y garantía directa de fábrica.</span>
                        </li>
                    </ul>
                </div>
                <div class="relative">
                    <div class="aspect-4/3 rounded-2xl overflow-hidden bg-gray-100 shadow-2xl relative z-10">
                        <img src="https://images.unsplash.com/photo-1595515106969-1ce29566ff1c?auto=format&fit=crop&q=80&w=1000" alt="Fabricación de muebles" class="w-full h-full object-cover">
                    </div>
                    <div class="absolute -bottom-6 -left-6 w-32 h-32 bg-red-600 rounded-full opacity-20 blur-2xl z-0"></div>
                    <div class="absolute -top-6 -right-6 w-40 h-40 bg-gray-900 rounded-full opacity-10 blur-2xl z-0"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- PREVIEW DEL CATÁLOGO -->
    <section class="py-24 bg-gray-50 border-y border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">Nuestros Favoritos</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Un vistazo a los modelos que nuestros clientes prefieren. Diseños exclusivos de nuestra línea.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
                @forelse($previewProducts as $product)
                    @php
                        $safeImageUrl = $product->image_url;
                    @endphp
                    <a href="{{ route('catalog.index') }}" class="group block">
                        <div class="relative overflow-hidden rounded-2xl bg-white aspect-square shadow-sm group-hover:shadow-xl transition-all duration-300 mb-4">
                            @if($safeImageUrl)
                                <img src="{{ $safeImageUrl }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-400">
                                    <svg class="w-12 h-12 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            @endif
                            <div class="absolute inset-0 bg-gray-900/0 group-hover:bg-gray-900/10 transition-colors duration-300"></div>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 truncate">{{ $product->name }}</h3>
                        <p class="text-sm text-red-600 font-medium">{{ $product->category->name ?? 'Mueble' }}</p>
                    </a>
                @empty
                    <div class="col-span-full text-center py-12">
                        <p class="text-gray-500">Próximamente más modelos en nuestro catálogo.</p>
                    </div>
                @endforelse
            </div>

            <div class="text-center">
                <a href="{{ route('catalog.index') }}" class="inline-flex items-center gap-2 bg-gray-900 hover:bg-gray-800 text-white px-8 py-4 rounded-full text-lg font-semibold transition-all shadow-lg hover:shadow-xl hover:-translate-y-1">
                    Explorar el Catálogo Digital
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>
    </section>

    <!-- VENTAJAS -->
    <section id="ventajas" class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                <div class="text-center">
                    <div class="w-16 h-16 bg-red-50 text-red-600 rounded-2xl flex items-center justify-center mx-auto mb-6 transform -rotate-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Mayoreo y Menudeo</h3>
                    <p class="text-gray-600">Atendemos pedidos por volumen para mueblerías y distribuidores, así como ventas individuales para tu hogar.</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 bg-red-50 text-red-600 rounded-2xl flex items-center justify-center mx-auto mb-6 transform rotate-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Materiales de Calidad</h3>
                    <p class="text-gray-600">Trabajamos con maderas seleccionadas, MDF y melaminas texturizadas para garantizar resistencia y estética.</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 bg-red-50 text-red-600 rounded-2xl flex items-center justify-center mx-auto mb-6 transform -rotate-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Entregas a Tiempo</h3>
                    <p class="text-gray-600">Compromiso real en nuestros tiempos de producción y entrega. Sabemos que tu tiempo es valioso.</p>
                </div>
            </div>
        </div>
    </section>

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

    <!-- Script Menu Móvil -->
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
    </script>
</body>
</html>
