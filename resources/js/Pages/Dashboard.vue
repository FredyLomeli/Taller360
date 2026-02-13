<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    isAdmin: Boolean,
    kpis: Object,
    sellersStats: Array,
    lowStockProducts: Array,
    recentSales: Array,
    filters: Object
});

const form = ref({
    start_date: props.filters.start_date,
    end_date: props.filters.end_date
});

const applyFilter = () => {
    router.get(route('dashboard'), { 
        start_date: form.value.start_date,
        end_date: form.value.end_date 
    }, {
        preserveState: true, preserveScroll: true, replace: true
    });
};

const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
};

const stageColors = {
    'pedido': 'bg-gray-100 text-gray-600 border-gray-200',
    'confirmado': 'bg-yellow-100 text-yellow-700 border-yellow-200',
    'produccion': 'bg-blue-100 text-blue-700 border-blue-200',
    'enviado': 'bg-purple-100 text-purple-700 border-purple-200',
    'entregado': 'bg-green-100 text-green-700 border-green-200',
    'cancelado': 'bg-red-100 text-red-700 border-red-200'
};

const stageLabels = {
    'pedido': 'Borrador',
    'confirmado': 'Confirmado',
    'produccion': 'Producción',
    'enviado': 'Listo/Enviado',
    'entregado': 'Entregado',
    'cancelado': 'Cancelado'
};
</script>

<template>
    <Head title="Panel Principal" />

    <AuthenticatedLayout>
        <div class="h-[calc(100vh-65px)] overflow-hidden bg-gray-50 flex flex-col p-6">
            
            <div class="max-w-7xl mx-auto w-full flex flex-col h-full space-y-7">
                
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 flex flex-col md:flex-row justify-between items-center gap-4 shrink-0">
                    <h2 class="font-bold text-2xl text-gray-800 flex items-center gap-3">
                        <div class="p-2.5 bg-blue-50 rounded-xl">
                            <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        </div>
                        {{ isAdmin ? 'Control General' : 'Mis Resultados' }}
                    </h2>

                    <div class="flex items-center gap-5 bg-gray-50 px-5 py-2.5 rounded-2xl border border-gray-200 shadow-inner">
                        <div class="flex flex-col">
                            <span class="text-[9px] text-gray-400 font-bold uppercase tracking-widest">Desde</span>
                            <input type="date" v-model="form.start_date" @change="applyFilter" 
                                class="border-none bg-transparent p-0 text-sm font-bold text-gray-700 focus:ring-0 cursor-pointer h-5 w-32">
                        </div>
                        <span class="text-gray-300 font-light text-xl">|</span>
                        <div class="flex flex-col">
                            <span class="text-[9px] text-gray-400 font-bold uppercase tracking-widest">Hasta</span>
                            <input type="date" v-model="form.end_date" @change="applyFilter" 
                                class="border-none bg-transparent p-0 text-sm font-bold text-gray-700 focus:ring-0 cursor-pointer h-5 w-32">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 shrink-0">
                    <div class="bg-white shadow-sm rounded-2xl p-6 border-l-4 border-green-500 relative overflow-hidden">
                        <div class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-2">Ingreso Cobrado</div>
                        <div class="text-3xl font-black text-gray-800">{{ formatMoney(kpis.income) }}</div>
                        <div class="text-[11px] text-gray-400 mt-2">En periodo seleccionado</div>
                    </div>

                    <div v-if="isAdmin" class="bg-white shadow-sm rounded-2xl p-6 border-l-4 border-blue-500 flex flex-col justify-between">
                        <div>
                            <div class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-2">En Taller (Activos)</div>
                            <div class="text-3xl font-black text-gray-800">{{ kpis.in_production }} <span class="text-sm font-bold text-gray-300">pedidos</span></div>
                        </div>
                        <Link :href="route('sales.index', { stage: 'produccion' })" class="text-xs text-blue-600 font-bold underline mt-4 hover:text-blue-800">Ver tablero →</Link>
                    </div>

                    <div v-if="isAdmin" class="bg-white shadow-sm rounded-2xl p-6 border-l-4 border-purple-500 flex flex-col justify-between">
                        <div>
                            <div class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-2">Listos para Entrega</div>
                            <div class="text-3xl font-black text-gray-800">{{ kpis.ready_to_ship }} <span class="text-sm font-bold text-gray-300">pedidos</span></div>
                        </div>
                        <Link :href="route('sales.index', { stage: 'enviado' })" class="text-xs text-purple-600 font-bold underline mt-4 hover:text-purple-800">Gestionar envíos →</Link>
                    </div>

                    <div v-if="isAdmin" class="bg-white shadow-sm rounded-2xl p-6 border-l-4 border-orange-500">
                        <div class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-2">Crédito Pendiente</div>
                        <div class="text-3xl font-black text-gray-800">{{ formatMoney(kpis.credit_receivable) }}</div>
                        <div class="text-[11px] text-gray-400 mt-2">Cuentas por cobrar</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 flex-1 min-h-0 pb-4">
                    
                    <div class="lg:col-span-2 bg-white shadow-sm rounded-3xl border border-gray-100 flex flex-col min-h-0 overflow-hidden">
                        <div class="px-7 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between shrink-0">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                <h3 class="font-bold text-gray-700 uppercase text-xs tracking-widest">Rendimiento de Equipo</h3>
                            </div>
                            <span class="text-[10px] font-medium text-gray-400 bg-white px-2 py-1 rounded-lg border border-gray-100">
                                Total Vendedores: {{ sellersStats.length }}
                            </span>
                        </div>
                        
                        <div class="overflow-y-auto flex-1 custom-scroll">
                            <table class="w-full text-sm text-left table-fixed">
                                <thead class="text-[10px] text-gray-400 uppercase tracking-wider sticky top-0 bg-white border-b border-gray-100 z-10">
                                    <tr>
                                        <th class="w-1/2 px-6 py-3 font-semibold">Vendedor</th>
                                        <th class="w-1/4 px-4 py-3 text-center font-semibold">Tickets</th>
                                        <th class="w-1/4 px-6 py-3 text-right font-semibold">Total Cobrado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="seller in sellersStats" :key="seller.id" class="hover:bg-blue-50/20 transition-all group">
                                        <td class="px-6 py-3 font-bold text-gray-700 flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 text-blue-600 flex items-center justify-center font-black text-[10px] shadow-sm border border-blue-200 group-hover:scale-110 transition-transform">
                                                {{ seller.name.substring(0,2).toUpperCase() }}
                                            </div>
                                            <span class="truncate">{{ seller.name }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600">
                                                {{ seller.tickets_count }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <span class="font-black text-green-600 tabular-nums">
                                                {{ formatMoney(seller.total_sold) }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="sellersStats.length === 0">
                                        <td colspan="3" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center gap-2">
                                                <svg class="w-10 h-10 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                                <span class="text-gray-400 font-medium">No hay datos para este periodo</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white shadow-sm rounded-3xl border border-red-100 flex flex-col min-h-0 overflow-hidden">
                        <div class="px-6 py-5 bg-red-50/50 border-b border-red-100 flex justify-between items-center shrink-0">
                            <h3 class="font-bold text-red-800 text-[10px] uppercase tracking-[0.1em] flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                Stock Crítico
                            </h3>
                            <Link :href="route('products.index')" class="text-[10px] text-red-600 font-black hover:underline uppercase">Gestionar</Link>
                        </div>
                        <div class="overflow-y-auto flex-1 custom-scroll bg-white p-2">
                            <ul class="divide-y divide-gray-50">
                                <li v-for="variant in lowStockProducts" :key="variant.id" class="p-5 hover:bg-red-50/50 transition-colors flex justify-between items-center group rounded-2xl">
                                    <div class="min-w-0">
                                        <div class="text-sm font-black text-gray-800 truncate mb-1">{{ variant.product.name }}</div>
                                        <div class="text-[10px] text-gray-400 font-bold uppercase truncate">
                                            {{ variant.material }} <span v-if="variant.sku" class="text-gray-300 px-1">|</span> {{ variant.sku }}
                                        </div>
                                    </div>
                                    <div class="text-center bg-red-100 px-4 py-1.5 rounded-2xl ml-4">
                                        <span class="block text-base font-black text-red-600 leading-none">{{ variant.stock }}</span>
                                        <span class="text-[8px] text-red-400 uppercase font-black tracking-tighter">Pzas</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Scrollbar más fino para no ensuciar el diseño */
.custom-scroll::-webkit-scrollbar {
    width: 5px;
}
.custom-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scroll::-webkit-scrollbar-thumb {
    background: #f1f1f1;
    border-radius: 20px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
    background: #e2e2e2;
}
</style>