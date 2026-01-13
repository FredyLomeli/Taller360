<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    clients: Array
})
const search = ref('');

// Filtro simple (Igual que en productos)
const filteredClients = computed(() => {
    if (!search.value) return props.clients;
    const term = search.value.toLowerCase();
    return props.clients.filter(c => 
        c.name.toLowerCase().includes(term) ||
        (c.email && c.email.toLowerCase().includes(term)) ||
        (c.business_name && c.business_name.toLowerCase().includes(term))
    );
});

// --- FUNCIÓN DE ELIMINAR CON SWEETALERT ---
const deleteClient = (client) => {
    Swal.fire({
        title: '¿Eliminar Cliente?',
        text: `Vas a eliminar a "${client.name}". Esta acción es irreversible.`,
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
                onSuccess: () => {
                    Swal.fire(
                        'Eliminado',
                        'El cliente ha sido eliminado correctamente.',
                        'success'
                    );
                },
                onError: (errors) => {
                    // CAPTURAMOS EL MENSAJE DEL GUARDIA
                    let msg = 'No se pudo eliminar.';
                    if (errors.error) msg = errors.error;

                    Swal.fire({
                        title: 'No se puede eliminar',
                        text: msg,
                        icon: 'error',
                        confirmButtonColor: '#d33'
                    });
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
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Cartera de Clientes</h2>
                    <div class="flex gap-2">
                        <input v-model="search" type="text" placeholder="Buscar cliente..." class="border rounded-lg px-4 py-2">
                        <Link :href="route('clients.create')" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-bold">
                            + Nuevo Cliente
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
                            <tr v-for="client in filteredClients" :key="client.id" class="border-b hover:bg-gray-50">
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
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>