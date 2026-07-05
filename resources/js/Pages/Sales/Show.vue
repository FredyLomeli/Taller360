<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    sale: Object
});

const showPaymentModal = ref(false);

const paymentForm = useForm({
    amount: '',
    payment_method: 'Efectivo',
    reference: '',
    paid_at: new Date().toISOString().slice(0, 10) // Fecha de hoy
});

const openPaymentModal = () => {
    // Sugerir el saldo pendiente automáticamente
    paymentForm.amount = saldoPendiente.value; 
    showPaymentModal.value = true;
};

const submitPayment = () => {
    paymentForm.post(route('sales.payment.store', props.sale.id), {
        onSuccess: () => {
            showPaymentModal.value = false;
            paymentForm.reset();
            // Swal.fire... (Opcional: feedback de éxito)
        }
    });
};

// --- ESTADOS Y COLORES ---
const statusColors = {
    'pedido': 'bg-gray-100 text-gray-800',
    'confirmado': 'bg-blue-100 text-blue-800',
    'produccion': 'bg-orange-100 text-orange-800 border-orange-200',
    'enviado': 'bg-purple-100 text-purple-800',
    'entregado': 'bg-green-100 text-green-800',
    'cancelado': 'bg-red-100 text-red-800'
};

const getStatusLabel = (stage) => {
    return stage.charAt(0).toUpperCase() + stage.slice(1);
};

// --- MODO TALLER (Ocultar Precios) ---
const productionMode = ref(false);

// --- CALCULOS ---
const saldoPendiente = computed(() => {
    return parseFloat(props.sale.total) - parseFloat(props.sale.paid_amount);
});

const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('es-MX', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute:'2-digit' 
    });
};

// --- LÓGICA DE EMBARQUES / ENTREGAS ---
const deliveryData = ref({});

const submitDelivery = (detailId, maxToDeliver, productName) => {
    const qty = parseInt(deliveryData.value[detailId]);

    if (!qty || qty < 1) {
        Swal.fire({ icon: 'warning', title: 'Atención', text: 'Ingresa una cantidad mayor a 0.' });
        return;
    }

    if (qty > maxToDeliver) {
        Swal.fire({ icon: 'error', title: 'Error', text: `Solo faltan ${maxToDeliver} piezas por entregar de este modelo.` });
        return;
    }

    Swal.fire({
        title: '¿Confirmar salida de almacén?',
        text: `¿Dar salida a ${qty} piezas de ${productName}? Se descontarán del inventario inmediatamente.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, entregar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('sales.deliveries.store'), {
                sale_detail_id: detailId,
                quantity: qty
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    deliveryData.value[detailId] = ''; 
                    Swal.fire({ icon: 'success', title: '¡Entregado!', text: 'El inventario se ha actualizado.', timer: 2000, showConfirmButton: false });
                }
            });
        }
    });
};
</script>

<template>
    <Head :title="`Pedido #${sale.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Pedido #{{ sale.id.toString().padStart(5, '0') }}
                </h2>
                
                <div class="flex items-center gap-3">
                    <label class="flex items-center cursor-pointer select-none">
                        <div class="relative">
                            <input type="checkbox" v-model="productionMode" class="sr-only">
                            <div class="block bg-gray-300 w-14 h-8 rounded-full transition" :class="productionMode ? 'bg-orange-400' : ''"></div>
                            <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition transform" :class="productionMode ? 'translate-x-6' : ''"></div>
                        </div>
                        <div class="ml-3 text-sm font-bold text-gray-600">
                            {{ productionMode ? '🛠️ MODO TALLER' : '💰 MODO OFICINA' }}
                        </div>
                    </label>

                    <div v-if="saldoPendiente > 0 && !productionMode" class="mt-3">
                        <button @click="openPaymentModal" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-1 px-3 rounded shadow text-sm flex items-center justify-center gap-2">
                            <span>💰</span> Registrar Abono
                        </button>
                    </div>

                    <button class="bg-gray-800 text-white px-4 py-2 rounded text-sm hover:bg-gray-700" onclick="window.print()">
                        🖨️ Imprimir
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12 printable-area">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6 border-t-4" 
                     :class="productionMode ? 'border-orange-400' : 'border-blue-500'">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            
                            <div>
                                <h3 class="text-xs font-bold text-gray-400 uppercase mb-2">Cliente</h3>
                                <div class="text-lg font-bold text-gray-800">{{ sale.client ? sale.client.name : 'Venta de Mostrador' }}</div>
                                <div v-if="sale.client" class="text-sm text-gray-600">
                                    <p>📞 {{ sale.client.phones }}</p>
                                    <p class="mt-1">📍 {{ sale.client.street_address }}, {{ sale.client.neighborhood }}</p>
                                </div>
                            </div>

                            <div class="text-center">
                                <h3 class="text-xs font-bold text-gray-400 uppercase mb-2">Estado Actual</h3>
                                <span class="px-4 py-2 rounded-full text-lg font-bold uppercase tracking-wide border" 
                                      :class="statusColors[sale.stage]">
                                    {{ getStatusLabel(sale.stage) }}
                                </span>
                                <p class="text-xs text-gray-400 mt-2">Creado el: {{ formatDate(sale.created_at) }}</p>
                            </div>

                            <div v-if="!productionMode" class="text-right">
                                <h3 class="text-xs font-bold text-gray-400 uppercase mb-2">Resumen Financiero</h3>
                                <div class="text-3xl font-black text-gray-800">{{ formatMoney(sale.total) }}</div>
                                
                                <div class="mt-2 text-sm">
                                    <div class="flex justify-between items-center gap-4 text-green-600 font-bold">
                                        <span>Pagado:</span>
                                        <span>{{ formatMoney(sale.paid_amount) }}</span>
                                    </div>
                                    <div v-if="saldoPendiente > 0" class="flex justify-between items-center gap-4 text-red-600 font-bold bg-red-50 px-2 py-1 rounded mt-1">
                                        <span>Restante:</span>
                                        <span>{{ formatMoney(saldoPendiente) }}</span>
                                    </div>
                                    <div v-else class="text-green-600 font-bold bg-green-50 px-2 py-1 rounded mt-1 text-center">
                                        ✅ LIQUIDADO
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-right flex flex-col justify-center items-end">
                                <span class="text-4xl">🔨</span>
                                <span class="text-sm font-bold text-gray-500">ORDEN DE PRODUCCIÓN</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
                            {{ productionMode ? 'Lista de Fabricación' : 'Detalle de Compra' }}
                        </h3>

                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-xs text-gray-500 uppercase border-b border-gray-200">
                                    <th class="py-3 font-bold w-16 text-center">Cant.</th>
                                    <th class="py-3 font-bold">Producto / Especificaciones</th>
                                    <th v-if="!productionMode" class="py-3 font-bold text-right">Precio Unit.</th>
                                    <th v-if="!productionMode" class="py-3 font-bold text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="item in sale.details" :key="item.id">
                                    <td class="py-4 text-center text-xl font-bold text-gray-700 align-top">
                                        {{ item.quantity }}
                                    </td>
                                    
                                    <td class="py-4 align-top">
                                        <div class="text-lg font-bold text-gray-800">
                                            {{ item.product_name }}
                                        </div>
                                        
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            <span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded font-bold border">
                                                🧱 {{ item.product_variant?.material || 'N/A' }}
                                            </span>
                                            
                                            <span v-if="item.chosen_color" class="px-2 py-1 bg-white border border-gray-300 text-gray-700 text-xs rounded font-bold flex items-center gap-1">
                                                🎨 Color: {{ item.chosen_color }}
                                            </span>
                                        </div>

                                        <div v-if="item.custom_notes" class="mt-2 p-2 bg-yellow-50 text-yellow-800 text-sm italic rounded border border-yellow-200 flex items-start gap-2">
                                            <span>📝</span>
                                            <span>{{ item.custom_notes }}</span>
                                        </div>
                                        <!-- INTERFAZ DE ENVÍOS PARCIALES -->
                                        <div v-if="!productionMode" class="mt-4 pt-3 border-t border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                                            <div class="text-xs">
                                                <span class="text-gray-500 font-bold uppercase tracking-wider block mb-1">Progreso de Entrega</span>
                                                <span class="font-black text-lg text-gray-800">{{ item.delivered_quantity || 0 }}</span> 
                                                <span class="text-gray-500">de {{ item.quantity }} enviadas</span>
                                                
                                                <span v-if="(item.delivered_quantity || 0) === item.quantity" class="ml-2 bg-green-100 text-green-700 px-2 py-0.5 rounded font-bold">
                                                    ✓ Completado
                                                </span>
                                            </div>

                                            <div v-if="(item.quantity - (item.delivered_quantity || 0)) > 0" class="flex items-center gap-2 bg-gray-50 p-1.5 rounded border border-gray-200 shadow-sm">
                                                <span class="text-xs font-bold text-gray-600 ml-1">Salida:</span>
                                                <input 
                                                    type="number" 
                                                    v-model="deliveryData[item.id]" 
                                                    min="1" 
                                                    :max="item.quantity - (item.delivered_quantity || 0)"
                                                    class="w-14 h-7 p-1 text-center border-gray-300 rounded text-xs focus:ring-green-500 font-bold" 
                                                    placeholder="Cant."
                                                >
                                                <button 
                                                    @click="submitDelivery(item.id, item.quantity - (item.delivered_quantity || 0), item.product_name)" 
                                                    class="bg-green-600 hover:bg-green-700 text-white w-7 h-7 flex items-center justify-center rounded font-bold transition shadow-sm"
                                                    title="Registrar Salida de Almacén"
                                                >
                                                    ✓
                                                </button>
                                            </div>
                                        </div>
                                    </td>

                                    <td v-if="!productionMode" class="py-4 text-right align-top text-gray-600">
                                        {{ formatMoney(item.unit_price) }}
                                    </td>
                                    <td v-if="!productionMode" class="py-4 text-right align-top font-bold text-gray-800">
                                        {{ formatMoney(item.subtotal) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div v-if="!productionMode && sale.payments && sale.payments.length > 0" class="mt-8 bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <h3 class="text-sm font-bold text-gray-700 uppercase mb-3 border-b pb-2">Historial de Pagos</h3>
                            <table class="w-full text-sm text-left">
                                <thead>
                                    <tr class="text-gray-500">
                                        <th>Fecha</th>
                                        <th>Método</th>
                                        <th>Referencia</th>
                                        <th class="text-right">Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="pay in sale.payments" :key="pay.id" class="border-b border-gray-100 last:border-0">
                                        <td class="py-2">{{ formatDate(pay.paid_at) }}</td>
                                        <td class="py-2 font-medium text-gray-700">{{ pay.payment_method }}</td>
                                        <td class="py-2 text-gray-500 italic">{{ pay.reference || '-' }}</td>
                                        <td class="py-2 text-right font-bold text-green-600">{{ formatMoney(pay.amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-8 border-t pt-4 text-sm text-gray-500">
                            <p class="font-bold">Notas Generales:</p>
                            <p>{{ sale.notes || 'Sin notas adicionales.' }}</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <Modal :show="showPaymentModal" @close="showPaymentModal = false" maxWidth="md">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Registrar Abono</h2>
                
                <form @submit.prevent="submitPayment">
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Monto a Abonar</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input v-model="paymentForm.amount" type="number" step="0.01" class="w-full pl-7 border-gray-300 rounded focus:ring-green-500" autoFocus>
                        </div>
                        <p class="text-xs text-red-500 mt-1" v-if="paymentForm.errors.amount">{{ paymentForm.errors.amount }}</p>
                        <p class="text-xs text-gray-500 mt-1">Saldo actual: {{ formatMoney(saldoPendiente) }}</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Método de Pago</label>
                        <select v-model="paymentForm.payment_method" class="w-full border-gray-300 rounded focus:ring-green-500">
                            <option>Efectivo</option>
                            <option>Transferencia</option>
                            <option>Tarjeta Débito/Crédito</option>
                            <option>Cheque</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Referencia / Nota (Opcional)</label>
                        <input v-model="paymentForm.reference" type="text" placeholder="Ej: Autorización 4543" class="w-full border-gray-300 rounded focus:ring-green-500">
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Fecha de Pago</label>
                        <input v-model="paymentForm.paid_at" type="date" class="w-full border-gray-300 rounded focus:ring-green-500">
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" @click="showPaymentModal = false" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancelar</button>
                        <button type="submit" :disabled="paymentForm.processing" class="bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700 shadow disabled:opacity-50">
                            Confirmar Pago
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
@media print {
    /* Ocultar elementos de navegación al imprimir */
    nav, header, button, label.flex { 
        display: none !important; 
    }
    .printable-area {
        padding: 0 !important;
        margin: 0 !important;
    }
    /* Forzar modo taller en impresión si se desea, o respetar la vista */
}
</style>