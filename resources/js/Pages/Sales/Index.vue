<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import debounce from 'lodash/debounce';

const props = defineProps({
    sales: Object,
    filters: Object
});

const mostrarHistorial = ref(false);

const search = ref(props.filters?.search || '');
const activeTab = ref(props.filters?.stage || 'all');
const sendingEmail = ref(false);

const updateFilters = () => {
    const queryParams = {};
    if (search.value) queryParams.search = search.value;
    if (activeTab.value !== 'all') queryParams.stage = activeTab.value;
    router.get(route('sales.index'), queryParams, { preserveState: true, replace: true });
};

watch(search, debounce(() => updateFilters(), 500));

const setTab = (tab) => {
    activeTab.value = tab;
    updateFilters();
};

const stages = {
    'pedido': { label: 'Cotización/Pedido', color: 'bg-gray-100 text-gray-800 border-gray-200' },
    'confirmado': { label: 'Confirmado', color: 'bg-blue-100 text-blue-800 border-blue-200' },
    'produccion': { label: 'En Producción', color: 'bg-purple-100 text-purple-800 border-purple-200' },
    'enviado': { label: 'Enviado / Ruta', color: 'bg-orange-100 text-orange-800 border-orange-200' },
    'entregado': { label: 'Entregado', color: 'bg-green-100 text-green-800 border-green-200' },
    'cancelado': { label: 'Cancelado', color: 'bg-red-100 text-red-800 border-red-200' }
};

const getStageLabel = (stage) => stages[stage]?.label || stage;
const getStageColor = (stage) => stages[stage]?.color || 'bg-gray-100 text-gray-800';

const selectedSale = ref(null);
const showModal = ref(false);

const openSaleDetails = (sale) => {
    selectedSale.value = sale;
    showModal.value = true;
    mostrarHistorial.value = false;
};

const closeModal = () => {
    showModal.value = false;
    setTimeout(() => selectedSale.value = null, 300);
};

const formatMoney = (amount) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
const formatDate = (dateString) => {
    if (!dateString) return 'No definida';
    return new Date(dateString).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
};

const advanceStage = (id, currentStage) => {
    let nextStage = '';
    let confirmTitle = '';
    let confirmText = '';
    let btnText = '';

    if (currentStage === 'pedido') {
        nextStage = 'confirmado';
        confirmTitle = '¿Confirmar Pedido?';
        confirmText = 'El cliente ha confirmado. Pasará a estado Confirmado.';
        btnText = 'Sí, confirmar';
    } else if (currentStage === 'confirmado') {
        nextStage = 'produccion';
        confirmTitle = '¿Pasar a Producción?';
        confirmText = 'El taller podrá ver este pedido para comenzarlo.';
        btnText = 'Sí, a producción';
    } else if (currentStage === 'produccion') {
        nextStage = 'enviado';
        confirmTitle = '¿Marcar como Enviado?';
        confirmText = 'El pedido ha salido del taller. Se descontará el inventario.';
        btnText = 'Sí, enviar';
    } else if (currentStage === 'enviado') {
        nextStage = 'entregado';
        confirmTitle = '¿Marcar como Entregado?';
        confirmText = 'El cliente ya recibió su mercancía.';
        btnText = 'Sí, entregado';
    }

    if (!nextStage) return;

    // 1. CERRAMOS EL MODAL PRIMERO
    closeModal();

    // 2. ESPERAMOS LA ANIMACIÓN Y LANZAMOS LA PREGUNTA
    setTimeout(() => {
        Swal.fire({
            title: confirmTitle,
            text: confirmText,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: btnText,
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                router.patch(route('sales.update-stage', id), { stage: nextStage }, {
                    onSuccess: () => Swal.fire({ title: 'Etapa Actualizada', icon: 'success', toast: true, position: 'top-end', timer: 3000, showConfirmButton: false }),
                    onError: (errors) => Swal.fire('Error', errors.error || 'No se pudo actualizar.', 'error')
                });
            } else {
                // Si cancelan la acción, podemos volver a abrir el detalle si gustas
                // openSaleDetails(selectedSale.value);
            }
        });
    }, 300); // 300ms es el tiempo que tarda el modal de Vue en cerrarse
};

const cancelSale = (id) => {
    Swal.fire({
        title: '¿Cancelar Pedido?',
        text: "Se anulará el documento y se liberará el stock si ya fue enviado.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, cancelar pedido',
        didOpen: () => {
            const container = document.querySelector('.swal2-container');
            if (container) container.style.zIndex = '99999';
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // ¡Usamos tu misma ruta maestra enviando el estado 'cancelado'!
            router.patch(route('sales.update-stage', id), { stage: 'cancelado' }, {
                onSuccess: () => {
                    closeModal();
                    Swal.fire({ title: 'Cancelado', text: 'El pedido ha sido anulado.', icon: 'success', toast: true, position: 'top-end', timer: 3000, showConfirmButton: false });
                },
                onError: (errors) => Swal.fire('Error', errors.error || 'No se pudo cancelar.', 'error')
            });
        }
    });
};

const sendEmail = (id) => {
    sendingEmail.value = true;
    router.post(route('sales.email', id), {}, {
        onFinish: () => sendingEmail.value = false,
        onSuccess: () => Swal.fire({ title: 'Enviado', text: 'Correo enviado al cliente.', icon: 'success', didOpen: () => document.querySelector('.swal2-container').style.zIndex = '99999'}),
        onError: (errors) => Swal.fire({ title: 'Error', text: errors.error || 'No se pudo enviar el correo.', icon: 'error', didOpen: () => document.querySelector('.swal2-container').style.zIndex = '99999'})
    });
};
</script>

<template>
    <Head title="Tablero de Pedidos" />

    <AuthenticatedLayout>
        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                    <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        Tablero de Pedidos
                    </h2>
                    
                    <div class="relative w-full md:w-96">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input v-model="search" type="text" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 text-sm shadow-sm" placeholder="Buscar Folio o Cliente..." />
                    </div>
                </div>

                <div class="flex gap-2 overflow-x-auto pb-4 mb-2 scrollbar-hide">
                    <button @click="setTab('all')" :class="activeTab === 'all' ? 'bg-gray-800 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'" class="px-5 py-2 rounded-full text-sm font-bold whitespace-nowrap transition-all">Todos</button>
                    <button @click="setTab('pedido')" :class="activeTab === 'pedido' ? 'bg-gray-800 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'" class="px-5 py-2 rounded-full text-sm font-bold whitespace-nowrap transition-all flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-gray-400"></span> Cotiz / Pedidos</button>
                    <button @click="setTab('confirmado')" :class="activeTab === 'confirmado' ? 'bg-gray-800 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'" class="px-5 py-2 rounded-full text-sm font-bold whitespace-nowrap transition-all flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Confirmados</button>
                    <button @click="setTab('produccion')" :class="activeTab === 'produccion' ? 'bg-gray-800 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'" class="px-5 py-2 rounded-full text-sm font-bold whitespace-nowrap transition-all flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-purple-500"></span> Producción</button>
                    <button @click="setTab('enviado')" :class="activeTab === 'enviado' ? 'bg-gray-800 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'" class="px-5 py-2 rounded-full text-sm font-bold whitespace-nowrap transition-all flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-orange-500"></span> Enviados / Ruta</button>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-4 font-bold">Folio / Fecha</th>
                                    <th class="px-6 py-4 font-bold">Cliente</th>
                                    <th class="px-6 py-4 font-bold">Etapa (Status)</th>
                                    <th class="px-6 py-4 font-bold text-right">Total</th>
                                    <th class="px-6 py-4 font-bold text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="sale in sales?.data || []" :key="sale.id" class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="block font-bold text-gray-900 text-base">#{{ sale.id.toString().padStart(6, '0') }}</span>
                                        <span class="text-xs text-gray-400">{{ formatDate(sale.created_at) }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span v-if="sale.client" class="font-bold text-gray-800">{{ sale.client.name }}</span>
                                        <span v-else class="text-gray-400 italic">Público General</span>
                                        <div v-if="sale.promised_date" class="mt-1 flex items-center gap-1 text-[10px] text-blue-600 font-bold">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            Promesa: {{ formatDate(sale.promised_date) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="getStageColor(sale.stage)" class="px-3 py-1 inline-flex text-xs font-bold rounded-full border shadow-sm uppercase tracking-wider">
                                            {{ getStageLabel(sale.stage) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right font-black text-gray-900 text-base">
                                        {{ formatMoney(sale.total) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <button @click="openSaleDetails(sale)" class="inline-flex items-center gap-2 px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-sm font-bold text-gray-700 hover:bg-gray-50 hover:text-green-600 shadow-sm transition-all active:scale-95">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            Ver / Gestionar
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!sales?.data?.length">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                        <p class="font-medium text-lg">No se encontraron pedidos</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 flex justify-between items-center bg-gray-50 border-t border-gray-200" v-if="sales?.data?.length > 0">
                        <Link v-if="sales.prev_page_url" :href="sales.prev_page_url" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-bold shadow-sm hover:bg-gray-50">Anterior</Link>
                        <span v-else></span>
                        <span class="text-xs font-bold text-gray-500 uppercase">Página {{ sales.current_page }} de {{ sales.last_page }}</span>
                        <Link v-if="sales.next_page_url" :href="sales.next_page_url" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-bold shadow-sm hover:bg-gray-50">Siguiente</Link>
                        <span v-else></span>
                    </div>
                </div>

            </div>
        </div>

        <Modal :show="showModal" @close="closeModal" maxWidth="2xl">
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden" v-if="selectedSale">
                
                <div :class="getStageColor(selectedSale.stage).split(' ')[0]" class="px-6 py-5 border-b relative overflow-hidden flex justify-between items-center">
                    <div>
                        <h3 class="font-black text-xl text-gray-900 tracking-tight">Pedido #{{ selectedSale.id.toString().padStart(6, '0') }}</h3>
                        <span :class="getStageColor(selectedSale.stage)" class="mt-1 px-2 py-0.5 inline-flex text-[10px] font-bold rounded uppercase tracking-wider bg-white">
                            {{ getStageLabel(selectedSale.stage) }}
                        </span>
                    </div>
                    <button @click="closeModal" class="text-gray-500 hover:text-gray-900 bg-white/50 hover:bg-white rounded-full p-1.5 transition-colors focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 bg-gray-50 max-h-[60vh] overflow-y-auto">
                    <div class="mb-6 grid grid-cols-2 gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Cliente</p>
                            <p class="font-bold text-gray-800 text-sm">{{ selectedSale.client ? selectedSale.client.name : 'Venta de Mostrador' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Fechas</p>
                            <p class="text-xs font-medium text-gray-600">Creación: {{ formatDate(selectedSale.created_at) }}</p>
                            <p class="text-xs font-bold text-blue-600 mt-0.5" v-if="selectedSale.promised_date">Promesa: {{ formatDate(selectedSale.promised_date) }}</p>
                        </div>
                    </div>

                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Detalle de Producción</h4>
                    <div class="space-y-3">
                        <div v-for="item in selectedSale.details" :key="item.id" class="bg-white p-3 rounded-lg border border-gray-200 shadow-sm relative">
                            <div class="flex justify-between items-start mb-2">
                                <div class="pr-4">
                                    <p class="font-bold text-gray-800 text-sm leading-tight">{{ item.product_name }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-[9px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded font-bold uppercase">{{ item.material || 'N/A' }}</span>
                                        <span class="text-[10px] text-green-600 font-bold flex items-center gap-1">
                                            <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>
                                            {{ item.chosen_color || 'Base' }}
                                        </span>
                                    </div>
                                </div>
                                <span class="font-black text-gray-900">{{ formatMoney(item.subtotal) }}</span>
                            </div>

                            <div v-if="item.custom_notes || parseFloat(item.additional_cost) > 0" class="mt-2 p-2 bg-yellow-50 rounded-lg border border-yellow-100">
                                <p v-if="item.custom_notes" class="text-xs text-yellow-800 font-medium">📝 {{ item.custom_notes }}</p>
                                <p v-if="parseFloat(item.additional_cost) > 0" class="text-[10px] font-bold text-yellow-700 mt-1">💰 Adicional: {{ formatMoney(item.additional_cost) }}</p>
                            </div>

                            <div class="mt-2 pt-2 border-t border-gray-50 flex justify-between items-center text-xs text-gray-500">
                                <span>{{ item.quantity }} x {{ formatMoney(item.unit_price) }} c/u</span>
                                <span v-if="item.discount_percent > 0" class="text-red-500 font-bold">-{{ item.discount_percent }}% Desc.</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-500">Anticipo / Pagado</span>
                            <span class="text-sm font-bold text-gray-800">{{ formatMoney(selectedSale.paid_amount) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xl font-black text-gray-900 pt-2 border-t border-gray-100">
                            <span>TOTAL PEDIDO</span>
                            <span class="text-green-600">{{ formatMoney(selectedSale.total) }}</span>
                        </div>
                        <div v-if="selectedSale.total > selectedSale.paid_amount" class="flex justify-between items-center mt-2 pt-2 border-t border-red-100">
                            <span class="text-sm font-bold text-red-500">Saldo Pendiente:</span>
                            <span class="text-sm font-bold text-red-600">{{ formatMoney(selectedSale.total - selectedSale.paid_amount) }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-200" v-if="selectedSale.history && selectedSale.history.length > 0">
                    <button @click="mostrarHistorial = !mostrarHistorial" class="w-full flex items-center justify-between text-left group focus:outline-none p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider flex items-center gap-2 group-hover:text-blue-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Historial de Movimientos ({{ selectedSale.history.length }})
                        </span>
                        <svg :class="{'rotate-180': mostrarHistorial}" class="w-4 h-4 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    
                    <div v-show="mostrarHistorial" class="mt-4 relative border-l-2 border-gray-200 ml-5 space-y-4 mb-2">
                        <div v-for="(record, index) in selectedSale.history" :key="index" class="relative pl-4">
                            <div class="absolute w-3 h-3 bg-blue-500 rounded-full -left-[7px] top-1.5 ring-4 ring-white"></div>
                            
                            <p class="text-xs font-bold text-gray-800">
                                {{ record.user?.name || 'Sistema' }} 
                                <span class="font-normal text-gray-500">movió de</span> 
                                <span class="uppercase text-[9px] bg-gray-100 px-1 py-0.5 rounded font-bold border border-gray-200">{{ record.from_stage }}</span>
                                <span class="font-normal text-gray-500">a</span>
                                <span class="uppercase text-[9px] bg-blue-50 text-blue-600 px-1 py-0.5 rounded font-bold border border-blue-100">{{ record.to_stage }}</span>
                            </p>
                            <p class="text-[10px] text-gray-400 mt-0.5">{{ formatDate(record.created_at) }} a las {{ new Date(record.created_at).toLocaleTimeString('es-MX', {hour: '2-digit', minute:'2-digit'}) }}</p>
                            
                            <p v-if="record.notes" class="text-[10px] bg-gray-50 border border-gray-100 p-1.5 rounded mt-1 italic text-gray-600">
                                "{{ record.notes }}"
                            </p>
                        </div>
                    </div>
                </div>

                </div> <div class="p-6 border-t border-gray-100 bg-white">

                <div class="p-6 border-t border-gray-100 bg-white">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-2">
                        <a :href="route('sales.printNote', selectedSale.id)" target="_blank" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow-md transition-all active:scale-95">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Nota (PDF)
                        </a>
                        
                        <button @click="sendEmail(selectedSale.id)" :disabled="sendingEmail" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-sm font-bold shadow-md transition-all active:scale-95 disabled:opacity-70 disabled:cursor-wait">
                            <template v-if="!sendingEmail">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                Enviar Email
                            </template>
                            <template v-else>
                                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Enviando...
                            </template>
                        </button>

                        <button v-if="['pedido', 'confirmado', 'produccion', 'enviado'].includes(selectedSale.stage)" 
                            @click="advanceStage(selectedSale.id, selectedSale.stage)"
                            class="flex items-center justify-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-xl text-sm font-bold shadow-lg shadow-green-200 transition-all active:scale-95">
                            <span>
                                {{ selectedSale.stage === 'pedido' ? 'Confirmar Pedido' : 
                                   selectedSale.stage === 'confirmado' ? 'A Producción' : 
                                   selectedSale.stage === 'produccion' ? 'Marcar Enviado' : 'Marcar Entregado' }}
                            </span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>
                        </button>
                    </div>

                    <div class="flex justify-end mt-4">
                        <button v-if="selectedSale.stage !== 'cancelado'" @click="cancelSale(selectedSale.id)" class="text-xs font-bold text-red-500 hover:text-red-700 underline flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Cancelar Pedido
                        </button>
                    </div>
                </div>

            </div>
        </Modal>
    </AuthenticatedLayout>
</template>