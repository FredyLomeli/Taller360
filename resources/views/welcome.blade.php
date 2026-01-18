<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Industria Santa Cecilia - Próximamente</title>
        
        <script src="https://cdn.tailwindcss.com"></script>
        
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; }
        </style>
    </head>
    <body class="bg-gray-50 text-gray-800 h-screen flex flex-col relative overflow-hidden">

        <div class="absolute top-0 left-0 w-full h-full overflow-hidden -z-10">
            <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-green-200 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-blob"></div>
            <div class="absolute top-[-10%] right-[-10%] w-96 h-96 bg-yellow-200 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-blob animation-delay-2000"></div>
            <div class="absolute bottom-[-20%] left-[20%] w-96 h-96 bg-pink-100 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-blob animation-delay-4000"></div>
        </div>

        <div class="flex-1 flex flex-col items-center justify-center p-6 text-center z-10">
            
            <div class="mb-8 animate-fade-in-up">
                <span class="bg-green-100 text-green-800 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-widest">
                    Industria Santa Cecilia
                </span>
            </div>

            <h1 class="text-5xl md:text-7xl font-extrabold text-gray-900 mb-6 tracking-tight leading-tight">
                Estamos construyendo <br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-green-600 to-teal-500">
                    algo cómodo para ti.
                </span>
            </h1>

            <p class="text-xl text-gray-600 max-w-2xl mb-10 leading-relaxed">
                Nuestra nueva sala de exhibición digital está en proceso de armado. 
                Pronto podrás explorar nuestro catálogo de muebles desde la comodidad de tu hogar.
            </p>

            <div class="flex gap-4 mb-12 text-4xl text-gray-400 opacity-50">
                <span>🪚</span>
                <span>🪑</span>
                <span>🔨</span>
            </div>

        </div>

        <div class="w-full bg-white/50 backdrop-blur-sm border-t border-gray-200 py-4 px-6 flex justify-between items-center text-sm z-10">
            <p class="text-gray-500">© {{ date('Y') }} Industria Santa Cecilia.</p>
            
            <a href="{{ route('login') }}" class="text-gray-400 hover:text-green-600 font-medium transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                Acceso Personal
            </a>
        </div>

        <style>
            .animate-blob { animation: blob 7s infinite; }
            .animation-delay-2000 { animation-delay: 2s; }
            .animation-delay-4000 { animation-delay: 4s; }
            @keyframes blob {
                0% { transform: translate(0px, 0px) scale(1); }
                33% { transform: translate(30px, -50px) scale(1.1); }
                66% { transform: translate(-20px, 20px) scale(0.9); }
                100% { transform: translate(0px, 0px) scale(1); }
            }
        </style>
    </body>
</html>