<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    shippableSales: Array
});

// El formulario del viaje (Carta Porte Interna)
const form = useForm({
    driver_name: '',
    license_plate: '',
    destination: '',
    items: [] 
});

const consumedByVariant = computed(() => {
    const map = {};
    for (const item of form.items) {
        map[item.product_variant_id] = (map[item.product_variant_id] || 0) + item.quantity;
    }
    return map;
});

const getAvailableToSend = (detail) => {
    const pending = detail.quantity - (detail.delivered_quantity || 0);
    const stock = detail.variant?.stock || 0;

    const currentLineQty = form.items.find(i => i.sale_detail_id === detail.id)?.quantity || 0;
    const takenByOtherLines = (consumedByVariant.value[detail.product_variant_id] || 0) - currentLineQty;

    const remainingSharedStock = Math.max(0, stock - takenByOtherLines);
    return Math.min(pending, remainingSharedStock);
};

const updateItem = (detail, event) => {
    let qty = parseInt(event.target.value) || 0;
    if (qty < 0) qty = 0;

    const maxAvailable = getAvailableToSend(detail);

    if (qty > maxAvailable) {
        qty = maxAvailable;
        event.target.value = qty;

        const pending = detail.quantity - (detail.delivered_quantity || 0);
        const reason = maxAvailable < pending
            ? 'ya apartaste piezas de este producto en otro pedido del viaje'
            : 'es lo máximo que este pedido necesita';
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Máximo alcanzado', text: `Solo quedan ${maxAvailable} disponibles (${reason}).`, showConfirmButton: false, timer: 2000 });
    }

    const existingIndex = form.items.findIndex(i => i.sale_detail_id === detail.id);
    if (qty > 0) {
        if (existingIndex >= 0) {
            form.items[existingIndex].quantity = qty;
        } else {
            form.items.push({ sale_detail_id: detail.id, quantity: qty, product_variant_id: detail.product_variant_id });
        }
    } else if (existingIndex >= 0) {
        form.items.splice(existingIndex, 1);
    }
};

const submit = () => {
    if (form.items.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Embarque vacío', text: 'Debes agregar al menos un artículo al viaje.' });
        return;
    }

    Swal.fire({
        title: '¿Autorizar Salida?',
        text: `Se generará el viaje para ${form.driver_name} y se descontará el inventario.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, despachar viaje',
        cancelButtonText: 'Revisar de nuevo'
    }).then((result) => {
        if (result.isConfirmed) {
            form.post(route('shipments.store'), {
                onSuccess: () => {
                    Swal.fire({ icon: 'success', title: '¡Buen Viaje!', text: 'El embarque está en ruta.', timer: 2000, showConfirmButton: false });
                },
                onError: (errors) => {
                    Swal.fire({
                        icon: 'error',
                        title: 'No se pudo generar el embarque',
                        text: errors.error || 'Revisa el stock disponible e intenta de nuevo.'
                    });
                }
            });
        }
    });
};

const totalItemsInTruck = computed(() => {
    return form.items.reduce((sum, item) => sum + item.quantity, 0);
});
</script>

<template>
    <Head title="Armar Embarque" />

    <AuthenticatedLayout>
        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Encabezado Estilo Tablero de Pedidos -->
                <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                    <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                        Armar Nuevo Embarque
                    </h2>
                    <div class="flex gap-3">
                        <!-- En el futuro aquí irá el botón de regresar al listado de viajes -->
                        <Link :href="route('sales.index')" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition-colors border border-gray-200">
                            Cancelar
                        </Link>
                    </div>
                </div>

                <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Columna Izquierda: Datos del Viaje -->
                    <div class="lg:col-span-1 space-y-6">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 sticky top-6">
                            <div class="p-4 bg-gray-50 border-b border-gray-200">
                                <h3 class="font-bold text-gray-700 uppercase text-xs tracking-wider">Datos del Vehículo y Chofer</h3>
                            </div>
                            
                            <div class="p-6 space-y-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Chofer / Repartidor <span class="text-red-500">*</span></label>
                                    <input v-model="form.driver_name" type="text" required class="block w-full border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 text-sm shadow-sm" placeholder="Ej: Roberto Sánchez">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Placas / Vehículo <span class="text-red-500">*</span></label>
                                    <input v-model="form.license_plate" type="text" required class="block w-full border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 text-sm shadow-sm uppercase" placeholder="Ej: JS-442-A">
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Destino o Ruta <span class="text-red-500">*</span></label>
                                    <textarea v-model="form.destination" required rows="2" class="block w-full border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 text-sm shadow-sm" placeholder="Ej: Ruta Norte (3 entregas)"></textarea>
                                </div>
                            </div>

                            <div class="p-6 bg-gray-50 border-t border-gray-100 flex flex-col gap-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-bold text-gray-500">Piezas a cargar:</span>
                                    <span class="text-3xl font-black text-gray-800">{{ totalItemsInTruck }}</span>
                                </div>
                                <button type="submit" :disabled="form.processing" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 rounded-lg shadow-sm transition-all active:scale-95 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Autorizar Salida
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Selección de Mercancía -->

                    

                    <div class="lg:col-span-2">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-200">
                            
                            <div class="p-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                                <h3 class="font-bold text-gray-700 uppercase text-xs tracking-wider">Mercancía Lista en Bodega</h3>
                                <span class="text-xs text-gray-500">Filtrado automático por stock físico</span>
                            </div>

                            <!-- Estado Vacío -->
                            <div v-if="shippableSales.length === 0" class="p-12 text-center text-gray-400">
                                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                <p class="font-medium text-lg">No hay mercancía para despachar</p>
                                <p class="text-sm mt-1">Todos los pedidos están entregados o siguen en el taller.</p>
                            </div>

                            <!-- Lista de Pedidos (Estilo Tablero) -->

                            <div class="overflow-y-auto max-h-[450px] divide-y divide-gray-100">
                                <div v-for="sale in shippableSales" :key="sale.id" class="p-6 hover:bg-gray-50/50 transition-colors">
                                    
                                    <div class="flex justify-between items-center mb-4">
                                        <div>
                                            <span class="font-bold text-blue-600 text-lg mr-2">
                                                Pedido #{{ sale.id.toString().padStart(5, '0') }}
                                            </span>
                                            <span class="font-bold text-gray-800">{{ sale.client?.name || 'Venta de Mostrador' }}</span>
                                        </div>
                                        <span class="px-3 py-1 bg-gray-100 text-gray-600 text-xs font-bold rounded-full border shadow-sm uppercase tracking-wider">
                                            {{ sale.stage }}
                                        </span>
                                    </div>

                                    <!-- Lista de Artículos (Estilo Detalle de Venta) -->
                                    <div class="space-y-3 pl-2 sm:pl-4">
                                        <template v-for="detail in sale.details" :key="detail.id">
                                            <div v-if="getAvailableToSend(detail) > 0" class="flex flex-col sm:flex-row sm:items-center justify-between bg-white border border-gray-200 p-3 rounded-lg shadow-sm gap-4">
                                                
                                                <div class="flex-1">
                                                    <p class="font-bold text-sm text-gray-800">{{ detail.product_name }}</p>
                                                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Color: {{ detail.chosen_color || 'N/A' }}</p>
                                                    <div class="flex items-center gap-4 mt-1 text-xs">
                                                        <span class="text-gray-500 font-medium">Pendientes: <span class="font-bold text-red-500">{{ detail.quantity - (detail.delivered_quantity || 0) }}</span></span>
                                                        <span class="text-gray-500 font-medium">Stock Físico: <span class="font-bold text-green-600">{{ detail.variant?.stock || 0 }}</span></span>
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2 bg-gray-50 p-1.5 rounded-lg border border-gray-200">
                                                    <label class="text-xs font-bold text-gray-500 ml-1">Cargar:</label>
                                                    <input 
                                                        type="number" 
                                                        min="0" 
                                                        :max="getAvailableToSend(detail)"
                                                        @input="updateItem(detail, $event)"
                                                        class="w-16 h-8 text-center font-bold text-sm border-gray-300 rounded focus:ring-green-500 shadow-inner"
                                                        placeholder="0"
                                                    >
                                                </div>

                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </form>

            </div>
        </div>
    </AuthenticatedLayout>
</template>