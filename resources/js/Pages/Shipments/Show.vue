<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({ shipment: Object });

const canCancel = computed(() => {
    if (props.shipment.status === 'cancelado') return false;
    if (props.shipment.status === 'en_transito') return true;
    return props.shipment.status === 'entregado' && props.shipment.pickup_type === 'recoleccion_cliente';
});

const cancelShipment = () => {
    Swal.fire({
        title: '¿Cancelar este embarque?',
        text: "El stock se regresará al inventario.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Sí, cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(route('shipments.cancel', props.shipment.id), {}, {
                onSuccess: () => Swal.fire({ icon: 'success', title: 'Cancelado', timer: 2000, showConfirmButton: false })
            });
        }
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Encabezado con datos del viaje -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mb-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-2xl font-black text-gray-800">Detalle de Embarque #{{ shipment.id.toString().padStart(4, '0') }}</h2>
                            <p class="text-gray-600 font-medium mt-1">Chofer: {{ shipment.driver_name }} | Placas: {{ shipment.license_plate }}</p>
                            <p class="text-sm text-gray-500 mt-2 font-bold">Fecha de registro: {{ new Date(shipment.created_at).toLocaleString('es-MX') }}</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <span :class="{
                                    'bg-orange-100 text-orange-700': shipment.status === 'en_transito',
                                    'bg-green-100 text-green-700': shipment.status === 'entregado',
                                    'bg-red-100 text-red-700': shipment.status === 'cancelado'
                                }" class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                {{ shipment.status }}
                            </span>
                            <button v-if="canCancel" @click="cancelShipment" class="text-sm font-bold text-red-500 hover:text-red-700">Cancelar Embarque</button>
                            <Link :href="route('shipments.index')" class="text-sm font-bold text-gray-500 hover:text-gray-800 transition">Regresar</Link>
                        </div>
                    </div>
                </div>

                <!-- Lista de Entregas -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b bg-gray-50 font-bold text-gray-700 uppercase text-xs">
                        Artículos a bordo
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="del in shipment.deliveries" :key="del.id" class="px-6 py-4 flex justify-between items-center">
                            <div>
                                <p class="font-bold text-gray-800">{{ del.sale_detail.product_name }}</p>
                                <!-- Color del mueble -->
                                <p class="text-xs text-gray-500 font-bold uppercase">Color: {{ del.sale_detail.chosen_color || 'N/A' }}</p>
                                <p class="text-xs text-gray-400">Pedido #{{ del.sale_detail.sale_id.toString().padStart(6, '0') }} | Cliente: {{ del.sale_detail.sale.client?.name || 'Mostrador' }}</p>
                            </div>
                            <span class="font-black text-green-600 bg-green-50 px-3 py-1 rounded-full text-sm">
                                {{ del.quantity_delivered }} unidades
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>