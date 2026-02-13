<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    productionQueue: Object // Recibimos el objeto agrupado
});

const formatDate = (date) => new Date().toLocaleDateString('es-MX');
</script>

<template>
    <Head title="Plan de Producción" />

    <AuthenticatedLayout>
        <div class="py-12 printable">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="flex justify-between items-center mb-6 no-print">
                    <h2 class="text-2xl font-bold text-gray-800">Plan de Producción</h2>
                    <button onclick="window.print()" class="bg-gray-800 text-white px-4 py-2 rounded font-bold hover:bg-gray-700">
                        🖨️ Imprimir Lista
                    </button>
                </div>

                <div v-if="Object.keys(productionQueue).length === 0" class="bg-white p-12 text-center rounded-lg shadow">
                    <p class="text-gray-500 text-xl">🎉 No hay órdenes pendientes en producción.</p>
                    <p class="text-sm text-gray-400">Ve al Tablero de Ventas y mueve pedidos a "Producción".</p>
                    <Link :href="route('sales.index')" class="text-blue-600 underline mt-4 inline-block">Ir al Tablero</Link>
                </div>

                <div v-else class="grid grid-cols-1 gap-6">
                    
                    <div v-for="(group, key) in productionQueue" :key="key" class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-8 border-orange-500">
                        <div class="p-6">
                            
                            <div class="flex justify-between items-start border-b pb-4 mb-4">
                                <div>
                                    <h3 class="text-2xl font-black text-gray-800 uppercase">{{ group.name }}</h3>
                                    <span class="bg-gray-200 text-gray-700 px-2 py-1 rounded text-sm font-bold mt-1 inline-block">
                                        Material: {{ group.material }}
                                    </span>
                                </div>
                                <div class="text-center bg-orange-100 p-3 rounded-lg border border-orange-200">
                                    <div class="text-xs text-orange-800 uppercase font-bold">Total a Fabricar</div>
                                    <div class="text-4xl font-black text-orange-600">{{ group.total_quantity }}</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                <div>
                                    <h4 class="font-bold text-gray-500 uppercase text-xs mb-2">🎨 Desglose por Colores</h4>
                                    <ul class="space-y-2">
                                        <li v-for="(items, color) in group.breakdown" :key="color" 
                                            class="flex justify-between items-center bg-gray-50 p-2 rounded border">
                                            <span class="font-bold text-gray-700 flex items-center gap-2">
                                                <span class="w-3 h-3 rounded-full border border-gray-300 bg-white"></span>
                                                {{ color || 'Sin color especificado' }}
                                            </span>
                                            <span class="font-bold text-lg">{{ items.reduce((acc, i) => acc + i.quantity, 0) }} pzas</span>
                                        </li>
                                    </ul>
                                </div>

                                <div>
                                    <h4 class="font-bold text-gray-500 uppercase text-xs mb-2">📋 Pedidos Vinculados</h4>
                                    <div class="flex flex-wrap gap-2 mb-4">
                                        <Link v-for="orderId in group.orders" :key="orderId" 
                                              :href="route('sales.show', orderId)"
                                              class="bg-blue-50 text-blue-600 border border-blue-200 px-2 py-1 rounded text-xs font-bold hover:bg-blue-100 transition">
                                            #{{ orderId }}
                                        </Link>
                                    </div>

                                    <div v-for="item in group.details" :key="item.id">
                                        <div v-if="item.custom_notes" class="text-xs text-red-600 bg-red-50 p-2 rounded border border-red-100 mb-1">
                                            ⚠️ <strong>Pedido #{{ item.sale_id }}:</strong> {{ item.custom_notes }}
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
@media print {
    .no-print { display: none; }
    .printable { padding: 0; margin: 0; }
    body { background: white; }
    .shadow-sm { box-shadow: none; border: 1px solid #ccc; }
}
</style>