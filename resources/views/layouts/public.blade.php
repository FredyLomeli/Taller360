<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo - Taller 360</title>
    <!-- Esto asume que tienes configurado Vite en tu Laravel -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-gray-900 antialiased">
    
    <header class="bg-black text-white border-b-4 border-red-600">
        <nav class="max-w-7xl mx-auto px-6 py-3 flex justify-between items-center">
            <!-- Logo -->
            <a href="/" class="flex items-center space-x-3">
                <img src="{{ asset('storage/logos/qfcLzQYAU9wOPCfZ4piXyaLTILwb4vYFkIUsD8cP.png') }}" alt="Logo Industria Santa Cecilia" class="h-12 w-auto">
                <!-- Opcional: si el logo solo es el símbolo, podrías agregar el texto aquí -->
            </a>

            <!-- Menú -->
            <div class="hidden md:flex space-x-8 font-bold text-sm uppercase tracking-widest">
                <a href="/catalogo" class="hover:text-red-500 transition">Roperos</a>
                <a href="#" class="hover:text-red-500 transition">Trincheros</a>
                <a href="#" class="hover:text-red-500 transition">Bases</a>
            </div>

            <!-- Acceso -->
            <a href="/login" class="bg-red-600 px-5 py-2 text-xs font-bold uppercase hover:bg-red-700 transition">Ingresar</a>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <!-- Footer sencillo -->
    <footer class="bg-black text-white py-12 border-t-4 border-red-600 mt-12">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-3 gap-8 text-center md:text-left">
            <div>
                <h4 class="font-bold text-lg uppercase tracking-widest text-red-600 mb-3">ISC Tepatitlán</h4>
                <p class="text-stone-400 text-sm">Industria Santa Cecilia. Fabricantes directos de muebles en Tepatitlán de Morelos, Jalisco.</p>
            </div>
            <div>
                <h4 class="font-bold text-sm uppercase tracking-widest text-stone-300 mb-3">Navegación</h4>
                <ul class="space-y-2 text-stone-400 text-sm">
                    <li><a href="#catalogo" class="hover:text-red-500 transition">Catálogo</a></li>
                    <li><a href="#contacto" class="hover:text-red-500 transition">Contacto</a></li>
                    <li><a href="/login" class="hover:text-red-500 transition">Acceso Admin</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-sm uppercase tracking-widest text-stone-300 mb-3">Ubicación</h4>
                <p class="text-stone-400 text-sm">Tepatitlán de Morelos, Jalisco, México.</p>
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-6 border-t border-stone-800 mt-8 pt-8 text-center text-stone-500 text-xs">
            <p>Copyright &copy; {{ date('Y') }} Industria Santa Cecilia (ISC). Todos los derechos reservados.</p>
        </div>
    </footer>

</body>
</html>