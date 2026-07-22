<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

defineProps({ shipments: Array });

const confirmDelivery = (id) => {
    Swal.fire({
        title: '¿Confirmar entrega al cliente?',
        text: "Esta acción marcará el viaje como completado y actualizará el estado de los pedidos.",
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: 'Sí, confirmado'
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(route('shipments.confirm', id));
        }
    });
};

const cancelShipment = (id) => {
    Swal.fire({
        title: '¿Cancelar este embarque?',
        text: "El stock se regresará al inventario y el pedido volverá a su estado anterior.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Sí, cancelar embarque',
        cancelButtonText: 'No, mantenerlo'
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(route('shipments.cancel', id), {}, {
                onSuccess: () => Swal.fire({ icon: 'success', title: 'Cancelado', text: 'Stock restituido correctamente.', timer: 2000, showConfirmButton: false }),
                onError: (errors) => Swal.fire('Error', errors.error || 'No se pudo cancelar.', 'error')
            });
        }
    });
};

// Nuevo: decide si se puede cancelar
const canCancel = (ship) => {
    if (ship.status === 'cancelado') return false;
    if (ship.status === 'en_transito') return true;
    // Solo mostrador puede cancelarse después de 'entregado'
    return ship.status === 'entregado' && ship.pickup_type === 'recoleccion_cliente';
};
</script>

<template>
    <Head title="Historial de Embarques" />
    <AuthenticatedLayout>
        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Encabezado -->
                <div class="flex justify-between items-center mb-6 bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <div>
                        <h2 class="text-2xl font-black text-gray-800">Historial de Embarques</h2>
                        <p class="text-sm text-gray-500">Seguimiento de rutas y entregas</p>
                    </div>
                    <Link :href="route('shipments.create')" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-lg font-bold shadow transition-all active:scale-95 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Nuevo Viaje
                    </Link>
                </div>

                <!-- Tabla de Viajes -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 border-b">
                            <tr class="text-xs text-gray-500 uppercase">
                                <th class="px-6 py-4">Folio Viaje</th>
                                <th class="px-6 py-4">Chofer / Placas</th>
                                <th class="px-6 py-4">Destino</th>
                                <th class="px-6 py-4 text-center">Estado</th>
                                <th class="px-6 py-4 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="ship in shipments" :key="ship.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-black text-blue-600">#{{ ship.id.toString().padStart(4, '0') }}</td>
                                <td class="px-6 py-4 font-bold">{{ ship.driver_name }} <br><span class="text-xs text-gray-400 font-normal">{{ ship.license_plate }}</span></td>
                                <td class="px-6 py-4">{{ ship.destination }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span :class="{
                                            'bg-orange-100 text-orange-700': ship.status === 'en_transito',
                                            'bg-green-100 text-green-700': ship.status === 'entregado',
                                            'bg-red-100 text-red-700': ship.status === 'cancelado'
                                        }" class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                        {{ ship.status === 'en_transito' ? 'En Ruta' : ship.status === 'entregado' ? 'Entregado' : 'Cancelado' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center flex justify-center gap-3">
                                    <!-- Botón de Ver Detalles -->
                                    <Link :href="route('shipments.show', ship.id)" class="text-gray-500 hover:text-indigo-600 p-2 transition-colors" title="Ver Detalles">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </Link>

                                    <!-- Botón de Imprimir Remisión -->
                                    <a :href="route('shipments.print', ship.id)" target="_blank" class="text-gray-500 hover:text-blue-600 p-2 transition-colors" title="Imprimir Remisión">
                                        🖨️
                                    </a>

                                    <!-- Botón de Confirmar entrega -->
                                    <button v-if="canCancel(ship)" @click="cancelShipment(ship.id)" class="text-red-500 font-bold hover:underline ml-2">
                                        Cancelar
                                    </button>
                                    <button v-if="ship.status === 'en_transito'" @click="confirmDelivery(ship.id)" class="text-green-600 font-bold hover:underline ml-2">
                                        Confirmar Entrega
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>