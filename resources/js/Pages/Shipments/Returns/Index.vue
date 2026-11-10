<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    returns: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        default: () => ({
            total_returns: 0,
            total_items: 0,
            damaged_items: 0,
            scrapped_items: 0,
            wrong_color_items: 0
        })
    }
});

const searchQuery = ref('');
const selectedReturn = ref(null);
const showDetailModal = ref(false);

const filteredReturns = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.returns;

    return props.returns.filter(ret => {
        const folio = (ret.folio || '').toLowerCase();
        const client = (ret.client?.name || '').toLowerCase();
        const authBy = (ret.authorized_by || '').toLowerCase();
        const notes = (ret.notes || '').toLowerCase();
        return folio.includes(q) || client.includes(q) || authBy.includes(q) || notes.includes(q);
    });
});

const openDetail = (ret) => {
    selectedReturn.value = ret;
    showDetailModal.value = true;
};

const closeDetail = () => {
    selectedReturn.value = null;
    showDetailModal.value = false;
};

const formatDateTime = (dateStr) => {
    if (!dateStr) return 'N/A';
    const d = new Date(dateStr);
    return d.toLocaleString('es-MX', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getReasonLabel = (reason) => {
    const map = {
        dano_transporte: 'Daño en Transporte',
        defecto_fabrica: 'Defecto de Fábrica',
        color_incorrecto: 'Color Incorrecto',
        cliente_cancelo: 'Cancelación / Rechazo Cliente',
        otro: 'Otro Motivo'
    };
    return map[reason] || reason;
};

const getDispositionBadge = (disp) => {
    switch (disp) {
        case 'danada':
            return { label: 'Dañada (Taller)', class: 'bg-amber-100 text-amber-800 border-amber-200' };
        case 'baja':
            return { label: 'Baja Definitiva (Merma)', class: 'bg-red-100 text-red-800 border-red-200' };
        case 'color_equivocado':
            return { label: 'Color Reasignado', class: 'bg-purple-100 text-purple-800 border-purple-200' };
        case 'buen_estado':
            return { label: 'Buen Estado (Almacén)', class: 'bg-green-100 text-green-800 border-green-200' };
        default:
            return { label: disp, class: 'bg-gray-100 text-gray-800 border-gray-200' };
    }
};
</script>

<template>
    <Head title="Historial de Devoluciones" />

    <AuthenticatedLayout>
        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Encabezado y Navegación -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl shadow-xs border border-gray-200">
                    <div>
                        <div class="flex items-center gap-3">
                            <Link :href="route('shipments.index')" class="text-sm font-semibold text-gray-500 hover:text-gray-800 flex items-center gap-1 transition-colors">
                                ← Volver a Embarques
                            </Link>
                        </div>
                        <h2 class="text-2xl font-black text-gray-900 mt-1 flex items-center gap-2">
                            <span>↩️</span> Historial de Devoluciones de Clientes
                        </h2>
                        <p class="text-sm text-gray-500">Registro de piezas retornadas, motivos y reingresos a stock</p>
                    </div>

                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <Link :href="route('shipments.returns.create')" class="w-full sm:w-auto bg-amber-600 hover:bg-amber-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-sm transition-all active:scale-95 flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Registrar Devolución
                        </Link>
                    </div>
                </div>

                <!-- Tarjetas de Resumen / KPIs -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-gray-200 flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl font-bold">
                            📦
                        </div>
                        <div>
                            <div class="text-2xl font-black text-gray-900">{{ stats.total_returns || 0 }}</div>
                            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Devoluciones</div>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-gray-200 flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl font-bold">
                            🔢
                        </div>
                        <div>
                            <div class="text-2xl font-black text-gray-900">{{ stats.total_items || 0 }}</div>
                            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Piezas Totales</div>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-gray-200 flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-bold">
                            ⚠️
                        </div>
                        <div>
                            <div class="text-2xl font-black text-amber-700">{{ stats.damaged_items || 0 }}</div>
                            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">A Stock Dañado</div>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-gray-200 flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-xl font-bold">
                            🗑️
                        </div>
                        <div>
                            <div class="text-2xl font-black text-red-700">{{ stats.scrapped_items || 0 }}</div>
                            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Bajas (Merma)</div>
                        </div>
                    </div>
                </div>

                <!-- Buscador y Filtro -->
                <div class="bg-white p-4 rounded-2xl shadow-xs border border-gray-200 flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar por folio (DEV-...), cliente, autorizado por o notas..."
                        class="w-full text-sm border-0 focus:ring-0 text-gray-800 placeholder-gray-400"
                    />
                    <button v-if="searchQuery" @click="searchQuery = ''" class="text-xs text-gray-400 hover:text-gray-600 font-bold px-2">
                        Limpiar
                    </button>
                </div>

                <!-- Tabla de Devoluciones -->
                <div class="bg-white rounded-2xl shadow-xs border border-gray-200 overflow-hidden">
                    <div v-if="filteredReturns.length === 0" class="p-12 text-center text-gray-500">
                        <div class="text-4xl mb-3">🔍</div>
                        <h3 class="text-base font-bold text-gray-800">No se encontraron devoluciones</h3>
                        <p class="text-sm text-gray-400 mt-1">Aún no hay registros que coincidan con la búsqueda.</p>
                        <Link :href="route('shipments.returns.create')" class="mt-4 inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-xs">
                            Registrar la primera devolución
                        </Link>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase font-semibold">
                                <tr>
                                    <th class="px-6 py-4">Folio</th>
                                    <th class="px-6 py-4">Fecha</th>
                                    <th class="px-6 py-4">Cliente</th>
                                    <th class="px-6 py-4 text-center">Pedido(s)</th>
                                    <th class="px-6 py-4">Autorizado Por</th>
                                    <th class="px-6 py-4 text-center">Piezas / Destino</th>
                                    <th class="px-6 py-4">Observaciones</th>
                                    <th class="px-6 py-4 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="ret in filteredReturns" :key="ret.id" class="hover:bg-amber-50/40 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap font-black text-amber-800">
                                        <span class="bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-lg">
                                            {{ ret.folio }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-medium">
                                        {{ formatDateTime(ret.returned_at) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-900">{{ ret.client?.name || 'Cliente sin nombre' }}</div>
                                        <div class="text-xs text-gray-400">{{ ret.client?.phones || ret.client?.phone || 'Sin teléfono' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <div v-if="ret.sale_ids && ret.sale_ids.length > 0" class="flex flex-wrap justify-center gap-1">
                                            <Link
                                                v-for="sid in ret.sale_ids"
                                                :key="sid"
                                                :href="route('sales.show', sid)"
                                                class="inline-flex items-center gap-1 text-xs font-black text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-2.5 py-1 rounded-lg transition-all active:scale-95 shadow-xs"
                                                title="Ir al detalle del pedido"
                                            >
                                                <span>📄</span> #{{ String(sid).padStart(4, '0') }}
                                            </Link>
                                        </div>
                                        <span v-else class="text-xs text-gray-400">—</span>
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-gray-700">
                                        <div class="flex items-center gap-1.5">
                                            <span>✍️</span>
                                            <span>{{ ret.authorized_by }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black bg-blue-100 text-blue-800">
                                            {{ ret.items?.reduce((acc, it) => acc + it.quantity, 0) || 0 }} pzas
                                        </span>
                                        <div class="text-[11px] text-gray-500 mt-1">
                                            {{ ret.items?.length || 0 }} partida(s)
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 max-w-xs truncate text-gray-500" :title="ret.notes">
                                        {{ ret.notes || '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <button
                                            @click="openDetail(ret)"
                                            class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition-colors"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            Ver Detalle
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal Detalle de la Devolución -->
        <div v-if="showDetailModal && selectedReturn" class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-3xl w-full shadow-2xl border border-gray-200 overflow-hidden transform transition-all">
                <!-- Header Modal -->
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <div>
                        <div class="text-xs font-bold text-gray-500 uppercase tracking-wider">Detalle de Auditoría</div>
                        <h3 class="text-xl font-black text-gray-900 flex items-center gap-2">
                            <span>Devolución</span>
                            <span class="text-amber-700">{{ selectedReturn.folio }}</span>
                        </h3>
                    </div>
                    <button @click="closeDetail" class="text-gray-400 hover:text-gray-600 p-2 rounded-xl text-xl leading-none">
                        ✕
                    </button>
                </div>

                <!-- Contenido Modal -->
                <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
                    <!-- Ficha Resumen -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-gray-50 p-4 rounded-xl text-xs">
                        <div>
                            <span class="text-gray-400 block">Cliente:</span>
                            <span class="font-bold text-gray-800">{{ selectedReturn.client?.name }}</span>
                        </div>
                        <div>
                            <span class="text-gray-400 block">Fecha y Hora:</span>
                            <span class="font-bold text-gray-800">{{ formatDateTime(selectedReturn.returned_at) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-400 block">Autorizado Por:</span>
                            <span class="font-bold text-gray-800">{{ selectedReturn.authorized_by }}</span>
                        </div>
                        <div>
                            <span class="text-gray-400 block">Registrado Por:</span>
                            <span class="font-bold text-gray-800">{{ selectedReturn.user?.name || 'Sistema' }}</span>
                        </div>
                    </div>

                    <div v-if="selectedReturn.notes" class="text-xs bg-amber-50 text-amber-900 border border-amber-200 p-3 rounded-xl">
                        <span class="font-bold block">Notas Generales:</span>
                        {{ selectedReturn.notes }}
                    </div>

                    <!-- Listado de Partidas -->
                    <div>
                        <h4 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-1.5">
                            <span>📋</span> Partidas Devueltas ({{ selectedReturn.items?.length || 0 }})
                        </h4>
                        <div class="space-y-3">
                            <div
                                v-for="(item, idx) in selectedReturn.items"
                                :key="item.id || idx"
                                class="p-4 rounded-xl border border-gray-200 bg-white hover:border-gray-300 transition-all flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3"
                            >
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-black text-gray-900 text-sm">
                                            {{ item.sale_detail?.product_name || 'Producto' }}
                                        </span>
                                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md font-medium">
                                            Cant: <strong class="text-gray-900">{{ item.quantity }}</strong>
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-500 flex flex-wrap items-center gap-3">
                                        <Link
                                            v-if="item.sale_detail?.sale_id"
                                            :href="route('sales.show', item.sale_detail.sale_id)"
                                            class="font-black text-blue-600 hover:text-blue-800 underline inline-flex items-center gap-1"
                                            title="Ver pedido"
                                        >
                                            📄 Pedido #{{ String(item.sale_detail.sale_id).padStart(4, '0') }}
                                        </Link>
                                        <span>•</span>
                                        <span v-if="item.shipment">
                                            Embarque: <strong>#{{ String(item.shipment.id).padStart(4, '0') }}</strong> ({{ item.shipment.driver_name }})
                                        </span>
                                        <span>•</span>
                                        <span>Motivo: <strong>{{ getReasonLabel(item.reason_type) }}</strong></span>
                                        <span v-if="item.chosen_color || item.color_relation">• Color devuelto: <strong>{{ item.color_relation?.name || item.chosen_color }}</strong></span>
                                    </div>
                                    <div v-if="item.notes" class="text-xs text-gray-600 italic bg-gray-50 p-2 rounded-lg mt-1">
                                        "{{ item.notes }}"
                                    </div>
                                </div>

                                <div class="shrink-0">
                                    <span
                                        :class="getDispositionBadge(item.disposition).class"
                                        class="px-3 py-1 rounded-full text-xs font-bold border block text-center"
                                    >
                                        {{ getDispositionBadge(item.disposition).label }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Modal -->
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-end">
                    <button
                        @click="closeDetail"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-5 py-2 rounded-xl text-sm font-bold transition-colors"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
