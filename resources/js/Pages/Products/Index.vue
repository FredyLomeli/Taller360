<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import ClientAutocomplete from '@/Components/ClientAutocomplete.vue';
import Modal from '@/Components/Modal.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    products: Array,
    clients: Array,
    categories: Array
});

const showImageModal = ref(false);
const selectedProductForModal = ref(null);

const openImageModal = (product) => {
    selectedProductForModal.value = product;
    showImageModal.value = true;
};

// --- ESTADO DEL CARRITO ---
const cart = ref([]);
const selectedClient = ref(null);
const searchProduct = ref(''); // Variable para el buscador
const selectedCategory = ref('Todas'); // Variable para la categoría

// 1. Modificar handleAddToCart para guardar el precio original
const handleAddToCart = (product) => {
    const existingItem = cart.value.find(item => item.variant_id === product.variant_id);

    if (existingItem) {
        existingItem.quantity++;
    } else {
        cart.value.push({
            variant_id: product.variant_id,
            product_name: product.product_name,
            material: product.material,
            color: product.color,
            sku: product.sku,
            quantity: 1,
            
            // --- CAMBIOS PARA DESCUENTOS ---
            original_price: product.price, // Guardamos el precio base
            price: product.price,          // Este será el precio final (con descuento)
            discount_percent: 0            // Iniciamos en 0%
        });
    }
};

// 2. Nueva función para calcular descuento en tiempo real
const updateDiscount = (index, percent) => {
    const item = cart.value[index];
    
    // Validamos que sea entre 0 y 100
    let p = parseFloat(percent);
    if (isNaN(p) || p < 0) p = 0;
    if (p > 100) p = 100;

    item.discount_percent = p;
    
    // Cálculo: Precio Original - (Precio Original * Porcentaje / 100)
    const discountAmount = item.original_price * (p / 100);
    item.price = item.original_price - discountAmount;
};

// --- FUNCIONES DEL CARRITO ---

const updateQuantity = (index, newQuantity) => {
    // 1. Si la cantidad es 0 o menor, preguntamos si quiere borrar
    if (newQuantity <= 0) {
        if (confirm("¿Deseas eliminar este producto del carrito?")) {
            cart.value.splice(index, 1); // Elimina el item
        }
        return;
    }

    // 2. Validación de Stock (Opcional pero recomendada)
    // Buscamos el producto original para saber su límite
    const itemInCart = cart.value[index];
    // Nota: Aquí necesitaríamos lógica más compleja para buscar el stock real en 'products',
    // pero por ahora permitiremos subir la cantidad libremente o limitarlo si tienes el dato a mano.
    
    // Actualizamos la cantidad
    cart.value[index].quantity = newQuantity;
};

const clearCart = () => {
    if (cart.value.length > 0 && confirm("¿Estás seguro de vaciar el carrito?")) {
        cart.value = [];
    }
};

// Función para quitar items
const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

// Computada para el Total $$
const cartTotal = computed(() => {
    return cart.value.reduce((total, item) => total + (item.price * item.quantity), 0);
});

const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};

// Función para procesar la venta
const submitSale = () => {
    if (cart.value.length === 0) return;
    

    if (!selectedClient.value) {
        alert("Por favor selecciona un cliente antes de cobrar.");
        return;
    }


    if (confirm(`¿Confirmar venta por ${formatMoney(cartTotal.value)}?`)) {
        router.post(route('sales.store'), {
            client_id: selectedClient.value ? selectedClient.value.id : null,
            cart: cart.value
        }, {
            onSuccess: () => {
                // Limpiar carrito si todo salió bien
                cart.value = [];
                // Opcional: Resetear cliente
                // selectedClient.value = null; 
                alert('Venta registrada correctamente.');
            },
            onError: (errors) => {
                // Mostrar error si falta stock u otro problema
                if (errors.error) alert(errors.error);
            }
        });
    }
};

const filteredProducts = computed(() => {
    let result = props.products;

    // 1. Filtro por Categoría
    if (selectedCategory.value !== 'Todas') {
        result = result.filter(p => p.category.name === selectedCategory.value);
    }

    // 2. Filtro por Buscador (Nombre o SKU de variantes)
    if (searchProduct.value) {
        const term = searchProduct.value.toLowerCase();
        result = result.filter(p => 
            p.name.toLowerCase().includes(term) || // Buscar por nombre producto
            p.variants.some(v => v.sku && v.sku.toLowerCase().includes(term)) // Buscar por SKU
        );
    }

    return result;
});

// --- ESTADO DEL PAGO ---
const showPaymentModal = ref(false);
const paymentForm = ref({
    method: 'Efectivo', // Efectivo, Tarjeta, Transferencia
    amount_received: 0, // Lo que paga el cliente
    is_credit: false    // Si es crédito puro
});

// Al abrir el modal, pre-llenamos el monto con el total
const openPaymentModal = () => {
    if (cart.value.length === 0) return;
    if (!selectedClient.value) {
        alert("Selecciona un cliente para continuar.");
        return;
    }
    
    paymentForm.value.amount_received = cartTotal.value; // Por defecto paga exacto
    paymentForm.value.method = 'Efectivo';
    showPaymentModal.value = true;
};

// Cálculo del Cambio
const changeAmount = computed(() => {
    const received = parseFloat(paymentForm.value.amount_received) || 0;
    const total = cartTotal.value;
    return received - total;
});

// Color del cambio (Rojo si falta dinero/crédito, Verde si sobra/cambio)
const changeColor = computed(() => {
    return changeAmount.value < 0 ? 'text-red-600' : 'text-green-600';
});

const processSale = () => {
    // A. Lógica para Crédito (Si falta dinero)
    if (changeAmount.value < 0) {
        // Usamos SweetAlert en lugar de confirm()
        Swal.fire({
            title: '¿Venta a Crédito?',
            text: `El monto recibido es menor al total. Faltan ${formatMoney(Math.abs(changeAmount.value))}. ¿Deseas registrarlo como saldo pendiente?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a', // Verde
            cancelButtonColor: '#d33',     // Rojo
            confirmButtonText: 'Sí, registrar crédito',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                sendSaleRequest(); // Si dice que SÍ, enviamos la venta
            }
        });
        return; // Detenemos aquí esperando la respuesta del usuario
    }

    // B. Si el pago es completo, enviamos directo
    sendSaleRequest();
};

// Función auxiliar para enviar la petición al backend
const sendSaleRequest = () => {
    router.post(route('sales.store'), {
        client_id: selectedClient.value.id,
        cart: cart.value,
        payment_method: paymentForm.value.method,
        amount_received: paymentForm.value.amount_received
    }, {
        onSuccess: (page) => { // Recibimos 'page' para ver datos si es necesario
            showPaymentModal.value = false;
            cart.value = [];
            
            // Obtenemos el ID de la última venta creada (Laravel puede devolverlo en flash o props)
            // TRUCO: Como Inertia no devuelve fácil el ID en onSuccess sin recargar, 
            // lo más fácil es redirigir al historial o simplemente mostrar éxito genérico.
            
            // PERO, para hacerlo PRO, vamos a asumir que la venta salió bien.
            // Si necesitas el ID exacto, el backend debería devolverlo en la sesión.
            // Por ahora, pondremos un botón para ir al Historial donde sí está el ID.

            Swal.fire({
                title: '¡Venta Exitosa!',
                text: '¿Deseas imprimir el ticket ahora?',
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ir a Imprimir',
                cancelButtonText: 'Cerrar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirigimos al historial de ventas
                    router.get(route('sales.index'));
                }
            });
        },
        onError: (errors) => {
            // ALERTA DE ERROR
            let msg = "Ocurrió un error al procesar la venta.";
            if (errors.error) msg = errors.error; // Mensaje desde el backend (ej: Sin stock)
            
            Swal.fire({
                title: 'Error',
                text: msg,
                icon: 'error',
                confirmButtonColor: '#d33'
            });
        }
    });
};
</script>

<template>
    <Head title="Venta" />

    <AuthenticatedLayout>
        <div class="p-4 flex flex-col lg:flex-row gap-4 overflow-hidden" style="height: calc(100vh - 5rem);">
            
            <div class="flex-1 flex flex-col overflow-hidden">
                
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-4 shrink-0">
                    <div class="flex flex-col md:flex-row gap-4 items-start">
                        
                        <div class="w-full md:w-1/2">
                            <ClientAutocomplete 
                                :clients="clients" 
                                v-model="selectedClient" 
                            />
                        </div>

                        <div class="w-full md:w-1/2">
                             <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Buscar Producto (Nombre o SKU)</label>
                             <div class="relative">
                                <input 
                                    v-model="searchProduct" 
                                    type="text" 
                                    placeholder="Ej: Ropero, SKU-123..." 
                                    class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 text-sm w-full"
                                >
                                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                <button v-if="searchProduct" @click="searchProduct = ''" class="absolute right-2 top-2 text-gray-400 hover:text-red-500">X</button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 overflow-x-auto pb-2 -mx-2 px-2 flex gap-2 scrollbar-hide">
                        <button 
                            @click="selectedCategory = 'Todas'"
                            :class="selectedCategory === 'Todas' ? 'bg-green-600 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="whitespace-nowrap px-4 py-2 rounded-full text-sm font-bold transition-all"
                        >
                            Todas
                        </button>
                        
                        <button 
                            v-for="cat in categories" 
                            :key="cat.id"
                            @click="selectedCategory = cat.name"
                            :class="selectedCategory === cat.name ? 'bg-green-600 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="whitespace-nowrap px-4 py-2 rounded-full text-sm font-bold transition-all"
                        >
                            {{ cat.name }}
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto pr-2 pb-20">
                    <div v-if="filteredProducts.length === 0" class="text-center py-20 text-gray-400">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p>No se encontraron productos con esos filtros.</p>
                        <button @click="selectedCategory='Todas'; searchProduct=''" class="mt-2 text-green-600 font-bold underline">Limpiar filtros</button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <ProductCard 
                            v-for="product in filteredProducts" 
                            :key="product.id" 
                            :product="product"
                            :price-tier="selectedClient ? Number(selectedClient.price_tier) : null" 
                            @add-to-cart="handleAddToCart" 
                            @open-modal="openImageModal" />
                        
                        <div v-if="showImageModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75 backdrop-blur-sm p-4" @click.self="showImageModal = false">
                            <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full overflow-hidden relative">
                                
                                <button @click="showImageModal = false" class="absolute top-2 right-2 text-gray-500 hover:text-gray-800 bg-white rounded-full p-1 z-10">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>

                                <div class="flex flex-col md:flex-row h-full max-h-[80vh]">
                                    <div class="md:w-1/2 bg-gray-100 flex items-center justify-center">
                                        <img v-if="selectedProductForModal?.image" :src="`/storage/${selectedProductForModal.image}`" class="w-full h-full object-contain">
                                        <div v-else class="p-10 text-gray-400 flex flex-col items-center">
                                            <svg class="w-20 h-20 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            <span>Sin imagen</span>
                                        </div>
                                    </div>

                                    <div class="md:w-1/2 p-6 flex flex-col overflow-y-auto">
                                        <h3 class="text-sm font-bold text-green-600 uppercase mb-1">{{ selectedProductForModal?.category.name }}</h3>
                                        <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ selectedProductForModal?.name }}</h2>
                                        <p class="text-sm text-gray-500 mb-4 font-medium">{{ selectedProductForModal?.measurements }}</p>
                                        
                                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 mb-4 flex-1">
                                            <h4 class="font-bold text-gray-700 mb-2">Descripción:</h4>
                                            <p class="text-gray-600 leading-relaxed whitespace-pre-line">
                                                {{ selectedProductForModal?.description || 'No hay descripción disponible.' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-full lg:w-96 bg-white shadow-xl rounded-xl flex flex-col border border-gray-200 h-full overflow-hidden shrink-0">
    
                <div class="p-4 border-b border-gray-200 bg-gray-50 rounded-t-xl flex justify-between items-center shrink-0">
                    <h2 class="text-lg font-bold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Ticket de Venta
                    </h2>
                    <button v-if="cart.length > 0" @click="clearCart" class="text-xs text-red-600 hover:underline">Limpiar</button>
                </div>

                <div class="flex-1 overflow-y-auto p-4 min-h-0 scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-transparent">
                    
                    <div v-if="cart.length === 0" class="h-full flex flex-col items-center justify-center text-gray-400">
                        <svg class="w-16 h-16 mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        <p class="font-medium">Carrito vacío</p>
                        <p class="text-sm">Selecciona productos</p>
                    </div>
                    
                    <div v-else class="space-y-3">
                        <div v-for="(item, index) in cart" :key="index" class="flex flex-col pb-3 border-b border-gray-100">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1 pr-2">
                                    <p class="text-sm font-bold text-gray-800 leading-tight">{{ item.product_name }}</p>
                                    <p class="text-xs text-gray-500">{{ item.material }} - {{ item.color }}</p>
                                    <p v-if="item.sku" class="text-[10px] text-gray-400">SKU: {{ item.sku }}</p>
                                </div>
                                <div class="text-right flex flex-col items-end">
                                    <p class="font-bold text-gray-900 text-base">{{ formatMoney(item.price * item.quantity) }}</p>
                                    <div v-if="item.discount_percent > 0">
                                        <p class="text-xs text-red-400 line-through">{{ formatMoney(item.original_price * item.quantity) }}</p>
                                        <span class="bg-green-100 text-green-700 text-[10px] font-bold px-1.5 py-0.5 rounded mt-0.5">
                                            -{{ item.discount_percent }}%
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center mt-1 bg-gray-50 p-1 rounded-lg">
                                <div class="flex items-center bg-white border border-gray-200 rounded px-1 shadow-sm h-8">
                                    <button @click="updateQuantity(index, item.quantity - 1)" class="text-gray-500 hover:text-red-600 px-2 font-bold">-</button>
                                    <span class="mx-1 text-sm font-bold min-w-[20px] text-center">{{ item.quantity }}</span>
                                    <button @click="updateQuantity(index, item.quantity + 1)" class="text-gray-500 hover:text-green-600 px-2 font-bold">+</button>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase">Desc %</label>
                                    <input type="number" min="0" max="100" :value="item.discount_percent" @input="updateDiscount(index, $event.target.value)" @focus="$event.target.select()" class="w-16 h-8 text-sm font-bold text-center border border-gray-300 rounded focus:ring-green-500 m-0">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 border-t border-gray-200 rounded-b-xl shrink-0 z-10">
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-gray-500 font-medium">Total</span>
                        <span class="text-2xl font-bold text-gray-900">{{ formatMoney(cartTotal) }}</span>
                    </div>
                    
                    <button 
                        @click="openPaymentModal"
                        :disabled="cart.length === 0 || !selectedClient"
                        :class="{'opacity-50 cursor-not-allowed': cart.length === 0 || !selectedClient}"
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg shadow-lg transition-colors"
                    >
                        COBRAR TICKET
                    </button>
                    <p v-if="cart.length > 0 && !selectedClient" class="text-xs text-red-500 text-center mt-2 font-bold">
                        Selecciona un cliente para cobrar
                    </p>
                </div>
            </div>
        </div>

        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
                
                <div class="bg-green-600 p-6 text-white text-center relative">
                    <button @click="showPaymentModal = false" class="absolute top-4 right-4 text-green-200 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>

                    <h3 class="text-lg font-medium opacity-90">Total a Pagar</h3>
                    <p class="text-4xl font-bold mt-1 mb-2">{{ formatMoney(cartTotal) }}</p>
                    
                    <div class="inline-block bg-green-700 bg-opacity-50 px-3 py-1 rounded-full text-sm font-medium border border-green-500">
                        Cliente: {{ selectedClient?.name || 'Público General' }}
                    </div>
                </div>

                <div class="p-6">
                    
                    <label class="block text-sm font-bold text-gray-700 mb-2">Método de Pago</label>
                    <div class="grid grid-cols-3 gap-3 mb-6">
                        <button 
                            @click="paymentForm.method = 'Efectivo'"
                            :class="paymentForm.method === 'Efectivo' ? 'bg-green-100 border-green-500 text-green-700 ring-2 ring-green-500' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                            class="border rounded-xl py-3 font-bold flex flex-col items-center gap-1 transition-all"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Efectivo
                        </button>
                        
                        <button 
                            @click="paymentForm.method = 'Tarjeta'; paymentForm.amount_received = cartTotal" 
                            :class="paymentForm.method === 'Tarjeta' ? 'bg-blue-100 border-blue-500 text-blue-700 ring-2 ring-blue-500' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                            class="border rounded-xl py-3 font-bold flex flex-col items-center gap-1 transition-all"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                            Tarjeta
                        </button>

                        <button 
                            @click="paymentForm.method = 'Transferencia'; paymentForm.amount_received = cartTotal"
                            :class="paymentForm.method === 'Transferencia' ? 'bg-purple-100 border-purple-500 text-purple-700 ring-2 ring-purple-500' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                            class="border rounded-xl py-3 font-bold flex flex-col items-center gap-1 transition-all"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                            Transf.
                        </button>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            {{ paymentForm.method === 'Efectivo' ? 'Dinero Recibido' : 'Monto a Cobrar' }}
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-3 text-gray-400 text-lg">$</span>
                            <input 
                                type="number" 
                                v-model="paymentForm.amount_received"
                                class="w-full pl-8 pr-4 py-3 text-2xl font-bold text-gray-800 border-gray-300 rounded-xl focus:ring-green-500 focus:border-green-500"
                                @focus="$event.target.select()"
                            >
                        </div>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-4 flex justify-between items-center mb-6 border border-gray-100">
                        <span class="font-bold text-gray-500">
                            {{ changeAmount >= 0 ? 'Cambio a Entregar:' : 'Saldo Pendiente (Crédito):' }}
                        </span>
                        <span class="text-2xl font-bold" :class="changeColor">
                            {{ formatMoney(Math.abs(changeAmount)) }}
                        </span>
                    </div>

                    <div class="flex gap-3">
                        <button 
                            @click="showPaymentModal = false" 
                            class="flex-1 py-3 border border-gray-300 rounded-xl font-bold text-gray-600 hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button 
                            @click="processSale" 
                            class="flex-1 py-3 bg-green-600 rounded-xl font-bold text-white hover:bg-green-700 shadow-lg transition-colors flex justify-center items-center gap-2"
                        >
                            <span>Confirmar Venta</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
