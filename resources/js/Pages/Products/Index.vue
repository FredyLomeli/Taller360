<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue'; 
import Swal from 'sweetalert2';

const props = defineProps({
    products: Array 
});

const search = ref('');

// --- PAGINACIÓN LOCAL ---
const itemsPerPage = ref(10);
const currentPage = ref(1);

const filteredProducts = computed(() => {
    if (!search.value) return props.products;
    const term = search.value.toLowerCase();
    
    return props.products.filter(p => 
        p.name.toLowerCase().includes(term) ||
        (p.category && p.category.name.toLowerCase().includes(term)) ||
        p.variants.some(v => (v.sku && v.sku.toLowerCase().includes(term)) || (v.measurements && v.measurements.toLowerCase().includes(term)))
    );
});

watch(search, () => { currentPage.value = 1; });
watch(itemsPerPage, () => { currentPage.value = 1; });

const paginatedProducts = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredProducts.value.slice(start, end);
});

const totalPages = computed(() => {
    return Math.ceil(filteredProducts.value.length / itemsPerPage.value);
});

const nextPage = () => {
    if (currentPage.value < totalPages.value) currentPage.value++;
};

const prevPage = () => {
    if (currentPage.value > 1) currentPage.value--;
};

// --- UTILIDADES ---
const getTotalStock = (variants) => {
    return variants.reduce((acc, variant) => acc + variant.stock, 0);
};

const getTotalDamaged = (variants) => {
    return variants.reduce((acc, variant) => acc + (variant.damaged_stock || 0), 0);
};

const getTotalAvailable = (variants) => {
    return variants.reduce((acc, variant) => {
        const avail = variant.available_stock !== undefined 
            ? variant.available_stock 
            : Math.max(0, variant.stock - (variant.reserved_stock || 0) - (variant.damaged_stock || 0));
        return acc + avail;
    }, 0);
};

const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount || 0);
};

const formatDateTime = (dateString) => {
    if (!dateString) return '';
    const d = new Date(dateString);
    return d.toLocaleString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// --- MODAL DE CONTROL DE DAÑOS ---
const showDamagedModal = ref(false);
const activeProduct = ref(null);
const activeVariant = ref(null);
const activeTab = ref('registrar');

const damagedForm = useForm({
    product_variant_id: '',
    type: 'marcar_danada',
    quantity: 1,
    notes: '',
});

const openDamagedModal = (product, variant) => {
    activeProduct.value = product;
    activeVariant.value = variant;
    damagedForm.product_variant_id = variant.id;
    damagedForm.type = 'marcar_danada';
    damagedForm.quantity = 1;
    damagedForm.notes = '';
    activeTab.value = 'registrar';
    showDamagedModal.value = true;
};

const submitDamagedForm = () => {
    damagedForm.post(route('damaged-stock.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showDamagedModal.value = false;
            // Actualizar referencia local de la variante activa si sigue en memoria
            Swal.fire({
                icon: 'success',
                title: '¡Movimiento Registrado!',
                text: 'El inventario y el historial han sido actualizados.',
                timer: 2000,
                showConfirmButton: false
            });
        },
        onError: (errors) => {
            const msg = Object.values(errors)[0] || 'Ocurrió un error al procesar el movimiento.';
            Swal.fire({
                icon: 'error',
                title: 'No permitido',
                text: msg
            });
        }
    });
};

// Resolver imagen o plantilla por defecto
const getProductImage = (imagePath) => {
    if (!imagePath) return '/storage/products/plantilla.jpg';
    if (imagePath.startsWith('http') || imagePath.startsWith('/')) return imagePath;
    if (imagePath.startsWith('products/')) return '/storage/' + imagePath;
    return '/storage/products/' + imagePath;
};

const handleImageError = (event) => {
    event.target.src = '/storage/products/plantilla.jpg';
};

// --- ACCIONES ---
const toggleFavorite = (product) => {
    router.put(route('products.toggle-favorite', product.id), {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const deleteProduct = (product) => {
    Swal.fire({
        title: '¿Eliminar Producto?',
        text: `Estás a punto de eliminar "${product.name}".`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('products.destroy', product.id), {
                onSuccess: () => Swal.fire('Eliminado', 'Producto eliminado.', 'success'),
                onError: (errors) => {
                    let mensaje = errors.error || 'Tiene historial de ventas.';
                    Swal.fire({ title: 'No permitido', text: mensaje, icon: 'error' });
                }
            });
        }
    });
};
</script>

<template>
    <Head title="Inventario" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                    <h2 class="text-2xl font-bold text-gray-800">Inventario ({{ products.length }})</h2>
                    
                    <div class="flex flex-wrap gap-2 w-full md:w-auto items-center">
                        <select v-model="itemsPerPage" class="border-gray-300 rounded-lg text-sm focus:ring-green-500 cursor-pointer">
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">Todos</option>
                        </select>

                        <input v-model="search" type="text" placeholder="Buscar por nombre, categoría o medida..." class="border border-gray-300 rounded-lg px-4 py-2 w-full md:w-80 focus:ring-green-500 shadow-sm">
                        
                        <Link :href="route('products.create')" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-bold flex items-center shadow transition-colors">
                            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Nuevo
                        </Link>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-100 border-b">
                                <tr>
                                    <th class="px-4 py-3 text-center w-10">★</th>
                                    <th class="px-4 py-3">Imagen</th>
                                    <th class="px-6 py-3">Producto / Categoría</th>
                                    <th class="px-6 py-3">Variantes (Material, Medidas & Precios)</th>
                                    <th class="px-6 py-3 text-center">Stock Total</th>
                                    <th class="px-6 py-3 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="product in paginatedProducts" :key="product.id" class="bg-white border-b hover:bg-gray-50 transition">
                                    
                                    <!-- Favorito -->
                                    <td class="px-4 py-4 text-center cursor-pointer" @click="toggleFavorite(product)">
                                        <span class="text-xl transition-transform transform active:scale-125 inline-block" 
                                              :class="product.is_favorite ? 'text-yellow-400' : 'text-gray-200 hover:text-gray-300'">
                                            ★
                                        </span>
                                    </td>

                                    <!-- Miniatura de Imagen -->
                                    <td class="px-4 py-4">
                                        <img :src="getProductImage(product.image)" @error="handleImageError" class="w-12 h-12 object-cover rounded-lg border border-gray-200 shadow-sm">
                                    </td>

                                    <!-- Producto y Categoría -->
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-900 text-base">{{ product.name }}</div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] bg-green-50 text-green-700 mt-1 border border-green-200 font-semibold">
                                            {{ product.category ? product.category.name : 'General' }}
                                        </span>
                                    </td>

                                    <!-- Variantes (Con Material, Medidas, SKU y Desglose de Dañadas) -->
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1.5 py-1">
                                            <div v-for="variant in product.variants" :key="variant.id" class="text-xs flex flex-col md:flex-row md:items-center justify-between bg-gray-50 px-3 py-2 rounded-lg border border-gray-200 shadow-2xs gap-2">
                                                <div class="flex flex-col gap-1">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <span class="font-bold text-gray-800">{{ variant.material }}</span>
                                                        <span class="bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold border border-blue-100">{{ variant.measurements }}</span>
                                                        <span v-if="variant.sku" class="text-gray-400 text-[10px]">({{ variant.sku }})</span>
                                                    </div>
                                                    
                                                    <!-- Desglose por color si tiene existencias registradas -->
                                                    <div v-if="variant.color_stocks && variant.color_stocks.length > 0" class="flex items-center gap-1 flex-wrap">
                                                        <span v-for="cs in variant.color_stocks" :key="cs.id || cs.color" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px]" :class="cs.stock > 0 ? 'bg-blue-50 text-blue-800 border border-blue-200 font-bold' : 'bg-gray-100 text-gray-400 border border-gray-200'">
                                                            <span class="w-1.5 h-1.5 rounded-full mr-1" :class="cs.stock > 0 ? 'bg-blue-500' : 'bg-gray-300'"></span>
                                                            {{ cs.color }}: {{ cs.stock }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2 flex-wrap justify-end">
                                                    <span class="font-bold text-green-700 mr-1">{{ formatMoney(variant.price_1) }}</span>
                                                    
                                                    <!-- Desglose de Stock Físico / Dañadas / Disponible -->
                                                    <div class="flex items-center gap-1.5 bg-white px-2 py-1 rounded border border-gray-200 text-[11px]">
                                                        <span class="text-gray-600 font-semibold" title="Stock Físico Total en Bodega">
                                                            Físico: <b>{{ variant.stock }}</b>
                                                        </span>
                                                        <span v-if="variant.damaged_stock > 0" class="text-amber-600 font-bold border-l pl-1.5 border-gray-200" title="Piezas Dañadas">
                                                            ⚠️ {{ variant.damaged_stock }}
                                                        </span>
                                                        <span class="text-green-600 font-black border-l pl-1.5 border-gray-200" title="Disponibles para Venta">
                                                            Disp: {{ variant.available_stock !== undefined ? variant.available_stock : Math.max(0, variant.stock - (variant.reserved_stock || 0) - (variant.damaged_stock || 0)) }}
                                                        </span>
                                                    </div>

                                                    <!-- Botón Control de Daños -->
                                                    <button @click="openDamagedModal(product, variant)" class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 rounded text-[11px] font-bold border border-amber-300 transition cursor-pointer flex items-center gap-1 shadow-2xs" title="Marcar dañada, reparar o dar de baja y ver historial">
                                                        <span>⚠️ Daños</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Stock Global -->
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <span :class="getTotalStock(product.variants) < 5 ? 'text-red-700 bg-red-100' : 'text-gray-700 bg-gray-100'" class="px-2.5 py-0.5 rounded-full font-bold border border-transparent text-xs" title="Stock Físico Total">
                                                Físico: {{ getTotalStock(product.variants) }}
                                            </span>
                                            <span v-if="getTotalDamaged(product.variants) > 0" class="text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200" title="Piezas Dañadas en Total">
                                                ⚠️ {{ getTotalDamaged(product.variants) }} dañadas
                                            </span>
                                            <span class="text-[11px] font-black text-green-700 bg-green-50 px-2 py-0.5 rounded border border-green-200" title="Disponibles para Venta">
                                                {{ getTotalAvailable(product.variants) }} disponibles
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex justify-center items-center gap-3">
                                            <Link :href="route('products.edit', product.id)" class="text-blue-600 hover:text-blue-800 font-bold text-xs uppercase">Editar</Link>
                                            <button @click="deleteProduct(product)" class="text-red-400 hover:text-red-600 cursor-pointer">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredProducts.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                        No se encontraron productos.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="filteredProducts.length > 0" class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                        <span class="text-xs text-gray-500">
                            Mostrando {{ (currentPage - 1) * itemsPerPage + 1 }} a {{ Math.min(currentPage * itemsPerPage, filteredProducts.length) }} de {{ filteredProducts.length }}
                        </span>
                        <div class="flex items-center gap-2">
                            <button @click="prevPage" :disabled="currentPage === 1" class="px-3 py-1 text-xs font-bold bg-white border border-gray-300 rounded hover:bg-gray-100 disabled:opacity-50">Anterior</button>
                            <button @click="nextPage" :disabled="currentPage === totalPages" class="px-3 py-1 text-xs font-bold bg-white border border-gray-300 rounded hover:bg-gray-100 disabled:opacity-50">Siguiente</button>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Modal Control de Daños e Historial -->
        <div v-if="showDamagedModal" class="fixed inset-0 bg-gray-900/50 flex items-center justify-center z-50 p-4 backdrop-blur-xs">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden transform transition-all flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/80">
                    <div>
                        <h3 class="text-base font-extrabold text-gray-800 flex items-center gap-2">
                            <span class="text-amber-500 text-lg">⚠️</span> Control de Daños — {{ activeProduct?.name }}
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Variante: <b class="text-gray-700">{{ activeVariant?.material }}</b> 
                            <span v-if="activeVariant?.measurements">({{ activeVariant?.measurements }})</span>
                            <span v-if="activeVariant?.sku" class="text-gray-400 ml-1">SKU: {{ activeVariant?.sku }}</span>
                        </p>
                    </div>
                    <button @click="showDamagedModal = false" class="text-gray-400 hover:text-gray-600 transition text-2xl leading-none">&times;</button>
                </div>

                <!-- Stock Summary Badges -->
                <div class="px-6 py-3 bg-amber-50/50 border-b border-amber-100 flex items-center justify-around text-center text-xs">
                    <div>
                        <span class="text-gray-500 font-semibold block text-[11px]">Stock Físico Total</span>
                        <span class="text-base font-extrabold text-gray-800">{{ activeVariant?.stock }}</span>
                    </div>
                    <div class="border-l border-amber-200 pl-4">
                        <span class="text-amber-700 font-semibold block text-[11px]">Piezas Dañadas</span>
                        <span class="text-base font-black text-amber-600">{{ activeVariant?.damaged_stock || 0 }}</span>
                    </div>
                    <div class="border-l border-amber-200 pl-4">
                        <span class="text-gray-500 font-semibold block text-[11px]">En Detallado</span>
                        <span class="text-base font-bold text-gray-600">{{ activeVariant?.reserved_stock || 0 }}</span>
                    </div>
                    <div class="border-l border-amber-200 pl-4">
                        <span class="text-green-700 font-semibold block text-[11px]">Disponibles para Venta</span>
                        <span class="text-base font-black text-green-600">{{ activeVariant?.available_stock !== undefined ? activeVariant?.available_stock : Math.max(0, activeVariant?.stock - (activeVariant?.reserved_stock || 0) - (activeVariant?.damaged_stock || 0)) }}</span>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-gray-200 bg-gray-50 text-xs font-bold">
                    <button 
                        @click="activeTab = 'registrar'" 
                        :class="activeTab === 'registrar' ? 'bg-white border-b-2 border-amber-500 text-amber-700 font-black' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'"
                        class="px-6 py-3 transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>📝 Registrar Movimiento</span>
                    </button>
                    <button 
                        @click="activeTab = 'historial'" 
                        :class="activeTab === 'historial' ? 'bg-white border-b-2 border-amber-500 text-amber-700 font-black' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'"
                        class="px-6 py-3 transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>📜 Historial de Auditoría ({{ (activeVariant?.damaged_logs || activeVariant?.damagedLogs || []).length }})</span>
                    </button>
                </div>

                <!-- Body (Scrollable) -->
                <div class="p-6 overflow-y-auto flex-1">
                    <!-- Tab Registrar Movimiento -->
                    <form v-if="activeTab === 'registrar'" @submit.prevent="submitDamagedForm" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Tipo de Acción</label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                <label 
                                    :class="damagedForm.type === 'marcar_danada' ? 'border-amber-500 bg-amber-50/50 text-amber-900 ring-1 ring-amber-500' : 'border-gray-200 hover:bg-gray-50 text-gray-700'"
                                    class="border rounded-lg p-3 cursor-pointer transition flex flex-col justify-between"
                                >
                                    <div class="flex items-center gap-2 font-bold text-xs mb-1">
                                        <input type="radio" v-model="damagedForm.type" value="marcar_danada" class="text-amber-600 focus:ring-amber-500">
                                        <span>⚠️ Marcar Dañada</span>
                                    </div>
                                    <p class="text-[10px] text-gray-500">Pasa de disponible a dañada. El stock físico sigue intacto.</p>
                                </label>

                                <label 
                                    :class="damagedForm.type === 'reparada' ? 'border-green-500 bg-green-50/50 text-green-900 ring-1 ring-green-500' : 'border-gray-200 hover:bg-gray-50 text-gray-700'"
                                    class="border rounded-lg p-3 cursor-pointer transition flex flex-col justify-between"
                                >
                                    <div class="flex items-center gap-2 font-bold text-xs mb-1">
                                        <input type="radio" v-model="damagedForm.type" value="reparada" class="text-green-600 focus:ring-green-500">
                                        <span>🔧 Reparada</span>
                                    </div>
                                    <p class="text-[10px] text-gray-500">Regresa de dañada a disponible para venta y embarque.</p>
                                </label>

                                <label 
                                    :class="damagedForm.type === 'baja_definitiva' ? 'border-red-500 bg-red-50/50 text-red-900 ring-1 ring-red-500' : 'border-gray-200 hover:bg-gray-50 text-gray-700'"
                                    class="border rounded-lg p-3 cursor-pointer transition flex flex-col justify-between"
                                >
                                    <div class="flex items-center gap-2 font-bold text-xs mb-1">
                                        <input type="radio" v-model="damagedForm.type" value="baja_definitiva" class="text-red-600 focus:ring-red-500">
                                        <span>🗑️ Baja Definitiva</span>
                                    </div>
                                    <p class="text-[10px] text-gray-500">Merma o pérdida total. Descuenta del stock físico y de dañadas.</p>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wide">Cantidad de Piezas</label>
                            <div class="flex items-center gap-3">
                                <input 
                                    type="number" 
                                    v-model="damagedForm.quantity" 
                                    min="1" 
                                    :max="damagedForm.type === 'marcar_danada' ? (activeVariant?.available_stock ?? 1) : (activeVariant?.damaged_stock || 1)"
                                    required 
                                    class="w-32 border-gray-300 rounded-lg shadow-sm focus:ring-amber-500 focus:border-amber-500 text-center font-bold text-base p-2"
                                >
                                <span class="text-xs text-gray-500">
                                    <span v-if="damagedForm.type === 'marcar_danada'">Máx. permitido: <b>{{ activeVariant?.available_stock }}</b> disponibles</span>
                                    <span v-else>Máx. permitido: <b>{{ activeVariant?.damaged_stock || 0 }}</b> dañadas</span>
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wide">Motivo / Notas de Auditoría</label>
                            <textarea 
                                v-model="damagedForm.notes" 
                                rows="3" 
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-amber-500 focus:border-amber-500 text-xs p-2.5" 
                                placeholder="Ej. Esquina despostillada durante descarga en almacén..."
                            ></textarea>
                            <p class="text-[10px] text-gray-400 mt-1">Este motivo quedará guardado permanentemente con tu usuario.</p>
                        </div>

                        <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                            <button type="button" @click="showDamagedModal = false" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 font-bold text-xs rounded-lg hover:bg-gray-50 transition shadow-sm cursor-pointer">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="damagedForm.processing" class="px-5 py-2 bg-amber-600 text-white font-bold text-xs rounded-lg hover:bg-amber-700 transition shadow-sm disabled:opacity-50 cursor-pointer">
                                Guardar Movimiento
                            </button>
                        </div>
                    </form>

                    <!-- Tab Historial de Auditoría -->
                    <div v-else class="space-y-3">
                        <div v-if="(activeVariant?.damaged_logs || activeVariant?.damagedLogs || []).length === 0" class="text-center py-10 text-gray-400 text-xs">
                            No hay movimientos registrados para esta variante.
                        </div>
                        <div v-else class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="w-full text-left text-xs whitespace-nowrap">
                                <thead class="bg-gray-100 text-gray-600 uppercase text-[10px] font-bold border-b border-gray-200">
                                    <tr>
                                        <th class="px-3 py-2">Fecha y Hora</th>
                                        <th class="px-3 py-2">Usuario</th>
                                        <th class="px-3 py-2 text-center">Tipo de Movimiento</th>
                                        <th class="px-3 py-2 text-center">Cant.</th>
                                        <th class="px-3 py-2">Notas / Motivo</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="log in (activeVariant?.damaged_logs || activeVariant?.damagedLogs || [])" :key="log.id" class="hover:bg-gray-50">
                                        <td class="px-3 py-2 text-gray-500 font-mono text-[11px]">{{ formatDateTime(log.created_at) }}</td>
                                        <td class="px-3 py-2 font-bold text-gray-800">{{ log.user ? log.user.name : 'Usuario #' + log.user_id }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <span v-if="log.type === 'marcar_danada'" class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-[10px] font-bold border border-amber-200">
                                                ⚠️ Dañada
                                            </span>
                                            <span v-else-if="log.type === 'reparada'" class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-[10px] font-bold border border-green-200">
                                                🔧 Reparada
                                            </span>
                                            <span v-else class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-[10px] font-bold border border-red-200">
                                                🗑️ Baja Definitiva
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center font-extrabold text-gray-700">{{ log.quantity }}</td>
                                        <td class="px-3 py-2 text-gray-600 text-[11px] max-w-xs truncate" :title="log.notes">{{ log.notes || 'Sin observaciones' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>