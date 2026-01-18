<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue'; 
import Swal from 'sweetalert2';

const props = defineProps({
    clients: Array
})
const search = ref('');

// --- PAGINACIÓN LOCAL ---
const itemsPerPage = ref(10);
const currentPage = ref(1);

// Filtro
const filteredClients = computed(() => {
    if (!search.value) return props.clients;
    const term = search.value.toLowerCase();
    return props.clients.filter(c => 
        c.name.toLowerCase().includes(term) ||
        (c.email && c.email.toLowerCase().includes(term)) ||
        (c.business_name && c.business_name.toLowerCase().includes(term))
    );
});

// Resetear página al buscar o cambiar limite
watch([search, itemsPerPage], () => {
    currentPage.value = 1;
});

// Datos Paginados
const paginatedClients = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredClients.value.slice(start, end);
});

const totalPages = computed(() => Math.ceil(filteredClients.value.length / itemsPerPage.value));

const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++; };
const prevPage = () => { if (currentPage.value > 1) currentPage.value--; };

// --- DELETE FUNCTION ---
const deleteClient = (client) => {
    Swal.fire({
        title: '¿Eliminar Cliente?',
        text: `Vas a eliminar a "${client.name}".`,
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
                    let msg = errors.error || 'No se pudo eliminar.';
                    Swal.fire({ title: 'Error', text: msg, icon: 'error', confirmButtonColor: '#d33' });
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
                    <h2 class="text-2xl font-bold text-gray-800">Cartera de Clientes</h2>
                    
                    <div class="flex flex-wrap gap-2 w-full md:w-auto items-center">
                        <select v-model="itemsPerPage" class="border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 cursor-pointer">
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>

                        <input v-model="search" type="text" placeholder="Buscar cliente..." class="border rounded-lg px-4 py-2 w-full md:w-64">
                        
                        <Link :href="route('clients.create')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold shadow transition">
                            + Nuevo
                        </Link>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border">
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
                        <tbody>
                            <tr v-for="client in paginatedClients" :key="client.id" class="border-b hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-gray-900">{{ client.name }}</div>
                                    <div class="text-xs">{{ client.business_name }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="bg-blue-100 text-blue-800 text-xs font-bold px-2 py-1 rounded">
                                        Nivel {{ client.price_tier }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="client.phones">📞 {{ client.phones }}</div>
                                    <div v-if="client.email">✉️ {{ client.email }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    {{ client.city }}, {{ client.state }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link :href="route('clients.edit', client.id)" class="text-blue-600 hover:underline mr-3">Editar</Link>
                                    <button @click="deleteClient(client)" class="text-red-600 hover:underline">Borrar</button>
                                </td>
                            </tr>
                            <tr v-if="filteredClients.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-gray-400">No hay clientes registrados.</td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="filteredClients.length > 0" class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                        <span class="text-xs text-gray-500">
                            {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, filteredClients.length) }} de {{ filteredClients.length }}
                        </span>
                        
                        <div class="flex items-center gap-2">
                            <button @click="prevPage" :disabled="currentPage === 1" class="px-3 py-1 text-xs bg-white border rounded hover:bg-gray-100 disabled:opacity-50">Ant.</button>
                            <span class="text-xs font-bold">{{ currentPage }} / {{ totalPages }}</span>
                            <button @click="nextPage" :disabled="currentPage === totalPages" class="px-3 py-1 text-xs bg-white border rounded hover:bg-gray-100 disabled:opacity-50">Sig.</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>