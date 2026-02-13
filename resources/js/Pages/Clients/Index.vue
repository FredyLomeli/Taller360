<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue'; 
import Swal from 'sweetalert2';
import throttle from 'lodash/throttle'; // Importante para no saturar la búsqueda

// Recibimos "clients" como Objeto (Paginator), no Array
const props = defineProps({
    clients: Object, 
    filters: Object
});

const search = ref(props.filters.search || '');

// --- BÚSQUEDA EN SERVIDOR ---
// Usamos throttle para esperar 300ms antes de pedir datos al servidor
watch(search, throttle((value) => {
    router.get(route('clients.index'), { search: value }, {
        preserveState: true,
        replace: true,
        preserveScroll: true
    });
}, 300));

// --- UTILIDADES ---
// Convertir Nivel 1 -> A, 2 -> B, etc.
const getPriceLabel = (tier) => {
    const map = { 1: 'A', 2: 'B', 3: 'C', 4: 'D', 5: 'E' };
    return map[tier] || tier;
};

// --- ELIMINAR CLIENTE ---
const deleteClient = (client) => {
    Swal.fire({
        title: '¿Eliminar Cliente?',
        text: `Vas a eliminar a "${client.name}" y su historial.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('clients.destroy', client.id), {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Eliminado', 'Cliente eliminado.', 'success'),
                onError: (errors) => {
                    let msg = errors.error || 'No se pudo eliminar (tiene ventas activas).';
                    Swal.fire({ title: 'Error', text: msg, icon: 'error' });
                }
            });
        }
    });
};
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                    <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                        Cartera de Clientes
                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">{{ clients.total }} total</span>
                    </h2>
                    
                    <div class="flex flex-wrap gap-2 w-full md:w-auto items-center">
                        <div class="relative w-full md:w-64">
                            <input v-model="search" type="text" placeholder="Buscar por nombre, tel..." class="border border-gray-300 rounded-lg pl-4 pr-10 py-2 w-full focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                        </div>
                        
                        <Link :href="route('clients.create')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold shadow transition flex items-center">
                            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Nuevo
                        </Link>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-100 border-b">
                                <tr>
                                    <th class="px-6 py-3">Nombre / Razón Social</th>
                                    <th class="px-6 py-3">Nivel Precio</th>
                                    <th class="px-6 py-3">Contacto</th>
                                    <th class="px-6 py-3">Ubicación</th>
                                    <th class="px-6 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="client in clients.data" :key="client.id" class="bg-white hover:bg-gray-50 transition">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-900 text-base">{{ client.name }}</div>
                                        <div class="text-xs text-gray-400">{{ client.business_name || 'Particular' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold"
                                              :class="{
                                                  'bg-green-100 text-green-800': client.price_tier === 1,
                                                  'bg-blue-100 text-blue-800': client.price_tier === 2,
                                                  'bg-purple-100 text-purple-800': client.price_tier >= 3
                                              }">
                                            Lista {{ getPriceLabel(client.price_tier) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div v-if="client.phones" class="flex items-center gap-1 text-gray-700 font-medium">
                                            📞 {{ client.phones }}
                                        </div>
                                        <div v-if="client.email && !client.email.includes('@system.local')" class="text-xs text-blue-500">
                                            ✉️ {{ client.email }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-gray-900">{{ client.city }}</div>
                                        <div class="text-xs text-gray-400">{{ client.state }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end items-center gap-3">
                                            <Link :href="route('clients.edit', client.id)" class="text-blue-600 hover:text-blue-800 font-bold hover:underline text-xs uppercase">Editar</Link>
                                            <button @click="deleteClient(client)" class="text-red-500 hover:text-red-700 font-bold hover:underline text-xs uppercase">Borrar</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="clients.data.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-10 h-10 mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                            No se encontraron clientes.
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="clients.links.length > 3" class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-center">
                        <div class="flex flex-wrap gap-1 justify-center">
                            <template v-for="(link, key) in clients.links" :key="key">
                                <div v-if="link.url === null" class="mr-1 mb-1 px-3 py-2 text-xs text-gray-400 border rounded" v-html="link.label" />
                                <Link v-else 
                                      class="mr-1 mb-1 px-3 py-2 text-xs border rounded hover:bg-white focus:border-blue-500 focus:text-blue-500 transition-colors" 
                                      :class="{ 'bg-blue-600 text-white font-bold border-blue-600': link.active, 'bg-white text-gray-700': !link.active }" 
                                      :href="link.url" 
                                      v-html="link.label" />
                            </template>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>