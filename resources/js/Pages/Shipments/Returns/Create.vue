<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    clients: {
        type: Array,
        default: () => []
    },
    selectedClientId: {
        type: [Number, String],
        default: null
    },
    returnableDeliveries: {
        type: Array,
        default: () => []
    },
    colors: {
        type: Array,
        default: () => []
    }
});

// Selector interactivo de cliente
const clientSearchQuery = ref('');
const isClientDropdownOpen = ref(false);

const filteredClients = computed(() => {
    const q = clientSearchQuery.value.trim().toLowerCase();
    if (!q) return props.clients;
    return props.clients.filter(c => 
        (c.name || '').toLowerCase().includes(q) || 
        (c.business_name || '').toLowerCase().includes(q) || 
        (c.phones || c.phone || '').includes(q)
    );
});

const currentSelectedClient = computed(() => {
    return props.clients.find(c => c.id === form.client_id) || null;
});

const selectClient = (client) => {
    form.client_id = client.id;
    isClientDropdownOpen.value = false;
    clientSearchQuery.value = '';
    
    // Reset partidas seleccionadas al cambiar de cliente
    form.items = [];
    
    // Carga parcial de entregas vía Inertia
    router.get(
        route('shipments.returns.create'),
        { client_id: client.id },
        { preserveState: true, preserveScroll: true, only: ['returnableDeliveries', 'selectedClientId'] }
    );
};

// Formulario de Devolución
const form = useForm({
    client_id: props.selectedClientId || null,
    authorized_by: '',
    returned_at: new Date().toISOString().slice(0, 16),
    notes: '',
    items: []
});

// Partidas seleccionadas para devolución
const selectedDeliveryIds = ref([]);

const toggleDeliverySelection = (delivery) => {
    const key = `${delivery.shipment_id}_${delivery.sale_detail_id}`;
    const existingIndex = form.items.findIndex(it => it.shipment_id === delivery.shipment_id && it.sale_detail_id === delivery.sale_detail_id);

    if (existingIndex >= 0) {
        form.items.splice(existingIndex, 1);
        selectedDeliveryIds.value = selectedDeliveryIds.value.filter(k => k !== key);
    } else {
        form.items.push({
            delivery_key: key,
            sale_detail_id: delivery.sale_detail_id,
            shipment_id: delivery.shipment_id,
            product_name: delivery.product_name,
            material: delivery.material,
            measurements: delivery.measurements,
            delivered_color: delivery.chosen_color,
            max_quantity: delivery.net_available,
            quantity: 1,
            reason_type: 'dano_transporte',
            disposition: 'danada',
            color_id: delivery.color_id || null,
            chosen_color: delivery.chosen_color || '',
            notes: ''
        });
        selectedDeliveryIds.value.push(key);
    }
};

const isDeliverySelected = (delivery) => {
    const key = `${delivery.shipment_id}_${delivery.sale_detail_id}`;
    return selectedDeliveryIds.value.includes(key);
};

const getItemForDelivery = (delivery) => {
    return form.items.find(it => it.shipment_id === delivery.shipment_id && it.sale_detail_id === delivery.sale_detail_id);
};

const handleDispositionChange = (item, delivery) => {
    if (item.disposition === 'color_equivocado') {
        // Sugerir selección de color real
        if (!item.color_id && props.colors.length > 0) {
            item.color_id = props.colors[0].id;
            item.chosen_color = props.colors[0].name;
        }
    } else {
        item.color_id = delivery.color_id || null;
        item.chosen_color = delivery.chosen_color || '';
    }
};

const handleColorSelect = (item, event) => {
    const colorId = parseInt(event.target.value);
    const color = props.colors.find(c => c.id === colorId);
    item.color_id = colorId || null;
    item.chosen_color = color ? color.name : '';
};

// Validación y Envío
const submitReturn = () => {
    if (!form.client_id) {
        Swal.fire('Cliente Requerido', 'Selecciona el cliente que realiza la devolución.', 'warning');
        return;
    }

    if (!form.authorized_by || !form.authorized_by.trim()) {
        Swal.fire('Autorización Requerida', 'El campo "¿Quién autoriza la devolución?" es obligatorio (Regla B3).', 'warning');
        return;
    }

    if (form.items.length === 0) {
        Swal.fire('Sin Partidas', 'Debes seleccionar al menos una partida a devolver.', 'warning');
        return;
    }

    for (const item of form.items) {
        if (!item.quantity || item.quantity < 1) {
            Swal.fire('Cantidad Inválida', `La cantidad a devolver de "${item.product_name}" debe ser al menos 1.`, 'warning');
            return;
        }
        if (item.quantity > item.max_quantity) {
            Swal.fire('Cantidad Excedida', `La cantidad a devolver de "${item.product_name}" (${item.quantity}) excede las ${item.max_quantity} disponibles.`, 'error');
            return;
        }
        if (item.disposition === 'color_equivocado' && !item.color_id) {
            Swal.fire('Color Requerido', `Debes indicar el color físico real regresado para "${item.product_name}".`, 'warning');
            return;
        }
    }

    const totalPieces = form.items.reduce((acc, it) => acc + parseInt(it.quantity), 0);

    Swal.fire({
        title: '¿Confirmar Devolución?',
        html: `Se registrará la devolución de <b>${totalPieces} pieza(s)</b> autorizada por: <br><b>${form.authorized_by}</b>.<br><br><span class="text-xs text-gray-500">El stock se ajustará de forma inmediata según el destino indicado.</span>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d97706',
        confirmButtonText: 'Sí, registrar devolución',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            form.post(route('shipments.returns.store'), {
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Devolución Registrada',
                        text: 'El folio fue generado y el inventario ha sido actualizado.',
                        timer: 2500,
                        showConfirmButton: false
                    });
                },
                onError: (errors) => {
                    const msg = errors.items || errors.authorized_by || errors.error || Object.values(errors)[0] || 'Ocurrió un error al registrar la devolución.';
                    Swal.fire('Error', msg, 'error');
                }
            });
        }
    });
};
</script>

<template>
    <Head title="Registrar Devolución de Cliente" />

    <AuthenticatedLayout>
        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Header de Navegación -->
                <div class="bg-white p-6 rounded-2xl shadow-xs border border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <Link :href="route('shipments.returns.index')" class="text-sm font-semibold text-gray-500 hover:text-gray-800 flex items-center gap-1 transition-colors">
                            ← Volver al Historial de Devoluciones
                        </Link>
                        <h2 class="text-2xl font-black text-gray-900 mt-1 flex items-center gap-2">
                            <span>✍️</span> Registrar Devolución de Cliente
                        </h2>
                        <p class="text-sm text-gray-500">Selecciona el cliente y las partidas de viajes entregados que reingresarán</p>
                    </div>

                    <button
                        @click="submitReturn"
                        :disabled="form.processing || form.items.length === 0"
                        class="w-full sm:w-auto bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white px-6 py-3 rounded-xl font-bold shadow-sm transition-all active:scale-95 flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <span>{{ form.processing ? 'Procesando...' : 'Completar Devolución' }}</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- Columna Izquierda: Datos Generales de la Devolución -->
                    <div class="lg:col-span-1 space-y-6">
                        <div class="bg-white p-6 rounded-2xl shadow-xs border border-gray-200 space-y-5">
                            <h3 class="text-base font-black text-gray-900 border-b pb-3 flex items-center gap-2">
                                <span>👤</span> 1. Información General
                            </h3>

                            <!-- Selector de Cliente con Buscador -->
                            <div class="relative">
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                    Cliente con Entregas <span class="text-red-500">*</span>
                                </label>
                                
                                <div
                                    @click="isClientDropdownOpen = !isClientDropdownOpen"
                                    class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm flex justify-between items-center cursor-pointer bg-white hover:border-gray-400 transition-colors"
                                >
                                    <span v-if="currentSelectedClient" class="font-bold text-gray-900 truncate">
                                        {{ currentSelectedClient.name }} <span v-if="currentSelectedClient.business_name" class="text-xs text-gray-500 font-normal">({{ currentSelectedClient.business_name }})</span>
                                    </span>
                                    <span v-else class="text-gray-400">Selecciona un cliente...</span>
                                    <span class="text-gray-400 text-xs">▼</span>
                                </div>

                                <!-- Dropdown flotante -->
                                <div
                                    v-if="isClientDropdownOpen"
                                    class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-xl z-30 max-h-60 overflow-hidden flex flex-col"
                                >
                                    <div class="p-2 border-b bg-gray-50">
                                        <input
                                            v-model="clientSearchQuery"
                                            type="text"
                                            placeholder="Buscar cliente..."
                                            class="w-full text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 focus:ring-1 focus:ring-amber-500"
                                            @click.stop
                                        />
                                    </div>
                                    <div class="overflow-y-auto divide-y divide-gray-100 flex-1">
                                        <div
                                            v-for="c in filteredClients"
                                            :key="c.id"
                                            @click="selectClient(c)"
                                            class="p-2.5 text-xs hover:bg-amber-50 cursor-pointer flex justify-between items-center"
                                        >
                                            <div class="font-bold text-gray-800">
                                                {{ c.name }} <span v-if="c.business_name" class="text-gray-400 font-normal">({{ c.business_name }})</span>
                                            </div>
                                            <span class="text-gray-400 text-[10px]">{{ c.phones || c.phone || '' }}</span>
                                        </div>
                                        <div v-if="filteredClients.length === 0" class="p-4 text-xs text-gray-400 text-center">
                                            No hay clientes con entregas pendientes de devolución.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Campo Obligatorio: Autorizado Por (Regla B3) -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                    ¿Quién Autoriza la Devolución? <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.authorized_by"
                                    type="text"
                                    placeholder="Ej: Lic. Gómez / Jefe de Almacén"
                                    class="w-full text-sm border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-amber-500 focus:border-amber-500 font-semibold"
                                />
                                <p class="text-[11px] text-gray-400 mt-1">Nombre y cargo de quien aprobó la recepción (obligatorio).</p>
                                <p v-if="form.errors.authorized_by" class="text-xs text-red-600 font-bold mt-1">
                                    {{ form.errors.authorized_by }}
                                </p>
                            </div>

                            <!-- Fecha y Hora -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                    Fecha y Hora de Recepción
                                </label>
                                <input
                                    v-model="form.returned_at"
                                    type="datetime-local"
                                    class="w-full text-sm border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-amber-500 focus:border-amber-500"
                                />
                            </div>

                            <!-- Observaciones Generales -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                    Observaciones / Motivo General
                                </label>
                                <textarea
                                    v-model="form.notes"
                                    rows="3"
                                    placeholder="Detalles sobre flete, incidente, cliente inconforme, etc..."
                                    class="w-full text-sm border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-amber-500 focus:border-amber-500"
                                ></textarea>
                            </div>
                        </div>

                        <!-- Resumen de Selección -->
                        <div class="bg-amber-50 border border-amber-200 p-5 rounded-2xl text-xs space-y-2">
                            <div class="font-black text-amber-900 text-sm flex items-center justify-between">
                                <span>Partidas a Devolver</span>
                                <span class="bg-amber-200 text-amber-900 px-2 py-0.5 rounded-full">{{ form.items.length }}</span>
                            </div>
                            <p class="text-amber-800">
                                Piezas totales seleccionadas: 
                                <strong>{{ form.items.reduce((acc, it) => acc + (parseInt(it.quantity) || 0), 0) }}</strong>
                            </p>
                            <p class="text-amber-700 text-[11px]">
                                Los movimientos de stock y bitácoras de auditoría se generarán automáticamente en el servidor bajo una transacción segura.
                            </p>
                        </div>
                    </div>

                    <!-- Columna Derecha: Tabla de Entregas Disponibles -->
                    <div class="lg:col-span-2 space-y-4">
                        <div class="bg-white p-6 rounded-2xl shadow-xs border border-gray-200">
                            <div class="flex justify-between items-center border-b pb-3 mb-4">
                                <div>
                                    <h3 class="text-base font-black text-gray-900 flex items-center gap-2">
                                        <span>🚚</span> 2. Partidas Entregadas Disponibles
                                    </h3>
                                    <p class="text-xs text-gray-500">Selecciona las partidas que el cliente está retornando</p>
                                </div>
                            </div>

                            <!-- Estado: Sin Cliente Seleccionado -->
                            <div v-if="!form.client_id" class="p-12 text-center text-gray-400">
                                <div class="text-4xl mb-2">👈</div>
                                <h4 class="font-bold text-gray-700 text-sm">Selecciona un cliente a la izquierda</h4>
                                <p class="text-xs text-gray-400 mt-1">Cargaremos en vivo sus remisiones de entrega activas.</p>
                            </div>

                            <!-- Estado: Cliente sin Entregas Disponibles -->
                            <div v-else-if="returnableDeliveries.length === 0" class="p-12 text-center text-gray-400">
                                <div class="text-4xl mb-2">📦</div>
                                <h4 class="font-bold text-gray-700 text-sm">Este cliente no tiene entregas activas</h4>
                                <p class="text-xs text-gray-400 mt-1">Todas las piezas entregadas ya fueron devueltas o los viajes están cancelados.</p>
                            </div>

                            <!-- Lista de Entregas Disponibles -->
                            <div v-else class="space-y-4">
                                <div
                                    v-for="deliv in returnableDeliveries"
                                    :key="deliv.delivery_id"
                                    :class="{
                                        'border-amber-400 bg-amber-50/20 ring-1 ring-amber-300': isDeliverySelected(deliv),
                                        'border-gray-200 bg-white hover:border-gray-300': !isDeliverySelected(deliv)
                                    }"
                                    class="p-4 rounded-xl border transition-all"
                                >
                                    <!-- Fila Superior: Checkbox y Datos de la Partida -->
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex items-start gap-3">
                                            <input
                                                type="checkbox"
                                                :checked="isDeliverySelected(deliv)"
                                                @change="toggleDeliverySelection(deliv)"
                                                class="w-5 h-5 mt-0.5 text-amber-600 rounded-lg border-gray-300 focus:ring-amber-500 cursor-pointer"
                                            />
                                            <div>
                                                <div class="font-black text-gray-900 text-sm flex items-center gap-2">
                                                    <span>{{ deliv.product_name }}</span>
                                                    <span class="text-xs text-gray-500 font-normal">({{ deliv.material }} - {{ deliv.measurements || 'S/M' }})</span>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-0.5 flex flex-wrap items-center gap-2">
                                                    <span class="font-semibold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-md">
                                                        Viaje {{ deliv.shipment_folio }} ({{ deliv.shipment_driver }})
                                                    </span>
                                                    <span>• Fecha: {{ deliv.shipment_date }}</span>
                                                    <span v-if="deliv.chosen_color">• Color original: <strong>{{ deliv.chosen_color }}</strong></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Cantidades: Entregadas vs Netas Disponibles -->
                                        <div class="text-right shrink-0">
                                            <div class="text-xs text-gray-500">
                                                Entregadas: <strong class="text-gray-800">{{ deliv.quantity_delivered }}</strong>
                                                <span v-if="deliv.already_returned > 0" class="text-red-600 text-[11px]">
                                                    (-{{ deliv.already_returned }} devueltas)
                                                </span>
                                            </div>
                                            <div class="text-xs font-black text-green-700 bg-green-50 px-2 py-0.5 rounded-md mt-1 inline-block">
                                                Disponible: {{ deliv.net_available }} pza(s)
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Panel de Configuración de la Partida (Visible al estar seleccionada) -->
                                    <div
                                        v-if="isDeliverySelected(deliv) && getItemForDelivery(deliv)"
                                        class="mt-4 pt-4 border-t border-amber-200/60 grid grid-cols-1 sm:grid-cols-3 gap-3 bg-white p-3 rounded-lg"
                                    >
                                        <!-- Cantidad a Devolver -->
                                        <div>
                                            <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">
                                                Cant. a Devolver (Máx: {{ deliv.net_available }}) <span class="text-red-500">*</span>
                                            </label>
                                            <input
                                                v-model.number="getItemForDelivery(deliv).quantity"
                                                type="number"
                                                min="1"
                                                :max="deliv.net_available"
                                                class="w-full text-sm border-gray-300 rounded-lg px-2.5 py-1.5 font-black text-amber-800 focus:ring-amber-500"
                                            />
                                        </div>

                                        <!-- Motivo -->
                                        <div>
                                            <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">
                                                Motivo de Devolución <span class="text-red-500">*</span>
                                            </label>
                                            <select
                                                v-model="getItemForDelivery(deliv).reason_type"
                                                class="w-full text-xs border-gray-300 rounded-lg px-2.5 py-1.5 focus:ring-amber-500"
                                            >
                                                <option value="dano_transporte">Daño en Transporte</option>
                                                <option value="defecto_fabrica">Defecto de Fábrica</option>
                                                <option value="color_incorrecto">Color Incorrecto</option>
                                                <option value="cliente_cancelo">Cliente Canceló / Rechazó</option>
                                                <option value="otro">Otro Motivo</option>
                                            </select>
                                        </div>

                                        <!-- Disposición / Destino de la Pieza -->
                                        <div>
                                            <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">
                                                Destino / Disposición <span class="text-red-500">*</span>
                                            </label>
                                            <select
                                                v-model="getItemForDelivery(deliv).disposition"
                                                @change="handleDispositionChange(getItemForDelivery(deliv), deliv)"
                                                class="w-full text-xs border-gray-300 rounded-lg px-2.5 py-1.5 font-bold focus:ring-amber-500"
                                            >
                                                <option value="danada">⚠️ Stock Dañado (Taller Reparación)</option>
                                                <option value="color_equivocado">🎨 Color Erróneo (Reingreso con otro color)</option>
                                                <option value="buen_estado">✓ Buen Estado (Reingreso Almacén)</option>
                                                <option value="baja">🗑️ Baja Definitiva (Merma total)</option>
                                            </select>
                                        </div>

                                        <!-- Selector de Color Real si es Color Equivocado -->
                                        <div v-if="getItemForDelivery(deliv).disposition === 'color_equivocado'" class="sm:col-span-3 bg-purple-50 p-3 rounded-lg border border-purple-200">
                                            <label class="block text-[11px] font-bold text-purple-900 uppercase mb-1">
                                                Color Físico Real que Regresó al Almacén <span class="text-red-500">*</span>
                                            </label>
                                            <select
                                                :value="getItemForDelivery(deliv).color_id"
                                                @change="handleColorSelect(getItemForDelivery(deliv), $event)"
                                                class="w-full text-xs border-purple-300 rounded-lg px-2.5 py-1.5 focus:ring-purple-500 font-bold bg-white text-purple-900"
                                            >
                                                <option v-for="c in colors" :key="c.id" :value="c.id">
                                                    {{ c.name }}
                                                </option>
                                            </select>
                                            <p class="text-[10px] text-purple-700 mt-1">
                                                Se sumará la existencia física al inventario de este color en el almacén.
                                            </p>
                                        </div>

                                        <!-- Notas Particulares del Producto -->
                                        <div class="sm:col-span-3">
                                            <input
                                                v-model="getItemForDelivery(deliv).notes"
                                                type="text"
                                                placeholder="Notas de esta pieza (ej: golpe en esquina superior, raspón flete, etc.)"
                                                class="w-full text-xs border-gray-200 rounded-lg px-2.5 py-1.5 focus:ring-amber-500 text-gray-700"
                                            />
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
