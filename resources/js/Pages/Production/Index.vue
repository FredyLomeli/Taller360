<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    productionQueue: Object, 
    weekRange: Object, 
});

const completionData = ref({});
const currentFilter = ref('todos');

const filteredQueue = computed(() => {
    if (currentFilter.value === 'todos') return props.productionQueue;
    
    const filtered = {};
    for (const [key, group] of Object.entries(props.productionQueue)) {
        if (currentFilter.value === 'embarque' && group.pending_to_fabricate === 0) {
            filtered[key] = group;
        } else if (currentFilter.value === 'fabricar' && group.pending_to_fabricate > 0) {
            filtered[key] = group;
        }
    }
    return filtered;
});

const changeWeek = (days) => {
    if (!props.weekRange) return; 
    const currentStart = new Date(props.weekRange.start);
    currentStart.setDate(currentStart.getDate() + days);
    
    router.get(route('production.plan'), { 
        start_date: currentStart.toISOString().split('T')[0] 
    }, { preserveState: true });
};

const formatToSpanish = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString + 'T12:00:00');
    return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatPromisedDate = (dateString) => {
    if (!dateString) return 'Sin fecha';
    
    // Usamos una expresión regular para extraer únicamente YYYY-MM-DD ignorando la "T" o las horas
    const match = dateString.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!match) return 'Sin fecha';
    
    // Extraemos las partes (restamos 1 al mes porque Javascript cuenta los meses del 0 al 11)
    const date = new Date(match[1], match[2] - 1, match[3]);
    
    return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
};

const submitCompletion = (detailId, maxQuantity) => {
    const qty = parseInt(completionData.value[detailId]);

    if (!qty || qty < 1) {
        Swal.fire({ icon: 'warning', title: 'Atención', text: 'Por favor ingresa una cantidad válida mayor a 0.' });
        return;
    }

    const sendRequest = () => {
        router.post(route('production.complete'), {
            sale_detail_id: detailId,
            quantity: qty
        }, {
            preserveScroll: true,
            onSuccess: () => {
                completionData.value[detailId] = ''; 
                Swal.fire({ 
                    icon: 'success', 
                    title: '¡Registrado!', 
                    text: 'Las piezas se enviaron al inventario.', 
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    };

    if (qty > maxQuantity) {
        Swal.fire({
            title: '¿Fabricar excedente por lote?',
            text: `El pedido solo requiere ${maxQuantity} pieza(s), pero vas a registrar ${qty}. Las ${qty - maxQuantity} pieza(s) sobrantes se sumarán a tu inventario general.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, registrar lote',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                sendRequest();
            }
        });
    } else {
        sendRequest();
    }
};
</script>

<template>
    <Head title="Plan de Producción" />

    <AuthenticatedLayout>
        <div class="py-12" id="printable-area">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="flex flex-col md:flex-row justify-between items-center bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-4 no-print gap-4">
                    <h2 class="text-2xl font-bold text-gray-800">Plan de Producción</h2>
                    
                    <div v-if="weekRange" class="flex items-center space-x-2 md:space-x-4">
                        <button @click="changeWeek(-7)" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-sm font-bold text-gray-600 transition">&laquo; Ant.</button>
                        <div class="font-bold text-blue-700 bg-blue-50 px-4 py-1.5 rounded uppercase text-sm border border-blue-100">
                            {{ formatToSpanish(weekRange.start) }} - {{ formatToSpanish(weekRange.end) }}
                        </div>
                        <button @click="changeWeek(7)" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-sm font-bold text-gray-600 transition">Sig. &raquo;</button>
                    </div>

                    <a :href="route('production.print')" target="_blank" class="bg-gray-800 text-white px-4 py-1.5 rounded text-sm font-bold hover:bg-gray-700 transition shadow-sm inline-block">
                        🖨️ Generar REPORTE
                    </a>
                </div>

                <div class="flex gap-2 mb-6 no-print overflow-x-auto pb-2">
                    <button @click="currentFilter = 'todos'" :class="currentFilter === 'todos' ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'" class="px-4 py-2 rounded text-sm font-bold border transition shadow-sm whitespace-nowrap">
                        📋 Todos los Pedidos
                    </button>
                    <button @click="currentFilter = 'embarque'" :class="currentFilter === 'embarque' ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'" class="px-4 py-2 rounded text-sm font-bold border transition shadow-sm whitespace-nowrap">
                        ✅ Listos para Embarque
                    </button>
                    <button @click="currentFilter = 'fabricar'" :class="currentFilter === 'fabricar' ? 'bg-red-600 text-white border-red-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'" class="px-4 py-2 rounded text-sm font-bold border transition shadow-sm whitespace-nowrap">
                        🛠️ Por Fabricar
                    </button>
                </div>

                <div v-if="Object.keys(filteredQueue).length === 0" class="bg-white p-12 text-center rounded-lg shadow-sm border border-gray-200">
                    <p class="text-gray-500 text-xl font-bold">🎉 No hay resultados para este filtro.</p>
                </div>

                <div v-else class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-[10px] font-bold border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4">Modelo a Fabricar</th>
                                <th class="px-6 py-4 text-center">Estatus de Inventario</th>
                                <th class="px-6 py-4">Pedidos Vinculados</th>
                                <th class="px-6 py-4 text-right no-print">Registrar Fabricación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(group, key) in filteredQueue" :key="key" class="hover:bg-gray-50 transition">
                                
                                <td class="px-6 py-4 align-top">
                                    <div class="font-bold text-gray-800 text-sm">{{ group.name }}</div>
                                    <div class="text-[10px] text-gray-400 uppercase font-bold mt-0.5">Mat: {{ group.material }}</div>
                                </td>

                                <td class="px-6 py-4 align-top text-center">
                                    <div class="flex justify-center gap-4 text-[11px] mb-2 font-semibold">
                                        <div class="text-gray-500 text-center">
                                            Necesario<br><span class="text-gray-800 text-sm">{{ group.total_needed }}</span>
                                        </div>
                                        <div class="text-gray-500 text-center border-l px-4">
                                            En Stock<br><span class="text-green-600 text-sm">{{ group.in_stock }}</span>
                                        </div>
                                        <div class="text-gray-500 text-center border-l pl-4">
                                            Faltan<br><span class="text-red-500 text-sm">{{ group.pending_to_fabricate }}</span>
                                        </div>
                                    </div>
                                    
                                    <span v-if="group.pending_to_fabricate === 0 && group.in_stock === 0" class="bg-blue-100 text-blue-700 border border-blue-200 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider shadow-sm">
                                        📦 Fabricado y Embarcado
                                    </span>
                                    <span v-else-if="group.pending_to_fabricate === 0" class="bg-green-100 text-green-700 border border-green-200 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider shadow-sm">
                                        ✅ Listo para Embarque
                                    </span>
                                    <span v-else-if="group.in_stock > 0" class="bg-orange-100 text-orange-700 border border-orange-200 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider shadow-sm">
                                        ⚠️ Producción Parcial
                                    </span>
                                    <span v-else class="bg-red-50 text-red-600 border border-red-200 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider shadow-sm">
                                        🛠️ Fabricar Todo
                                    </span>
                                </td>

                                <!-- Pedidos actualizados con Fecha Compromiso -->
                                <td class="px-6 py-4 align-top w-[200px] whitespace-normal">
                                    <div class="flex flex-wrap gap-1">
                                        <Link v-for="order in group.orders" :key="order.id" :href="route('sales.show', order.id)" 
                                              :class="[
                                                  'px-2 py-1 rounded text-[10px] font-bold border transition shadow-sm inline-flex items-center gap-1',
                                                  order.is_overdue ? 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100' : 'bg-white text-gray-600 border-gray-200 hover:border-blue-400 hover:text-blue-600'
                                              ]">
                                            <span>#{{ order.id }}</span>
                                            <span class="text-[9px] font-normal border-l pl-1 border-current opacity-80 flex items-center gap-0.5">
                                                📅 {{ formatPromisedDate(order.promised_date) }}
                                            </span>
                                        </Link>
                                    </div>
                                </td>

                                <!-- Acciones actualizadas con Fecha Compromiso -->
                                <td class="px-6 py-4 align-top min-w-[250px] no-print">
                                    <template v-for="item in group.details" :key="'action-'+item.id">
                                        <div v-if="group.pending_to_fabricate > 0 && (item.quantity - (item.completed_quantity || 0)) > 0" class="flex items-center justify-between gap-3 mb-2 bg-gray-50/50 p-2 rounded border border-gray-200 shadow-sm last:mb-0">
                                            
                                            <div class="flex flex-col">
                                                <span class="text-[11px] text-gray-700 font-bold">
                                                    Ped. #{{ item.sale_id }} <span class="text-gray-400 ml-1 font-normal">| Restan: {{ item.quantity - (item.completed_quantity || 0) }}</span>
                                                </span>
                                                <span class="text-[9px] text-gray-500 mt-0.5 flex items-center gap-1">
                                                    📅 Promesa: {{ formatPromisedDate(item.sale?.promised_date) }}
                                                </span>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <input 
                                                    type="number" 
                                                    v-model="completionData[item.id]" 
                                                    min="1" 
                                                    class="w-20 h-8 p-1 text-center border-gray-300 rounded text-sm focus:ring-blue-500 focus:border-blue-500 font-semibold shadow-inner" 
                                                    placeholder="Cant."
                                                >
                                                <button 
                                                    @click="submitCompletion(item.id, item.quantity - (item.completed_quantity || 0))" 
                                                    class="bg-blue-600 text-white w-8 h-8 flex items-center justify-center rounded text-sm font-bold hover:bg-blue-700 transition shadow-sm"
                                                    title="Registrar"
                                                >
                                                    ✓
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                    
                                    <div v-if="group.pending_to_fabricate === 0" class="text-xs text-green-600 font-bold text-center py-2 bg-green-50 rounded border border-green-100">
                                        ✅ Producción Completada
                                    </div>
                                </td>

                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
@media print {
    .no-print { display: none !important; }
    body * { visibility: hidden; }
    #printable-area, #printable-area * { visibility: visible; }
    #printable-area { position: absolute; left: 0; top: 0; width: 100%; padding: 0 !important; }
    .shadow-sm { box-shadow: none !important; border: 1px solid #000 !important; }
    .bg-gray-50 { background-color: #fff !important; }
}
</style>