<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    products: Array
});

const search = ref('');

// --- FILTRADO SIMPLE EN FRONTEND ---
const filteredProducts = computed(() => {
    if (!search.value) return props.products;
    const term = search.value.toLowerCase();
    
    return props.products.filter(p => 
        p.name.toLowerCase().includes(term) ||
        p.category.name.toLowerCase().includes(term) ||
        p.variants.some(v => v.sku && v.sku.toLowerCase().includes(term)) // Buscar por SKU también
    );
});

// Función para sumar stock total de todas las variantes
const getTotalStock = (variants) => {
    return variants.reduce((acc, variant) => acc + variant.stock, 0);
};

const deleteProduct = (product) => {
    if (confirm(`¿Estás seguro de eliminar "${product.name}"? Esta acción no se puede deshacer.`)) {
        router.delete(route('products.destroy', product.id), {
            preserveScroll: true, // Para que no te mande al inicio de la página
        });
    }
};
</script>

<template>
    <Head title="Inventario" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                    <h2 class="text-2xl font-bold text-gray-800">Administración de Inventario</h2>
                    
                    <div class="flex gap-2 w-full md:w-auto">
                        <input v-model="search" type="text" placeholder="Buscar por nombre, categoría o SKU..." class="border border-gray-300 rounded-lg px-4 py-2 w-full md:w-80 focus:ring-green-500 focus:border-green-500">
                        
                        <Link :href="route('products.create')" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-bold flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Nuevo
                        </Link>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-100 border-b">
                                <tr>
                                    <th class="px-6 py-3">Producto</th>
                                    <th class="px-6 py-3">Categoría</th>
                                    <th class="px-6 py-3">Variantes (SKUs)</th>
                                    <th class="px-6 py-3 text-center">Stock Total</th>
                                    <th class="px-6 py-3 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="product in filteredProducts" :key="product.id" class="bg-white border-b hover:bg-gray-50 transition">
                                    
                                    <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                        {{ product.name }}
                                        <div class="text-xs text-gray-400">{{ product.measurements }}</div>
                                    </td>
                                    
                                    <td class="px-6 py-4">
                                        <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded border border-gray-500">
                                            {{ product.category.name }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1">
                                            <span v-for="variant in product.variants" :key="variant.id" class="text-xs">
                                                <span class="font-bold text-gray-700">{{ variant.material }}</span> 
                                                ({{ variant.color }})
                                                <span v-if="variant.sku" class="text-gray-400"> - SKU: {{ variant.sku }}</span>
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <span :class="getTotalStock(product.variants) < 5 ? 'text-red-600 bg-red-100' : 'text-green-600 bg-green-100'" class="px-2 py-1 rounded-full font-bold">
                                            {{ getTotalStock(product.variants) }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <div class="flex justify-center gap-2">
                                            <Link :href="route('products.edit', product.id)" class="font-medium text-blue-600 hover:underline">
                                                Editar
                                            </Link>
                                            
                                            <button @click="deleteProduct(product)" class="font-medium text-red-600 hover:underline">
                                                Borrar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredProducts.length === 0">
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-400">
                                        No se encontraron productos.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>