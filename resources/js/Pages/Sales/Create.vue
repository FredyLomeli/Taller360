<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClientAutocomplete from '@/Components/ClientAutocomplete.vue';
import { VueSignaturePad } from 'vue-signature-pad';
import Modal from '@/Components/Modal.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const signaturePad = ref(null);

const props = defineProps({
    products: Array,
    clients: Array,
});

// --- CONFIGURACIÓN DE COLORES (CATÁLOGO VISUAL) ---
const materialColors = {
    'MDF': [
        { name: 'Chocolate', hex: '#5D4037' },
        { name: 'Blanco', hex: '#F5F5F5', border: true },
        { name: 'Gris', hex: '#9E9E9E' },
        { name: 'Nogal', hex: '#8D6E63' },
        { name: 'Negro', hex: '#212121' }
    ],
    'Madera': [
        { name: 'Natural', hex: '#D7CCC8' },
        { name: 'Caoba', hex: '#4E342E' },
        { name: 'Nogal', hex: '#5D4037' },
        { name: 'Cedro', hex: '#A1887F' }
    ],
    'Melamina': [
        { name: 'Blanco', hex: '#FFFFFF', border: true },
        { name: 'Gris', hex: '#BDBDBD' },
        { name: 'Texturizado', hex: '#EFEBE9' },
        { name: 'Oyamel', hex: '#D7CCC8' }
    ],
    'default': [{ name: 'Estándar', hex: '#CCCCCC' }]
};

const getColorsForMaterial = (materialName) => {
    const key = Object.keys(materialColors).find(k => materialName.includes(k));
    return key ? materialColors[key] : materialColors['default'];
};

// --- ESTADO GLOBAL ---
const clientList = ref([...props.clients]);
const selectedClient = ref(null);
const cart = ref([]);
const searchProduct = ref('');
const selectedCategory = ref('Todas');

const categories = computed(() => {
    const cats = props.products.map(p => p.category ? p.category.name : null).filter(Boolean);
    return ['Todas', ...new Set(cats)];
});

// --- VALIDACIONES DE INPUT ---
const validateQuantity = (item) => {
    let val = parseInt(item.quantity);
    if (isNaN(val) || val < 1) item.quantity = 1;
    else item.quantity = val;
};

const validateDiscount = (item) => {
    let val = parseFloat(item.discount_percent);
    if (isNaN(val) || val < 0) val = 0;
    if (val > 50) {
        val = 50;
        Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, icon: 'warning', title: 'Máximo 50% descuento' });
    }
    item.discount_percent = val;
};

// --- CLIENTE MODAL ---
const showClientModal = ref(false);
const isCreatingClient = ref(false);
const newClientForm = ref({ name: '', business_name: '', price_tier: 1, email: '', phones: '', street_address: '', neighborhood: '', city: 'Tepatitlán', state: 'Jalisco', zip_code: '', references: '' });
const formErrors = ref({});

const openClientModal = () => {
    newClientForm.value = { name: '', business_name: '', price_tier: 1, email: '', phones: '', street_address: '', neighborhood: '', city: 'Tepatitlán', state: 'Jalisco', zip_code: '', references: '' };
    formErrors.value = {};
    showClientModal.value = true;
};

const saveNewClient = async () => {
    formErrors.value = {}; 
    if (!newClientForm.value.name) { formErrors.value.name = "Requerido"; return; }
    isCreatingClient.value = true;
    try {
        const response = await axios.post(route('clients.store'), newClientForm.value);
        const newClient = response.data.client;
        clientList.value.unshift(newClient);
        selectedClient.value = newClient; 
        showClientModal.value = false;
        Swal.fire({ icon: 'success', title: 'Cliente Creado', toast: true, position: 'top-end', timer: 3000, showConfirmButton: false });
    } catch (error) {
        if (error.response?.status === 422) {
            const backendErrors = error.response.data.errors;
            for (const key in backendErrors) formErrors.value[key] = backendErrors[key][0];
        }
    } finally {
        isCreatingClient.value = false;
    }
};

// --- LOGICA DE PRECIOS ---
const getPriceForClient = (variant) => {
    if (!selectedClient.value) return 0;
    const tier = selectedClient.value.price_tier || 1;
    return parseFloat(variant[`price_${tier}`]) || parseFloat(variant.price_1);
};

// --- CARRITO ---
const handleAddToCart = (product, variant) => {
    if (!selectedClient.value) {
        Swal.fire({ title: 'Atención', text: 'Selecciona un cliente para ver precios.', icon: 'info', confirmButtonColor: '#16a34a' });
        return;
    }
    const price = getPriceForClient(variant);
    
    cart.value.push({
        variant_id: variant.id, 
        product_name: product.name, 
        // AGREGAMOS ESTO:
        category_name: product.category ? product.category.name : '', 
        
        material: variant.material, 
        sku: variant.sku, 
        image: product.image,
        price: price, 
        quantity: 1, 
        discount_percent: 0,
        chosen_color: '', 
        notes: '', 
        additional_cost: 0
    });
};

const removeFromCart = (index) => cart.value.splice(index, 1);

// --- NUEVO: VACIAR CARRITO CON SWEETALERT ---
const clearCart = () => {
    if (cart.value.length === 0) return;
    Swal.fire({
        title: '¿Limpiar pedido?',
        text: "Se eliminarán todos los productos del carrito.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, vaciar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            cart.value = [];
            Swal.fire({ title: 'Limpio', icon: 'success', timer: 1000, showConfirmButton: false });
        }
    });
};

const cartTotal = computed(() => {
    return cart.value.reduce((total, item) => {
        const unitPrice = parseFloat(item.price);
        const qty = parseInt(item.quantity);
        const discountPct = parseFloat(item.discount_percent) || 0;
        const extra = parseFloat(item.additional_cost) || 0;
        const discountedPrice = unitPrice * (1 - (discountPct / 100));
        return total + (discountedPrice * qty) + extra;
    }, 0);
});

const formatMoney = (amount) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);

// --- FILTROS ---
const filteredProducts = computed(() => {
    let result = props.products;
    if (selectedCategory.value !== 'Todas') {
        result = result.filter(p => p.category?.name === selectedCategory.value);
    }
    if (searchProduct.value) {
        const term = searchProduct.value.toLowerCase();
        result = result.filter(p => p.name.toLowerCase().includes(term) || p.variants.some(v => v.sku?.toLowerCase().includes(term)));
    }
    return result;
});

// --- IMAGEN MODAL ---
const showImageModal = ref(false);
const selectedProductForModal = ref(null);
const openImageModal = (product) => {
    selectedProductForModal.value = product;
    showImageModal.value = true;
};

// --- NUEVO: MODAL DE NOTAS/EXTRAS ---
const showNotesModal = ref(false);
const editingItemIndex = ref(null);
const noteForm = ref({ notes: '', additional_cost: 0 });

const openNotesModal = (index) => {
    editingItemIndex.value = index;
    // Copiamos los valores actuales al formulario temporal
    const item = cart.value[index];
    noteForm.value = { 
        notes: item.notes || '', 
        additional_cost: item.additional_cost || 0 
    };
    showNotesModal.value = true;
};

const saveNotes = () => {
    if (editingItemIndex.value !== null) {
        // 1. Validaciones de Seguridad
        let cost = parseFloat(noteForm.value.additional_cost);
        if (isNaN(cost) || cost < 0) cost = 0;

        // 2. Guardar cambios
        cart.value[editingItemIndex.value].notes = noteForm.value.notes;
        cart.value[editingItemIndex.value].additional_cost = cost;
    }
    showNotesModal.value = false;
};

// --- CHECKOUT ---
const showPaymentModal = ref(false);
const paymentForm = ref({ method: 'Efectivo', amount_received: 0, promised_date: '' });

const openPaymentModal = () => {
    if (cart.value.length === 0) return;
    const missingColor = cart.value.find(i => !i.chosen_color);
    if (missingColor) { 
        Swal.fire({ title: 'Falta Color', text: `Selecciona el color para: ${missingColor.product_name}`, icon: 'warning' }); 
        return; 
    }
    showPaymentModal.value = true;
    const date = new Date(); date.setDate(date.getDate() + 15);
    paymentForm.value.promised_date = date.toISOString().split('T')[0];
    paymentForm.value.amount_received = 0;
    showPaymentModal.value = true;
    setTimeout(() => {
        if (signaturePad.value) {
            signaturePad.value.resizeCanvas(); // Fuerza al recuadro a tomar su tamaño real
        }
    }, 300);
};

const remainingBalance = computed(() => Math.max(0, cartTotal.value - (parseFloat(paymentForm.value.amount_received) || 0)));

const submitOrder = () => {
    // Capturamos la firma justo antes de enviar
    const { isEmpty, data } = signaturePad.value.saveSignature();
    
    if (isEmpty) {
        Swal.fire({ title: 'Falta Firma', text: 'Es obligatorio que el cliente firme para autorizar el pedido.', icon: 'warning' });
        return;
    }

    const payload = {
        client_id: selectedClient.value.id,
        items: cart.value.map(item => ({
            variant_id: item.variant_id, quantity: item.quantity, price: item.price, 
            discount_percent: item.discount_percent, chosen_color: item.chosen_color, 
            notes: item.notes, additional_cost: item.additional_cost
        })),
        payment_method: paymentForm.value.method,
        paid_amount: paymentForm.value.amount_received,
        promised_date: paymentForm.value.promised_date,
        signature: data // <--- ENVIAMOS EL BASE64 DE LA FIRMA
    };

    router.post(route('sales.store'), payload, {
        onSuccess: () => {
            showPaymentModal.value = false; cart.value = []; selectedClient.value = null;
            Swal.fire({ title: 'Pedido Creado', icon: 'success', timer: 2000, showConfirmButton: false });
        },
        onError: (e) => Swal.fire('Error', e.error || 'Revisa los datos', 'error')
    });
};

// Funciones auxiliares para el Pad
const clearSignature = () => signaturePad.value.clearSignature();

</script>

<template>
    <Head title="Punto de Venta" />

    <AuthenticatedLayout>
        <div class="flex flex-col lg:flex-row gap-4 h-[calc(100vh-65px)] overflow-hidden p-3 bg-gray-100">
            
            <div class="flex-1 flex flex-col overflow-hidden gap-3">
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 shrink-0">
                    <div class="flex flex-col md:flex-row gap-4 items-center mb-4">
                        <div class="w-full md:w-1/2 relative z-20">
                            <div class="flex justify-between mb-1">
                                <label class="text-[10px] font-bold text-gray-400 uppercase">Cliente</label>
                                <button @click="openClientModal" class="text-[10px] font-bold text-blue-600 hover:underline">+ Nuevo</button>
                            </div>
                            <ClientAutocomplete :clients="clientList" v-model="selectedClient" />
                        </div>
                        <div class="w-full md:w-1/2 relative">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Buscador</label>
                            <input v-model="searchProduct" type="text" placeholder="Nombre, SKU..." class="w-full pl-9 pr-4 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-green-500">
                            <svg class="w-4 h-4 text-gray-400 absolute left-3 bottom-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            <button v-if="searchProduct" @click="searchProduct=''" class="absolute right-2 bottom-2 text-gray-400 font-bold hover:text-red-500">✕</button>
                        </div>
                    </div>
                    <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-hide pt-2 border-t border-gray-100">
                        <button v-for="cat in categories" :key="cat" @click="selectedCategory = cat" :class="selectedCategory === cat ? 'bg-green-600 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="whitespace-nowrap px-4 py-1.5 rounded-full text-sm font-bold transition-all">{{ cat }}</button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto pr-2 pb-10">
                    <div v-if="!selectedClient" class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg mb-4 flex items-center gap-3">
                        <svg class="w-6 h-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="font-bold text-sm">Selecciona un cliente para ver precios.</span>
                    </div>
                    <div v-if="filteredProducts.length === 0" class="text-center py-20 text-gray-400"><p>No se encontraron productos.</p></div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <div v-for="product in filteredProducts" :key="product.id" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col group">
                            <div class="h-40 bg-gray-50 relative cursor-pointer overflow-hidden" @click="openImageModal(product)">
                                <img v-if="product.image" :src="`/storage/${product.image}`" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                <div v-else class="w-full h-full flex items-center justify-center text-gray-300"><svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>
                                <div v-if="product.is_favorite" class="absolute top-2 right-2 bg-white p-1 rounded-full shadow text-yellow-400"><svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg></div>
                            </div>
                            <div class="p-3 flex-1 flex flex-col">
                                <div class="mb-2">
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">{{ product.category?.name }}</p>
                                    <h3 class="font-bold text-gray-800 text-sm leading-tight line-clamp-2">{{ product.name }}</h3>
                                    <p class="text-xs text-gray-500 mt-1">{{ product.measurements }}</p>
                                </div>
                                <div class="mt-auto space-y-1.5">
                                    <button v-for="variant in product.variants" :key="variant.id" @click="handleAddToCart(product, variant)" :disabled="!selectedClient" :class="!selectedClient ? 'opacity-50 cursor-not-allowed bg-gray-50' : 'bg-gray-50 hover:bg-green-600 hover:text-white'" class="w-full flex justify-between items-center px-3 py-2 border border-gray-200 rounded-lg transition-colors group/btn">
                                        <span class="text-xs font-bold">{{ variant.material }}</span>
                                        <span class="text-xs font-black group-hover:text-white">{{ selectedClient ? formatMoney(getPriceForClient(variant)) : '---' }}</span>
                                        <span v-if="selectedClient" class="bg-white text-green-600 rounded-full w-5 h-5 flex items-center justify-center text-xs shadow opacity-0 group-hover:group-hover/btn:opacity-100 transition-opacity">+</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-full lg:w-96 bg-white shadow-xl rounded-xl border border-gray-200 flex flex-col overflow-hidden shrink-0 h-full">
                <div class="p-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-base font-bold text-gray-800 flex items-center gap-2"><svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>Ticket de Pedido</h2>
                    <button v-if="cart.length > 0" @click="clearCart" class="text-xs text-red-500 hover:underline font-bold">Vaciar</button>
                </div>

                <div class="flex-1 overflow-y-auto p-2 space-y-2 bg-gray-50/30">
                    <div v-if="cart.length === 0" class="h-full flex flex-col items-center justify-center text-gray-400 opacity-60">
                        <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <p class="text-xs font-medium">Carrito vacío</p>
                    </div>

                    <div v-for="(item, index) in cart" :key="index" class="bg-white p-2 rounded-lg shadow-sm border border-gray-200 relative group hover:shadow-md transition-shadow">
                        <button @click="removeFromCart(index)" class="absolute top-1.5 right-1.5 text-gray-300 hover:text-red-500 font-bold leading-none text-base z-10">&times;</button>
                        
                        <div class="pr-6 mb-2 flex items-start gap-1.5">
                            <h4 class="font-bold text-gray-800 text-xs leading-tight line-clamp-2">{{ item.product_name }}</h4>
                            <span v-if="item.category_name" class="text-[9px] bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded border border-blue-100 font-bold uppercase tracking-wider">{{ item.category_name }}</span>
                            <span class="text-[9px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded border border-gray-200 font-bold uppercase tracking-wider">{{ item.material }}</span>
                        </div>

                        <div class="flex items-end justify-between gap-2 mb-2 border-t border-gray-50 pt-2">
                            <div class="flex-1 min-w-0">
                                <label class="block text-[8px] font-bold text-gray-400 uppercase mb-1 truncate">Color: <span class="text-green-600 font-semibold">{{ item.chosen_color || '...' }}</span></label>
                                <div class="flex flex-wrap gap-1">
                                    <button v-for="color in getColorsForMaterial(item.material)" :key="color.name" @click="item.chosen_color = color.name" :title="color.name" class="w-5 h-5 rounded-full border shadow-sm transition-transform hover:scale-110 relative" :class="{'ring-1 ring-green-500 ring-offset-1 scale-110': item.chosen_color === color.name, 'border-gray-300': color.border}" :style="{ backgroundColor: color.hex }"></button>
                                </div>
                            </div>
                            <div class="flex bg-gray-50 p-1 rounded-lg border border-gray-200 shrink-0 h-10 items-center">
                                <div class="px-1 border-r border-gray-200">
                                    <label class="block text-[7px] font-bold text-gray-400 uppercase text-center mb-0.5">Cant</label>
                                    <input v-model.number="item.quantity" @input="validateQuantity(item)" type="number" min="1" class="w-10 h-5 text-xs bg-transparent border-none p-0 text-center font-bold focus:ring-0 text-gray-800">
                                </div>
                                <div class="px-1">
                                    <label class="block text-[7px] font-bold text-gray-400 uppercase text-center mb-0.5">Desc%</label>
                                    <input v-model.number="item.discount_percent" @input="validateDiscount(item)" type="number" min="0" max="50" class="w-10 h-5 text-xs bg-transparent border-none p-0 text-center font-bold focus:ring-0 text-red-500 placeholder-gray-300" placeholder="0">
                                </div>
                            </div>
                        </div>

                        <div class="mb-1">
                            <button @click="openNotesModal(index)" class="w-full flex items-center justify-center gap-1 py-1.5 rounded text-[10px] font-bold transition-colors border border-dashed" :class="(item.notes || item.additional_cost > 0) ? 'bg-blue-50 text-blue-600 border-blue-200 hover:bg-blue-100' : 'bg-gray-50 text-gray-500 border-gray-300 hover:bg-gray-100'">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                {{ (item.notes || item.additional_cost > 0) ? 'Editar Detalles' : 'Agregar Notas / Extras' }}
                            </button>
                            
                            <div v-if="item.notes || item.additional_cost > 0" class="mt-1.5 p-1.5 bg-blue-50 rounded border-l-2 border-blue-400 text-[9px] text-blue-800">
                                <p v-if="item.notes" class="leading-tight line-clamp-2">📝 {{ item.notes }}</p>
                                <p v-if="item.additional_cost > 0" class="font-bold mt-0.5">💰 Adicional: {{ formatMoney(item.additional_cost) }}</p>
                            </div>
                        </div>

                        <div class="pt-1.5 border-t border-gray-100 text-right flex flex-col items-end">
                            <div v-if="item.discount_percent > 0" class="text-[9px] text-green-600 font-medium flex items-center gap-1 bg-green-50 px-1.5 py-0.5 rounded mb-0.5">
                                <span>Ahorras: {{ formatMoney((item.price * (item.discount_percent/100)) * item.quantity) }}</span>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span v-if="item.discount_percent > 0" class="text-xs text-gray-400 line-through decoration-red-300">
                                    {{ formatMoney((item.price * item.quantity) + (parseFloat(item.additional_cost)||0)) }}
                                </span>
                                <p class="font-bold text-gray-900 text-sm leading-none">
                                    {{ formatMoney( ((item.price * (1 - (item.discount_percent/100))) * item.quantity) + (parseFloat(item.additional_cost)||0) ) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-white border-t border-gray-200 shadow-up z-10">
                    <div class="flex justify-between items-end mb-3">
                        <span class="text-sm text-gray-500 font-medium">Total Estimado</span>
                        <span class="text-2xl font-bold text-gray-900">{{ formatMoney(cartTotal) }}</span>
                    </div>
                    <button @click="openPaymentModal" :disabled="cart.length === 0 || !selectedClient" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3.5 rounded-xl shadow-lg transition-all disabled:opacity-50 flex justify-center items-center gap-2">
                        <span>GENERAR PEDIDO</span>
                    </button>
                </div>
            </div>
        </div>

        <Modal :show="showNotesModal" @close="showNotesModal = false" maxWidth="sm">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <span class="bg-blue-100 text-blue-600 p-1 rounded"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></span>
                    Detalles de Producción
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Notas / Especificaciones</label>
                        <textarea v-model="noteForm.notes" rows="3" placeholder="Ej: Jaladeras doradas, Vidrio esmerilado, Altura especial..." class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Costo Extra ($)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-gray-500 font-bold">$</span>
                            <input v-model="noteForm.additional_cost" type="number" min="0" class="w-full pl-7 border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 font-bold text-gray-800">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Este monto se sumará al total del producto.</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button @click="showNotesModal = false" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold hover:bg-gray-50">Cancelar</button>
                    <button @click="saveNotes" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-bold hover:bg-blue-700 shadow-lg">Guardar Detalles</button>
                </div>
            </div>
        </Modal>

        <Modal :show="showPaymentModal" @close="showPaymentModal = false" maxWidth="md">
            <div class="bg-white rounded-2xl overflow-hidden shadow-xl transform transition-all">
                
                <div class="bg-green-600 p-6 text-white text-center relative rounded-t-2xl">
                    <button @click="showPaymentModal=false" class="absolute top-4 right-4 text-green-200 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                    
                    <h3 class="text-lg font-medium opacity-90 mb-1">Total a Pagar</h3>
                    <p class="text-4xl font-bold tracking-tight">{{ formatMoney(cartTotal) }}</p>
                    
                    <div class="mt-3 inline-block bg-green-700 bg-opacity-50 px-3 py-1 rounded-full text-sm font-medium border border-green-500">
                        Cliente: {{ selectedClient?.name || 'Público General' }}
                    </div>
                </div>

                <div class="p-6 bg-white rounded-b-2xl">
                    
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Método de Pago</label>
                    <div class="grid grid-cols-3 gap-3 mb-5">
                        <button @click="paymentForm.method = 'Efectivo'" 
                            :class="paymentForm.method === 'Efectivo' ? 'bg-green-50 border-green-500 text-green-700 ring-1 ring-green-500' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                            class="border rounded-xl py-3 flex flex-col items-center gap-1 transition-all duration-200 group">
                            <svg class="w-6 h-6 mb-1" :class="paymentForm.method === 'Efectivo' ? 'text-green-600' : 'text-gray-400 group-hover:text-gray-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            <span class="text-xs font-bold">Efectivo</span>
                        </button>
                        
                        <button @click="paymentForm.method = 'Tarjeta'" 
                            :class="paymentForm.method === 'Tarjeta' ? 'bg-blue-50 border-blue-500 text-blue-700 ring-1 ring-blue-500' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                            class="border rounded-xl py-3 flex flex-col items-center gap-1 transition-all duration-200 group">
                            <svg class="w-6 h-6 mb-1" :class="paymentForm.method === 'Tarjeta' ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                            <span class="text-xs font-bold">Tarjeta</span>
                        </button>

                        <button @click="paymentForm.method = 'Transferencia'" 
                            :class="paymentForm.method === 'Transferencia' ? 'bg-purple-50 border-purple-500 text-purple-700 ring-1 ring-purple-500' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                            class="border rounded-xl py-3 flex flex-col items-center gap-1 transition-all duration-200 group">
                            <svg class="w-6 h-6 mb-1" :class="paymentForm.method === 'Transferencia' ? 'text-purple-600' : 'text-gray-400 group-hover:text-gray-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                            <span class="text-xs font-bold">Transf.</span>
                        </button>
                    </div>

                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">
                        {{ paymentForm.method === 'Efectivo' ? 'Monto Recibido (Anticipo)' : 'Monto a Cargar' }}
                    </label>
                    <div class="relative mb-4">
                        <span class="absolute left-4 top-3 text-gray-400 text-xl font-bold">$</span>
                        <input 
                            v-model.number="paymentForm.amount_received" 
                            type="number" 
                            class="w-full pl-9 pr-4 py-3 text-3xl font-bold text-gray-800 border border-gray-300 rounded-xl focus:ring-green-500 focus:border-green-500 transition-shadow shadow-sm placeholder-gray-300"
                            placeholder="0.00"
                            @focus="$event.target.select()"
                        >
                    </div>

                    <div class="bg-gray-50 rounded-xl p-4 flex justify-between items-center mb-5 border border-gray-100">
                        <span class="font-bold text-gray-500 text-sm">
                            {{ remainingBalance > 0 ? 'Resta por Pagar:' : 'Cambio:' }}
                        </span>
                        <span class="text-2xl font-bold" :class="remainingBalance > 0 ? 'text-red-500' : 'text-green-600'">
                            {{ formatMoney(Math.abs(remainingBalance > 0 ? remainingBalance : (paymentForm.amount_received - cartTotal))) }}
                        </span>
                    </div>

                    <details class="group mb-6">
                        <summary class="flex items-center gap-2 cursor-pointer text-xs font-bold text-gray-500 hover:text-green-600 select-none transition-colors">
                            <span class="bg-gray-100 p-1 rounded group-open:bg-green-100 group-open:text-green-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </span>
                            <span>Definir Fecha de Entrega (Opcional)</span>
                            <svg class="w-3 h-3 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </summary>
                        
                        <div class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-100 animate-fade-in-down">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Fecha Promesa</label>
                            <input v-model="paymentForm.promised_date" type="date" class="w-full text-sm border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 text-gray-700">
                            <p class="text-[10px] text-gray-400 mt-1">Si se deja vacío, se coordinará después con el cliente.</p>
                        </div>
                    </details>

                    <div class="mt-6 mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">
                                Firma de Autorización del Cliente
                            </label>
                            <button type="button" @click="clearSignature" class="text-[10px] text-red-500 font-bold hover:underline">
                                Limpiar Firma
                            </button>
                        </div>
                        
                        <div class="border-2 border-dashed border-gray-200 rounded-xl bg-gray-50 overflow-hidden relative group">
                            <VueSignaturePad
                                width="100%"
                                height="180px"
                                ref="signaturePad"
                                class="cursor-crosshair"
                                :options="{ 
                                    penColor: '#1a202c',
                                    backgroundColor: 'rgba(0,0,0,0)'
                                }"
                            />
                            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 pointer-events-none opacity-20 group-focus-within:opacity-0 transition-opacity">
                                <p class="text-[10px] text-gray-400 font-medium border-t border-gray-300 pt-1 px-4">X _______________________</p>
                            </div>
                        </div>
                        <p class="text-[9px] text-gray-400 mt-2 italic text-center">
                            Al firmar, el cliente acepta el diseño, materiales y fecha estimada de entrega.
                        </p>
                    </div>

                    <div class="flex gap-3">
                        <button 
                            @click="showPaymentModal = false" 
                            class="flex-1 py-3.5 border border-gray-300 rounded-xl font-bold text-gray-600 hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button 
                            @click="submitOrder" 
                            class="flex-1 py-3.5 bg-green-600 rounded-xl font-bold text-white hover:bg-green-700 shadow-lg shadow-green-200 transition-all flex justify-center items-center gap-2"
                        >
                            <span>Confirmar Pedido</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </button>
                    </div>

                </div>
            </div>
        </Modal>

        <Modal :show="showClientModal" @close="showClientModal = false">
             <div class="p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Nuevo Cliente</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                    <input v-model="newClientForm.name" placeholder="Nombre *" class="w-full text-sm border-gray-300 rounded">
                    <input v-model="newClientForm.phones" placeholder="Teléfono" class="w-full text-sm border-gray-300 rounded">
                    <input v-model="newClientForm.email" placeholder="Email" class="w-full text-sm border-gray-300 rounded">
                    <input v-model="newClientForm.street_address" placeholder="Dirección" class="w-full text-sm border-gray-300 rounded col-span-2">
                </div>
                <div class="flex justify-end gap-2">
                    <button @click="showClientModal=false" class="px-4 py-2 border rounded">Cancelar</button>
                    <button @click="saveNewClient" :disabled="isCreatingClient" class="px-4 py-2 bg-blue-600 text-white rounded font-bold">Guardar</button>
                </div>
             </div>
        </Modal>

        <div v-if="showImageModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4 transition-opacity" @click.self="showImageModal=false">
            <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full overflow-hidden relative flex flex-col md:flex-row animate-fade-in-up">
                
                <button @click="showImageModal=false" class="absolute top-4 right-4 z-10 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>

                <div class="w-full md:w-1/2 bg-gray-100 flex items-center justify-center relative min-h-[300px] md:min-h-[450px]">
                    <img v-if="selectedProductForModal?.image" 
                         :src="`/storage/${selectedProductForModal.image}`" 
                         class="w-full h-full object-contain max-h-[500px] mix-blend-multiply p-6 transition-transform hover:scale-105 duration-700">
                    
                    <div v-else class="flex flex-col items-center text-gray-300">
                        <svg class="w-20 h-20 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span class="font-medium text-sm">Sin imagen</span>
                    </div>
                </div>

                <div class="w-full md:w-1/2 p-8 flex flex-col justify-center bg-white">
                    
                    <h4 class="text-green-600 font-bold tracking-wider uppercase text-xs mb-2">
                        {{ selectedProductForModal?.category?.name || 'General' }}
                    </h4>

                    <h2 class="text-3xl font-extrabold text-gray-900 mb-2 leading-tight">
                        {{ selectedProductForModal?.name }}
                    </h2>

                    <p class="text-gray-500 font-medium text-lg mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg>
                        {{ selectedProductForModal?.measurements || 'Medidas no especificadas' }}
                    </p>

                    <div class="bg-gray-50 p-6 rounded-2xl border border-gray-100">
                        <h3 class="font-bold text-gray-800 mb-2 text-sm">Descripción:</h3>
                        <p class="text-gray-600 text-sm leading-relaxed whitespace-pre-line">
                            {{ selectedProductForModal?.description || 'No hay descripción disponible para este modelo.' }}
                        </p>
                    </div>

                    <div class="mt-8 md:hidden">
                        <button @click="showImageModal=false" class="w-full py-3 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200">
                            Cerrar
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>