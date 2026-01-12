<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    sales: Object // Viene paginado
});

const sendingEmail = ref(false);

// --- LÓGICA DEL MODAL DE DETALLES ---
const selectedSale = ref(null);
const showModal = ref(false);

const openSaleDetails = (sale) => {
    selectedSale.value = sale;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedSale.value = null;
};

// --- FORMATOS ---
const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};

const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('es-MX', options);
};

const sendEmail = (id) => {
    sendingEmail.value = true;
    router.post(route('sales.email', id), {}, {
        onFinish: () => sendingEmail.value = false,
        onSuccess: () => {
            Swal.fire('Enviado', 'El correo se ha enviado correctamente.', 'success');
        },
        onError: (errors) => {
            Swal.fire('Error', errors.error || 'No se pudo enviar el correo.', 'error');
        }
    });
};

// Función para cancelar venta
const cancelSale = (id) => {
    Swal.fire({
        title: '¿Cancelar Venta?',
        text: "Se devolverán los productos al inventario. Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33', // Rojo peligro
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, cancelar venta',
        cancelButtonText: 'No, volver'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('sales.cancel', id), {}, {
                onSuccess: () => {
                    closeModal(); // Cerramos el modal
                    Swal.fire('Cancelado', 'La venta ha sido anulada y el stock restaurado.', 'success');
                },
                onError: () => {
                    Swal.fire('Error', 'No se pudo cancelar la venta.', 'error');
                }
            });
        }
    });
};
</script>

<template>
    <Head title="Historial de Ventas" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Historial de Ventas</h2>
                    </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-100 border-b">
                            <tr>
                                <th class="px-6 py-3">Folio / Fecha</th>
                                <th class="px-6 py-3">Cliente</th>
                                <th class="px-6 py-3">Vendedor</th>
                                <th class="px-6 py-3">Estado</th>
                                <th class="px-6 py-3 text-right">Total</th>
                                <th class="px-6 py-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="sale in sales.data" :key="sale.id" class="border-b hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <span class="block font-bold text-gray-900">#{{ sale.id.toString().padStart(6, '0') }}</span>
                                    <span class="text-xs text-gray-500">{{ formatDate(sale.created_at) }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span v-if="sale.client" class="font-medium text-gray-800">{{ sale.client.name }}</span>
                                    <span v-else class="text-gray-400 italic">Venta General</span>
                                </td>
                                <td class="px-6 py-4">
                                    {{ sale.user.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="{
                                        'bg-green-100 text-green-800': sale.status === 'pagado',
                                        'bg-red-100 text-red-800': sale.status === 'cancelado',
                                        'bg-yellow-100 text-yellow-800': sale.status === 'pendiente' || sale.status === 'parcial'
                                    }" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full uppercase">
                                        {{ sale.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-gray-900 text-base">
                                    {{ formatMoney(sale.total) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    
                                    <button 
                                        @click="openSaleDetails(sale)" 
                                        class="text-green-600 hover:text-green-900 font-bold flex items-center justify-end gap-1"
                                        title="Ver Detalles y Opciones"
                                    >
                                        <span>Ver / Gestionar</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>

                                </td>
                            </tr>
                             <tr v-if="sales.data.length === 0">
                                <td colspan="6" class="px-6 py-8 text-center text-gray-400">
                                    Aún no hay ventas registradas.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="p-4 flex justify-between items-center border-t bg-gray-50" v-if="sales.data.length > 0">
                        <Link v-if="sales.prev_page_url" :href="sales.prev_page_url" class="px-3 py-1 bg-white border rounded hover:bg-gray-100">Anterior</Link>
                        <span v-else></span>
                        
                        <span class="text-xs text-gray-500">Página {{ sales.current_page }} de {{ sales.last_page }}</span>

                        <Link v-if="sales.next_page_url" :href="sales.next_page_url" class="px-3 py-1 bg-white border rounded hover:bg-gray-100">Siguiente</Link>
                        <span v-else></span>
                    </div>
                </div>

            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
                
                <div :class="selectedSale.status === 'cancelado' ? 'bg-red-600' : 'bg-green-600'" class="px-6 py-4 border-b border-green-500 flex justify-between items-center relative overflow-hidden">
                    <h3 class="font-bold text-lg text-white">Ticket de Venta #{{ selectedSale.id.toString().padStart(6, '0') }}</h3>
                    <button 
                        @click="closeModal" 
                        class="absolute top-4 right-4 text-white/80 hover:text-white hover:bg-white/20 rounded-full p-1 transition-colors focus:outline-none"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 bg-gray-50 max-h-[70vh] overflow-y-auto">
                    
                    <div class="mb-4 text-center border-b border-gray-200 pb-4">
                        <p class="text-sm text-gray-500">{{ formatDate(selectedSale.created_at) }}</p>
                        <p class="font-bold text-gray-800 text-lg mt-1">
                            {{ selectedSale.client ? selectedSale.client.name : 'Venta de Mostrador' }}
                        </p>
                        <p class="text-xs text-gray-400">Atendido por: {{ selectedSale.user.name }}</p>
                    </div>

                    <div class="space-y-3">
                        <div v-for="item in selectedSale.details" :key="item.id" class="flex justify-between items-start text-sm">
                            <div>
                                <p class="font-bold text-gray-700">{{ item.product_name }}</p>
                                <p class="text-xs text-gray-500">{{ item.quantity }} x {{ formatMoney(item.unit_price) }}</p>
                            </div>
                            <span class="font-bold text-gray-900">{{ formatMoney(item.subtotal) }}</span>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-gray-200 pt-4">
                        <div class="flex justify-between items-center text-xl font-bold text-gray-900">
                            <span>TOTAL</span>
                            <span>{{ formatMoney(selectedSale.total) }}</span>
                        </div>
                        <p class="text-center text-xs text-gray-400 mt-4">¡Gracias por su compra!</p>
                    </div>

                </div>

                <div class="mt-4 pt-4 border-t border-gray-100">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        
                        <a :href="route('sales.print', selectedSale.id)" target="_blank" 
                        class="flex items-center justify-center w-full gap-2 px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-lg text-sm font-bold shadow transition-all active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            Ticket Térmico
                        </a>

                        <a :href="route('sales.printNote', selectedSale.id)" target="_blank" 
                        class="flex items-center justify-center w-full gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold shadow transition-all active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Nota (PDF)
                        </a>

                        <button 
                            @click="sendEmail(selectedSale.id)"
                            :disabled="sendingEmail"
                            class="flex items-center justify-center w-full gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-bold shadow transition-all active:scale-95 disabled:opacity-70 disabled:cursor-wait"
                        >
                            <template v-if="!sendingEmail">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                Enviar Email
                            </template>
                            <template v-else>
                                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Enviando...
                            </template>
                        </button>

                        <button 
                            v-if="selectedSale.status !== 'cancelado'"
                            @click="cancelSale(selectedSale.id)" 
                            class="flex items-center justify-center w-full gap-2 px-4 py-2 bg-red-100 text-red-700 border border-red-200 hover:bg-red-600 hover:text-white rounded-lg text-sm font-bold shadow-sm transition-all active:scale-95 group"
                        >
                            <svg class="w-4 h-4 text-red-500 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Cancelar Venta
                        </button>

                        <div v-else class="flex items-center justify-center w-full gap-2 px-4 py-2 bg-red-500 text-white rounded-lg text-sm font-bold opacity-80 cursor-not-allowed shadow-inner">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                            Venta Cancelada
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>