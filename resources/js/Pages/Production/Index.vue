<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    productionQueue: Object, 
    weekRange: Object, 
    pausedItems: Array,
    allVariants: Array,
});

const completionData = ref({});
const currentFilter = ref('todos');
const isPausedExpanded = ref(false);

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

const groupedPausedItems = computed(() => {
    if (!props.pausedItems) return [];
    
    const groups = {};
    props.pausedItems.forEach(item => {
        const saleId = item.sale_id;
        if (!groups[saleId]) {
            groups[saleId] = {
                sale_id: saleId,
                client_name: item.sale?.client?.name || 'Venta de Mostrador',
                items: []
            };
        }
        groups[saleId].items.push(item);
    });
    
    return Object.values(groups).sort((a, b) => a.sale_id - b.sale_id);
});

const availableVariants = computed(() => {
    if (!props.allVariants) return [];
    return props.allVariants.map(variant => {
        return {
            id: variant.id,
            name: variant.product.name + ' - ' + variant.material + (variant.measurements ? ' (' + variant.measurements + ')' : '')
        };
    });
});

const searchQuery = ref('');
const showDropdown = ref(false);

const filteredVariants = computed(() => {
    if (!searchQuery.value) return availableVariants.value;
    const lowerCaseQuery = String(searchQuery.value).toLowerCase();
    return availableVariants.value.filter(v => v.name.toLowerCase().includes(lowerCaseQuery));
});

const selectVariant = (variant) => {
    workOrderForm.product_variant_id = variant.id;
    searchQuery.value = variant.name;
    showDropdown.value = false;
};

const showWorkOrderModal = ref(false);
const workOrderForm = useForm({
    product_variant_id: '',
    quantity_requested: 1,
    target_date: '',
    notes: ''
});

const submitWorkOrder = () => {
    if (!workOrderForm.product_variant_id) {
        Swal.fire({ icon: 'warning', title: 'Atención', text: 'Por favor selecciona una variante de la lista.' });
        return;
    }

    workOrderForm.post(route('work-orders.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showWorkOrderModal.value = false;
            workOrderForm.reset();
            searchQuery.value = '';
            Swal.fire({ icon: 'success', title: 'Orden Creada', text: 'Se ha añadido a la cola de producción.', timer: 2000, showConfirmButton: false });
        }
    });
};

const releaseHold = (id) => {
    Swal.fire({
        title: 'Reprogramar Remanente',
        text: 'Selecciona la nueva fecha promesa para fabricar estas piezas:',
        input: 'date',
        showCancelButton: true,
        confirmButtonColor: '#ea580c',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Reprogramar',
        inputValidator: (value) => {
            if (!value) {
                return 'Debes seleccionar una fecha'
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(route('sale-details.release-hold', id), { new_date: result.value }, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({ icon: 'success', title: 'Reprogramado', timer: 1500, showConfirmButton: false });
                }
            });
        }
    });
};

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
    
    const match = dateString.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!match) return 'Sin fecha';
    
    const date = new Date(match[1], match[2] - 1, match[3]);
    
    return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
};

const submitCompletion = (sourceType, sourceId, maxQuantity) => {
    const key = sourceType + '-' + sourceId;
    const qty = parseInt(completionData.value[key]);

    if (!qty || qty < 1) {
        Swal.fire({ icon: 'warning', title: 'Atención', text: 'Por favor ingresa una cantidad válida mayor a 0.' });
        return;
    }

    const sendRequest = () => {
        const payload = { quantity: qty };
        if (sourceType === 'sale_detail') {
            payload.sale_detail_id = sourceId;
        } else {
            payload.work_order_id = sourceId;
        }

        router.post(route('production.complete'), payload, {
            preserveScroll: true,
            onSuccess: () => {
                completionData.value[key] = ''; 
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
            text: `El requerimiento solo pide ${maxQuantity} pieza(s), pero vas a registrar ${qty}. Las ${qty - maxQuantity} pieza(s) sobrantes se sumarán a tu inventario general.`,
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
                
                <!-- Remanentes Pausados (Acordeón Minimalista) -->
                <div v-if="pausedItems && pausedItems.length > 0" class="mb-4 no-print">
                    <div @click="isPausedExpanded = !isPausedExpanded" class="bg-amber-100 border border-amber-300 text-amber-800 px-4 py-2 rounded-lg cursor-pointer flex justify-between items-center shadow-sm hover:bg-amber-200 transition">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">⚠️</span>
                            <span class="font-bold text-sm">Hay {{ pausedItems.length }} remanentes de envíos parciales pendientes de reprogramar.</span>
                        </div>
                        <span class="text-xs font-bold bg-amber-50 px-2 py-1 rounded border border-amber-200">
                            {{ isPausedExpanded ? 'Ocultar' : 'Mostrar' }}
                        </span>
                    </div>

                    <div v-show="isPausedExpanded" class="mt-2 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <table class="w-full text-left text-xs whitespace-nowrap">
                            <thead class="bg-gray-50 text-gray-600 uppercase font-bold border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-2">Producto</th>
                                    <th class="px-4 py-2">Pedido</th>
                                    <th class="px-4 py-2 text-center">Cant.</th>
                                    <th class="px-4 py-2 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template v-for="group in groupedPausedItems" :key="group.sale_id">
                                    <!-- Partidas del Pedido -->
                                    <tr v-for="item in group.items" :key="item.id" class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-3">
                                            <div class="font-bold text-gray-800 text-sm">{{ item.product_name }}</div>
                                            <div class="text-[10px] text-gray-500 uppercase font-bold">{{ item.variant?.material }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <Link :href="route('sales.show', group.sale_id)" class="text-blue-600 font-bold hover:underline">Pedido #{{ group.sale_id }}</Link>
                                            <span class="text-gray-500 block text-xs mt-0.5">{{ group.client_name }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center font-bold text-amber-600 text-sm">
                                            {{ Math.max(0, item.quantity - Math.max(item.completed_quantity || 0, item.detailed_quantity || 0, item.delivered_quantity || 0)) }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button @click="releaseHold(item.id)" class="bg-amber-100 text-amber-700 px-3 py-1.5 rounded text-xs font-bold hover:bg-amber-200 border border-amber-300 transition whitespace-nowrap">
                                                Reprogramar
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row justify-between items-center bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-4 no-print gap-4">
                    <h2 class="text-2xl font-bold text-gray-800">Plan de Producción</h2>
                    
                    <div v-if="weekRange" class="flex items-center space-x-2 md:space-x-4">
                        <button @click="changeWeek(-7)" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-sm font-bold text-gray-600 transition">&laquo; Ant.</button>
                        <div class="font-bold text-blue-700 bg-blue-50 px-4 py-1.5 rounded uppercase text-sm border border-blue-100">
                            {{ formatToSpanish(weekRange.start) }} - {{ formatToSpanish(weekRange.end) }}
                        </div>
                        <button @click="changeWeek(7)" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-sm font-bold text-gray-600 transition">Sig. &raquo;</button>
                    </div>

                    <div class="flex gap-2">
                        <button @click="showWorkOrderModal = true" class="bg-purple-600 text-white px-4 py-1.5 rounded text-sm font-bold hover:bg-purple-700 transition shadow-sm inline-block">
                            + Crear Orden de Trabajo
                        </button>
                        <a :href="route('production.print')" target="_blank" class="bg-gray-800 text-white px-4 py-1.5 rounded text-sm font-bold hover:bg-gray-700 transition shadow-sm inline-block">
                            🖨️ Generar REPORTE
                        </a>
                    </div>
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
                                <th class="p-3">Modelo a Fabricar</th>
                                <th class="p-3 text-center">Estatus de Inventario</th>
                                <th class="p-3">Requerimientos (Pedidos / Órdenes)</th>
                                <th class="p-3 text-right no-print">Registrar Fabricación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(group, key) in filteredQueue" :key="key" class="hover:bg-gray-50 transition">
                                
                                <td class="p-3 align-top">
                                    <div class="font-bold text-gray-800 text-sm">{{ group.name }}</div>
                                    <div class="text-[10px] text-gray-400 uppercase font-bold mt-0.5">
                                        Mat: {{ group.material }} <span v-if="group.measurements" class="text-gray-300">|</span> {{ group.measurements }}
                                    </div>
                                </td>

                                <td class="p-3 align-top text-center">
                                    <div class="flex justify-center gap-2 text-[11px] mb-2 font-semibold">
                                        <div class="text-gray-500 text-center">
                                            Necesario<br><span class="text-gray-800 text-sm">{{ group.total_needed }}</span>
                                        </div>
                                        <div class="text-gray-500 text-center border-l px-2">
                                            En Stock<br><span class="text-green-600 text-sm">{{ group.in_stock }}</span>
                                        </div>
                                        <div class="text-gray-500 text-center border-l px-2">
                                            En Detallado<br><span :class="group.total_detailed > 0 ? 'text-amber-600' : 'text-gray-400'" class="text-sm">{{ group.total_detailed || 0 }}</span>
                                        </div>
                                        <div class="text-gray-500 text-center border-l px-2">
                                            Enviados<br><span class="text-indigo-600 font-bold text-sm">{{ group.total_delivered || 0 }}</span>
                                        </div>
                                        <div class="text-gray-500 text-center border-l pl-2">
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

                                <td class="p-3 align-top w-[250px] whitespace-normal">
                                    <div class="flex flex-wrap gap-1">
                                        <template v-for="order in group.orders" :key="order.id">
                                            <Link v-if="order.type === 'sale'" :href="route('sales.show', order.id.replace('Pedido-', ''))" 
                                                  :class="[
                                                      'px-2 py-1 rounded text-[10px] font-bold border transition shadow-sm inline-flex items-center gap-1',
                                                      order.is_overdue ? 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100' : 'bg-white text-gray-600 border-gray-200 hover:border-blue-400 hover:text-blue-600'
                                                  ]">
                                                <span>{{ order.id }}</span>
                                                <span class="text-[9px] font-normal border-l pl-1 border-current opacity-80 flex items-center gap-0.5">
                                                    📅 {{ formatPromisedDate(order.promised_date) }}
                                                </span>
                                            </Link>
                                            
                                            <span v-else class="px-2 py-1 rounded text-[10px] font-bold border border-purple-200 bg-purple-50 text-purple-700 shadow-sm inline-flex items-center gap-1">
                                                <span>🛠️ {{ order.id }}</span>
                                                <span class="text-[9px] font-normal border-l pl-1 border-current opacity-80">Manual</span>
                                            </span>
                                        </template>
                                    </div>
                                </td>

                                <td class="p-3 align-top min-w-[280px] no-print">
                                    <template v-for="item in group.details" :key="'action-'+item.source_type+'-'+item.source_id">
                                        <div v-if="group.pending_to_fabricate > 0" class="flex items-center justify-between gap-2 mb-1 bg-gray-50/50 p-2 rounded border border-gray-200 shadow-sm last:mb-0">
                                            
                                            <div class="flex flex-col">
                                                <span v-if="item.source_type === 'sale_detail'" class="text-[10px] text-gray-700 font-bold flex items-center flex-wrap gap-1">
                                                    <Link :href="route('sales.show', item.sale_id)" class="text-blue-600 hover:underline">Ped. #{{ item.sale_id }}</Link>
                                                    <span class="text-gray-400 font-normal">| Restan: {{ Math.max(0, item.quantity - Math.max(item.completed_quantity || 0, item.detailed_quantity || 0, item.delivered_quantity || 0)) }}</span>
                                                    <span v-if="item.detailed_quantity > 0" class="text-[9px] text-amber-600 bg-amber-50 border border-amber-200 px-1 rounded font-bold">En Detallado: {{ item.detailed_quantity }}</span>
                                                </span>
                                                <span v-else class="text-[10px] text-purple-700 font-bold">
                                                    WO #{{ item.source_id }} <span class="text-gray-400 ml-1 font-normal">| Restan: {{ Math.max(0, item.quantity - Math.max(item.completed_quantity || 0, item.detailed_quantity || 0, item.delivered_quantity || 0)) }}</span>
                                                </span>
                                                
                                                <span v-if="item.source_type === 'sale_detail'" class="text-[9px] text-gray-500 flex items-center gap-1">
                                                    📅 Promesa: {{ formatPromisedDate(item.sale?.promised_date) }}
                                                </span>
                                                <span v-else class="text-[9px] text-purple-500 flex items-center gap-1">
                                                    🛠️ Orden de Trabajo Manual
                                                </span>
                                            </div>

                                            <div class="flex items-center gap-1">
                                                <template v-if="Math.max(0, item.quantity - Math.max(item.completed_quantity || 0, item.detailed_quantity || 0, item.delivered_quantity || 0)) <= 0">
                                                    <span class="bg-gray-100 text-gray-500 text-[10px] px-2 py-1 rounded font-bold border border-gray-200">Cubierto por Detallado</span>
                                                </template>
                                                <template v-else>
                                                    <input 
                                                        type="number" 
                                                        v-model="completionData[item.source_type + '-' + item.source_id]" 
                                                        min="1" 
                                                        class="w-16 h-7 p-1 text-center border-gray-300 rounded text-xs focus:ring-blue-500 focus:border-blue-500 font-semibold shadow-inner" 
                                                        placeholder="Cant."
                                                    >
                                                    <button 
                                                        @click="submitCompletion(item.source_type, item.source_id, Math.max(0, item.quantity - Math.max(item.completed_quantity || 0, item.detailed_quantity || 0, item.delivered_quantity || 0)))" 
                                                        class="bg-blue-600 text-white w-7 h-7 flex items-center justify-center rounded text-xs font-bold hover:bg-blue-700 transition shadow-sm"
                                                        title="Registrar"
                                                    >
                                                        ✓
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    <div v-if="group.pending_to_fabricate === 0" class="text-xs text-green-600 font-bold text-center py-2 bg-green-50 rounded border border-green-100 mt-2">
                                        ✅ Producción Completada
                                    </div>
                                </td>

                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
        
        <!-- Modal Orden de Trabajo -->
        <div v-if="showWorkOrderModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                    <h3 class="text-lg font-extrabold text-gray-800 flex items-center gap-2">
                        <span class="text-purple-600">🛠️</span> Crear Orden de Trabajo
                    </h3>
                    <button @click="showWorkOrderModal = false" class="text-gray-400 hover:text-gray-600 transition text-xl">&times;</button>
                </div>
                <form @submit.prevent="submitWorkOrder" class="p-6 space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Variante a Producir</label>
                        <div class="relative">
                            <input 
                                type="text" 
                                v-model="searchQuery" 
                                @focus="showDropdown = true"
                                @blur="setTimeout(() => showDropdown = false, 200)"
                                placeholder="Escribe para buscar..." 
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-purple-500 focus:border-purple-500 text-sm p-2.5"
                                required
                            >
                            <ul v-show="showDropdown" class="absolute z-10 w-full bg-white border border-gray-200 shadow-xl max-h-48 rounded-lg py-1 text-sm overflow-auto focus:outline-none mt-1">
                                <li 
                                    v-for="v in filteredVariants" 
                                    :key="v.id" 
                                    @click="selectVariant(v)"
                                    class="cursor-pointer select-none relative py-2.5 px-4 hover:bg-purple-50 text-gray-700 font-medium transition-colors"
                                >
                                    {{ v.name }}
                                </li>
                                <li v-if="filteredVariants.length === 0" class="text-gray-400 py-3 px-4 text-sm italic text-center">
                                    No se encontraron variantes
                                </li>
                            </ul>
                        </div>
                        <p class="text-[10px] text-gray-500 mt-1">Selecciona cualquier variante del catálogo.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Cantidad</label>
                            <input type="number" v-model="workOrderForm.quantity_requested" required min="1" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5 text-center font-bold">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Fecha Esperada</label>
                            <input type="date" v-model="workOrderForm.target_date" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Notas (Opcional)</label>
                        <textarea v-model="workOrderForm.notes" rows="2" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5 text-sm" placeholder="Ej. Lote urgente para stock de emergencia..."></textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button type="button" @click="showWorkOrderModal = false" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 font-bold text-sm rounded-lg hover:bg-gray-50 transition shadow-sm">Cancelar</button>
                        <button type="submit" :disabled="workOrderForm.processing" class="px-5 py-2 bg-purple-600 text-white font-bold text-sm rounded-lg hover:bg-purple-700 transition shadow-sm disabled:opacity-50">Guardar Orden</button>
                    </div>
                </form>
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
    .bg-gray-50, .bg-orange-50 { background-color: #fff !important; }
}
</style>