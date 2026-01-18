<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

// Recibimos la NUEVA estructura del controlador
const props = defineProps({
    isAdmin: Boolean,
    kpis: Object,          // { income, credit_receivable, tickets }
    sellersStats: Array,   // Solo admin
    lowStockProducts: Array, // Solo admin
    recentSales: Array,    // Solo vendedor
    filters: Object        // { start_date, end_date }
});

// Variables reactivas para las fechas
const form = ref({
    start_date: props.filters.start_date,
    end_date: props.filters.end_date
});

// Función para aplicar filtro (recarga la página)
const applyFilter = () => {
    router.get(route('dashboard'), { 
        start_date: form.value.start_date,
        end_date: form.value.end_date 
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
};

// Formato de moneda
const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount || 0);
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ isAdmin ? 'Panel de Control' : 'Mis Resultados' }}
                </h2>
                
                <div class="flex items-center gap-2 bg-white p-2 rounded shadow-sm">
                    <span class="text-xs text-gray-500 font-bold uppercase">Periodo:</span>
                    <input type="date" v-model="form.start_date" @change="applyFilter" 
                           class="border-none text-sm p-1 focus:ring-0 text-gray-700 font-medium">
                    <span class="text-gray-400">-</span>
                    <input type="date" v-model="form.end_date" @change="applyFilter" 
                           class="border-none text-sm p-1 focus:ring-0 text-gray-700 font-medium">
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-green-500">
                        <div class="text-gray-500 text-sm font-medium uppercase">Ingreso Real (Caja)</div>
                        <div class="text-3xl font-bold text-gray-800 mt-2">{{ formatMoney(kpis.income) }}</div>
                        <div class="text-xs text-gray-400 mt-1">Cobrado en efectivo/tarjeta</div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-blue-500">
                        <div class="text-gray-500 text-sm font-medium uppercase">Ventas Cerradas</div>
                        <div class="text-3xl font-bold text-gray-800 mt-2">{{ kpis.tickets }}</div>
                        <div class="text-xs text-gray-400 mt-1">Notas generadas</div>
                    </div>

                    <div v-if="isAdmin" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-orange-500">
                        <div class="text-gray-500 text-sm font-medium uppercase">Crédito Otorgado</div>
                        <div class="text-3xl font-bold text-gray-800 mt-2">{{ formatMoney(kpis.credit_receivable) }}</div>
                        <div class="text-xs text-gray-400 mt-1">Pendiente de cobro del periodo</div>
                    </div>
                </div>

                <div v-if="isAdmin" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-bold text-lg text-gray-700 mb-4 border-b pb-2">Rendimiento por Vendedor</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2">Vendedor</th>
                                        <th class="px-3 py-2 text-right">Tickets</th>
                                        <th class="px-3 py-2 text-right">Cobrado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="seller in sellersStats" :key="seller.id" class="border-b last:border-0 hover:bg-gray-50">
                                        <td class="px-3 py-3 font-medium text-gray-900">{{ seller.name }}</td>
                                        <td class="px-3 py-3 text-right">{{ seller.tickets_count }}</td>
                                        <td class="px-3 py-3 text-right font-bold text-green-600">{{ formatMoney(seller.total_sold) }}</td>
                                    </tr>
                                    <tr v-if="sellersStats.length === 0">
                                        <td colspan="3" class="text-center py-4 text-gray-500">No hay datos en este periodo.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white shadow-sm sm:rounded-lg p-6 border border-red-100">
                        <div class="flex justify-between items-center mb-4 border-b pb-2">
                            <h3 class="font-bold text-lg text-red-700">⚠️ Stock Crítico</h3>
                            <a :href="route('products.inventory')" class="text-xs text-red-500 hover:underline">Ver todo</a>
                        </div>
                        <ul class="space-y-3">
                            <li v-for="variant in lowStockProducts" :key="variant.id" class="flex justify-between items-center">
                                <div>
                                    <div class="font-medium text-gray-800">{{ variant.product.name }}</div>
                                    <div class="text-xs text-gray-500">{{ variant.material }} - {{ variant.color }}</div>
                                </div>
                                <span class="bg-red-100 text-red-800 text-xs font-bold px-2 py-1 rounded-full">
                                    {{ variant.stock }} pzas
                                </span>
                            </li>
                            <li v-if="lowStockProducts.length === 0" class="text-green-600 text-sm">
                                ¡Todo excelente! No hay productos con stock bajo.
                            </li>
                        </ul>
                    </div>
                </div>

                <div v-else class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-bold text-lg text-gray-700 mb-4">Mis Últimas 5 Ventas</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2">Folio</th>
                                    <th class="px-4 py-2">Cliente</th>
                                    <th class="px-4 py-2">Fecha</th>
                                    <th class="px-4 py-2 text-right">Total</th>
                                    <th class="px-4 py-2 text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="sale in recentSales" :key="sale.id" class="border-b last:border-0">
                                    <td class="px-4 py-3 font-mono text-gray-600">#{{ sale.id }}</td>
                                    <td class="px-4 py-3">{{ sale.client?.name || 'Público General' }}</td>
                                    <td class="px-4 py-3">{{ new Date(sale.created_at).toLocaleDateString() }}</td>
                                    <td class="px-4 py-3 text-right font-bold">{{ formatMoney(sale.total) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span :class="{
                                            'bg-green-100 text-green-800': sale.status === 'pagado',
                                            'bg-yellow-100 text-yellow-800': sale.status === 'pendiente',
                                            'bg-red-100 text-red-800': sale.status === 'cancelado'
                                        }" class="px-2 py-1 rounded-full text-xs font-bold uppercase">
                                            {{ sale.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>