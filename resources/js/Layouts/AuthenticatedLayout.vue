<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
const showingProductMenu = ref(false);
const showingClientMenu = ref(false);

// Control para el menú lateral móvil
const showingNavigationDropdown = ref(false);

// Control manual para el menú de perfil (Drop-up)
const showingProfileMenu = ref(false);
const isSidebarOpen = ref(true);
const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
    // Cierra los submenús al minimizar para que no se vea raro
    if (!isSidebarOpen.value) {
        showingProductMenu.value = false;
        showingClientMenu.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        
        <aside 
            class="bg-green-900 text-white fixed h-full z-20 transition-all duration-300 ease-in-out overflow-hidden flex flex-col"
            :class="isSidebarOpen ? 'w-64' : 'w-20'" 
        >
            <div class="h-16 flex items-center justify-between px-4 bg-green-800 shrink-0">
                <span v-show="isSidebarOpen" class="font-bold text-xl tracking-wider transition-opacity duration-200 whitespace-nowrap">
                    POS SYSTEM
                </span>
                <span v-show="!isSidebarOpen" class="font-bold text-xl mx-auto">
                    POS
                </span>

                <button @click="toggleSidebar" class="text-green-200 hover:text-white p-1 rounded focus:outline-none" :class="!isSidebarOpen ? 'hidden' : ''">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path></svg>
                </button>
            </div>
            
            <button v-if="!isSidebarOpen" @click="toggleSidebar" class="w-full py-2 text-green-300 hover:text-white flex justify-center border-b border-green-800 mb-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>
            </button>

            <nav class="flex-1 px-2 py-4 space-y-2 overflow-y-auto scrollbar-hide">
                
                <Link :href="route('dashboard')" 
                      class="flex items-center px-3 py-3 rounded-lg transition-colors whitespace-nowrap"
                      :class="{'bg-green-700 text-white': route().current('dashboard'), 'text-green-100 hover:bg-green-800 hover:text-white': !route().current('dashboard')}"
                      title="Dashboard"
                >
                    <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    <span v-show="isSidebarOpen" class="ml-3">Dashboard</span>
                </Link>

                <Link :href="route('sales.index')" 
                      class="flex items-center px-3 py-3 rounded-lg transition-colors whitespace-nowrap"
                      :class="{'bg-green-700 text-white': route().current('sales.index'), 'text-green-100 hover:bg-green-800 hover:text-white': !route().current('sales.index')}"
                      title="Reporte de Ventas"
                >
                    <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span v-show="isSidebarOpen" class="ml-3">Reporte de Ventas</span>
                </Link>

                <Link :href="route('products.index')" 
                      class="flex items-center px-3 py-3 rounded-lg transition-colors whitespace-nowrap mb-4"
                      :class="{'bg-green-600 text-white shadow-lg': route().current('products.index'), 'text-green-100 hover:bg-green-800 hover:text-white': !route().current('products.index')}"
                      title="Punto de Venta"
                >
                    <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    <span v-show="isSidebarOpen" class="ml-3 font-bold">Punto de Venta</span>
                </Link>

                <div class="border-t border-green-800 my-2"></div>

                <div>
                    <button @click="isSidebarOpen ? showingProductMenu = !showingProductMenu : toggleSidebar()" 
                            class="flex items-center justify-between w-full px-3 py-3 rounded-lg transition-colors text-green-100 hover:bg-green-800 hover:text-white whitespace-nowrap"
                            :class="{'bg-green-800': showingProductMenu && isSidebarOpen}"
                            title="Almacén">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                            <span v-show="isSidebarOpen" class="ml-3">Almacén</span>
                        </div>
                        <svg v-show="isSidebarOpen" class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': showingProductMenu}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div v-show="showingProductMenu && isSidebarOpen" class="pl-10 pr-2 space-y-1 mt-1 bg-green-900/50 py-2 rounded-lg">
                        <Link :href="route('products.inventory')" :class="{'text-white font-bold bg-green-700': route().current('products.inventory')}" class="block py-2 px-2 text-sm text-green-200 hover:text-white rounded hover:bg-green-700 transition">Inventario General</Link>
                        <Link :href="route('products.create')" :class="{'text-white font-bold bg-green-700': route().current('products.create')}" class="block py-2 px-2 text-sm text-green-200 hover:text-white rounded hover:bg-green-700 transition">Nuevo Producto</Link>
                    </div>
                </div>

                <div>
                    <button @click="isSidebarOpen ? showingClientMenu = !showingClientMenu : toggleSidebar()" 
                            class="flex items-center justify-between w-full px-3 py-3 rounded-lg transition-colors text-green-100 hover:bg-green-800 hover:text-white whitespace-nowrap"
                            :class="{'bg-green-800': showingClientMenu && isSidebarOpen}"
                            title="Clientes">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            <span v-show="isSidebarOpen" class="ml-3">Clientes</span>
                        </div>
                        <svg v-show="isSidebarOpen" class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': showingClientMenu}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div v-show="showingClientMenu && isSidebarOpen" class="pl-10 pr-2 space-y-1 mt-1 bg-green-900/50 py-2 rounded-lg">
                        <Link :href="route('clients.index')" :class="{'text-white font-bold bg-green-700': route().current('clients.index')}" class="block py-2 px-2 text-sm text-green-200 hover:text-white rounded hover:bg-green-700 transition">Listado</Link>
                        <Link :href="route('clients.create')" :class="{'text-white font-bold bg-green-700': route().current('clients.create')}" class="block py-2 px-2 text-sm text-green-200 hover:text-white rounded hover:bg-green-700 transition">Nuevo Cliente</Link>
                    </div>
                </div>

                <div class="mt-auto border-t border-green-800 pt-2 pb-2"> 
                    <Link :href="route('settings.index')" 
                        class="flex items-center px-3 py-3 rounded-lg transition-colors whitespace-nowrap"
                        :class="{'bg-green-700 text-white': route().current('settings.index'), 'text-green-100 hover:bg-green-800 hover:text-white': !route().current('settings.index')}"
                        title="Configuración">
                        
                        <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        
                        <span v-show="isSidebarOpen" class="ml-3">Configuración</span>
                    </Link>
                </div>

            </nav>

            <div class="p-4 border-t border-green-800 shrink-0">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center text-sm font-bold shadow shrink-0">
                        {{ $page.props.auth.user.name.charAt(0) }}
                    </div>
                    <div v-show="isSidebarOpen" class="ml-3 whitespace-nowrap overflow-hidden">
                        <p class="text-sm font-bold text-white">{{ $page.props.auth.user.name }}</p>
                        <Link :href="route('logout')" method="post" as="button" class="text-xs text-green-300 hover:text-white">Cerrar Sesión</Link>
                    </div>
                </div>
            </div>
        </aside>
        
        <div class="flex-1 flex flex-col min-h-screen transition-all duration-300 ease-in-out"
             :class="isSidebarOpen ? 'ml-64' : 'ml-20'">
            
            <main>
                <slot />
            </main>
            
        </div>
    </div>
</template>