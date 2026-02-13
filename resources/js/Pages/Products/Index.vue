<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue'; 
import Swal from 'sweetalert2';

const props = defineProps({
    products: Array // Recibimos el array completo
});

const search = ref('');

// --- PAGINACIÓN LOCAL (Tu lógica original) ---
const itemsPerPage = ref(10);
const currentPage = ref(1);

// Filtrado 
const filteredProducts = computed(() => {
    if (!search.value) return props.products;
    const term = search.value.toLowerCase();
    
    return props.products.filter(p => 
        p.name.toLowerCase().includes(term) ||
        (p.category && p.category.name.toLowerCase().includes(term)) ||
        p.variants.some(v => v.sku && v.sku.toLowerCase().includes(term))
    );
});

// Reseteos de página al buscar
watch(search, () => { currentPage.value = 1; });
watch(itemsPerPage, () => { currentPage.value = 1; });

// Datos paginados (Corte del array)
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

const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount || 0);
};

// --- ACCIONES (Backend) ---

// 1. Alternar Favorito (NUEVO)
const toggleFavorite = (product) => {
    router.put(route('products.toggle-favorite', product.id), {}, {
        preserveScroll: true,
        preserveState: true, // Importante para no perder la página actual
        onSuccess: () => {
            // Opcional: Feedback visual muy sutil si quieres
        }
    });
};

// 2. Eliminar
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

                        <input v-model="search" type="text" placeholder="Buscar..." class="border border-gray-300 rounded-lg px-4 py-2 w-full md:w-64 focus:ring-green-500 shadow-sm">
                        
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
                                    <th class="px-6 py-3">Producto</th>
                                    <th class="px-6 py-3">Variantes</th>
                                    <th class="px-6 py-3 text-center">Stock</th>
                                    <th class="px-6 py-3 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="product in paginatedProducts" :key="product.id" class="bg-white border-b hover:bg-gray-50 transition">
                                    
                                    <td class="px-4 py-4 text-center cursor-pointer" @click="toggleFavorite(product)">
                                        <span class="text-xl transition-transform transform active:scale-125 inline-block" 
                                              :class="product.is_favorite ? 'text-yellow-400' : 'text-gray-200 hover:text-gray-300'">
                                            ★
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-900 text-base">{{ product.name }}</div>
                                        <div class="text-xs text-gray-400">{{ product.measurements || 'Estándar' }}</div>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-600 mt-1 border">
                                            {{ product.category ? product.category.name : 'General' }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1">
                                            <div v-for="variant in product.variants" :key="variant.id" class="text-xs flex justify-between bg-gray-50 px-2 py-1 rounded border border-gray-100">
                                                <span class="font-bold text-gray-700">{{ variant.material }}</span>
                                                <div class="flex gap-2">
                                                    <span class="text-gray-500">{{ formatMoney(variant.price_1) }}</span>
                                                    <span :class="variant.stock > 0 ? 'text-green-600' : 'text-red-400 font-bold'">{{ variant.stock }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <span :class="getTotalStock(product.variants) < 5 ? 'text-red-700 bg-red-100' : 'text-green-700 bg-green-100'" class="px-3 py-1 rounded-full font-bold border border-transparent text-xs">
                                            {{ getTotalStock(product.variants) }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <div class="flex justify-center items-center gap-3">
                                            <Link :href="route('products.edit', product.id)" class="text-blue-600 hover:text-blue-800 font-bold text-xs uppercase">Editar</Link>
                                            <button @click="deleteProduct(product)" class="text-red-400 hover:text-red-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredProducts.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-400">
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
    </AuthenticatedLayout>
</template>