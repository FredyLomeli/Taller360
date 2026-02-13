<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue'; // <--- Importamos 'ref' para controlar el modal
import Swal from 'sweetalert2';

const props = defineProps({
    product: Object,
    categories: Array
});

// --- VARIABLE PARA EL MODAL DE IMAGEN ---
const showImageModal = ref(false); // Empieza cerrado (falso)

// --- 1. REGLAS DE NEGOCIO ---
const categoryRules = {
    'Roperos':               ['MDF', 'Madera y MDF enchapado', 'Melamina'],
    'Trinchers':             ['Madera', 'Madera y MDF enchapado', 'MDF'],
    'Cómodas y Lokers':      ['MDF', 'Madera y MDF enchapado', 'Melamina'],
    'Recámaras y comedores': ['MDF', 'Madera y MDF enchapado', 'Melamina'],
    'Bases':                 ['MDF', 'Madera']
};

// --- 2. FORMULARIO ---
const form = useForm({
    _method: 'PUT',
    name: props.product.name,
    category_id: props.product.category_id,
    measurements: props.product.measurements,
    description: props.product.description,
    is_favorite: Boolean(props.product.is_favorite),
    image: null,
    variants: props.product.variants.map(v => ({
        id: v.id,
        material: v.material,
        sku: v.sku,
        stock: v.stock,
        price_1: v.price_1,
        price_2: v.price_2,
        price_3: v.price_3,
        price_4: v.price_4,
        price_5: v.price_5
    }))
});

// --- 3. COMPUTADAS ---
const selectedCategoryName = computed(() => {
    const category = props.categories.find(c => c.id == form.category_id);
    return category ? category.name : null;
});

const availableMaterials = computed(() => {
    if (!selectedCategoryName.value) return [];
    const rules = categoryRules[selectedCategoryName.value];
    if (rules) return rules;
    return ['MDF', 'Madera', 'Melamina', 'MDF Enchapado', 'Pino', 'Parota', 'Tzalam']; 
});

// --- 4. ACCIONES ---
const addVariant = () => {
    form.variants.push({ 
        id: null, material: '', sku: '', stock: 0, 
        price_1: 0, price_2: null, price_3: null, price_4: null, price_5: null 
    });
};

const removeVariant = (index) => {
    if (form.variants.length > 1) {
        form.variants.splice(index, 1);
    } else {
        Swal.fire('Atención', 'El producto debe conservar al menos una variante.', 'warning');
    }
};

const handleImageUpload = (event) => {
    form.image = event.target.files[0];
};

const submit = () => {
    if (!form.name || !form.category_id) {
        Swal.fire('Faltan datos', 'Revisa Nombre y Categoría.', 'warning');
        return;
    }
    if (form.variants.some(v => !v.material || v.price_1 <= 0)) {
        Swal.fire('Variantes incompletas', 'Todas requieren MATERIAL y PRECIO PÚBLICO.', 'error');
        return;
    }
    form.post(route('products.update', props.product.id), {
        onSuccess: () => {
            Swal.fire({ title: '¡Actualizado!', text: 'Los cambios se guardaron correctamente.', icon: 'success', timer: 2000, showConfirmButton: false });
        },
        onError: (errors) => {
            console.error(errors);
            Swal.fire('Error', 'Revisa los campos marcados en rojo.', 'error');
        }
    });
};

const sanitizeNumber = (variant, field) => {
    if (variant[field] < 0) variant[field] = 0;
};
</script>

<template>
    <Head title="Editar Producto" />

    <AuthenticatedLayout>
        <div class="py-12 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Editar: {{ props.product.name }}</h2>
                        <p class="text-sm text-gray-500">Actualiza precios, stock o detalles.</p>
                    </div>
                    <Link :href="route('products.index')" class="text-gray-500 hover:text-gray-700 font-bold flex items-center gap-1 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Cancelar
                    </Link>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                        <h3 class="text-sm font-bold text-blue-600 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Información General</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Nombre del Modelo <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" class="w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                                <span v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</span>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Categoría <span class="text-red-500">*</span></label>
                                <select v-model="form.category_id" class="w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm cursor-pointer">
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                                <p v-if="selectedCategoryName" class="text-[10px] text-blue-500 mt-1 font-bold">✓ Materiales disponibles: {{ selectedCategoryName }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Medidas</label>
                                <input v-model="form.measurements" type="text" class="w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-gray-700 mb-1">Descripción</label>
                                <textarea v-model="form.description" rows="2" class="w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Fotografía</label>
                                <input @change="handleImageUpload" type="file" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                                
                                <div v-if="props.product.image" class="mt-2 flex items-center gap-2 p-2 bg-gray-50 rounded border border-gray-200">
                                    <img :src="'/storage/' + props.product.image" class="w-10 h-10 object-cover rounded border border-gray-300 shadow-sm">
                                    <span class="text-xs text-gray-600 font-bold">Imagen actual</span>
                                    
                                    <button type="button" @click="showImageModal = true" class="text-[10px] text-blue-600 underline ml-auto font-bold hover:text-blue-800 cursor-pointer">
                                        🔍 Ver Grande
                                    </button>
                                </div>
                            </div>

                            <div class="md:col-span-3">
                                <label class="inline-flex items-center gap-3 p-3 border border-yellow-200 bg-yellow-50 rounded-lg cursor-pointer hover:bg-yellow-100 transition-colors w-full sm:w-auto">
                                    <input v-model="form.is_favorite" type="checkbox" class="w-5 h-5 text-yellow-500 border-gray-300 rounded focus:ring-yellow-500 cursor-pointer">
                                    <div>
                                        <span class="block font-bold text-gray-800 text-sm">Destacar como Favorito ⭐</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                        <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-2">
                            <div>
                                <h3 class="text-sm font-bold text-blue-600 uppercase tracking-wider">Variantes de Inventario</h3>
                                <p class="text-xs text-gray-400 mt-1">Gestión de SKUs y precios por material.</p>
                            </div>
                            <button type="button" @click="addVariant" class="text-xs bg-blue-50 text-blue-600 border border-blue-200 px-3 py-1.5 rounded-lg font-bold hover:bg-blue-100 transition-colors flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                Agregar Variante
                            </button>
                        </div>

                        <div class="space-y-6">
                            <div v-for="(variant, index) in form.variants" :key="index" class="rounded-xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                                
                                <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 flex justify-between items-center">
                                    <span class="text-xs font-bold text-gray-500 uppercase">
                                        Variante #{{ index + 1 }} 
                                        <span v-if="variant.id" class="text-[9px] bg-gray-200 px-1 rounded ml-1 text-gray-500">ID: {{ variant.id }}</span>
                                    </span>
                                    <button v-if="form.variants.length > 1" type="button" @click="removeVariant(index)" class="text-gray-400 hover:text-red-500 transition-colors flex items-center gap-1 text-xs font-bold px-2 py-1 rounded hover:bg-red-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        Eliminar
                                    </button>
                                </div>

                                <div class="p-5 bg-white">
                                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-start mb-4">
                                        <div class="md:col-span-4">
                                            <label class="block text-xs font-bold text-gray-600 mb-1">Material <span class="text-red-500">*</span></label>
                                            <div v-if="!form.category_id" class="text-xs text-orange-500 p-2 bg-orange-50 rounded border border-orange-100">⚠ Categoría requerida.</div>
                                            <select v-else v-model="variant.material" class="w-full text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 cursor-pointer">
                                                <option v-if="variant.material && !availableMaterials.includes(variant.material)" :value="variant.material">{{ variant.material }} (Archivado)</option>
                                                <option value="" disabled>Seleccionar Material...</option>
                                                <option v-for="mat in availableMaterials" :key="mat" :value="mat">{{ mat }}</option>
                                            </select>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-xs font-bold text-gray-500 mb-1">SKU / Código</label>
                                            <input v-model="variant.sku" type="text" class="w-full text-sm border-gray-300 rounded-lg focus:ring-blue-500 text-gray-600">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-xs font-bold text-gray-500 mb-1">Stock</label>
                                            <input v-model="variant.stock" type="number" min="0" @blur="sanitizeNumber(variant, 'stock')" class="w-full text-sm border-gray-300 rounded-lg focus:ring-blue-500 text-gray-600">
                                        </div>
                                        <div class="md:col-span-4">
                                            <label class="block text-xs font-bold text-green-700 mb-1">Precio Público (P1) <span class="text-red-500">*</span></label>
                                            <div class="relative">
                                                <span class="absolute left-3 top-2 text-gray-500 font-bold">$</span>
                                                <input v-model="variant.price_1" type="number" min="0" step="0.50" @blur="sanitizeNumber(variant, 'price_1')" class="w-full pl-7 text-sm border-green-300 bg-green-50 rounded-lg focus:ring-green-500 font-bold text-gray-900 shadow-sm">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100 mt-4">
                                        <p class="text-[10px] text-gray-400 uppercase font-bold mb-2 tracking-wider">Listas de Precios Adicionales</p>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                            <div class="relative" v-for="i in 4" :key="i">
                                                <label class="block text-[9px] text-gray-500 mb-1">Precio {{ i + 1 }}</label>
                                                <div class="relative rounded-md shadow-sm">
                                                    <span class="absolute inset-y-0 left-0 flex items-center pl-2 text-gray-400 text-xs">$</span>
                                                    <input v-model="variant[`price_${i+1}`]" type="number" step="0.50" class="block w-full rounded border-gray-300 pl-5 text-xs focus:border-blue-500 focus:ring-blue-500" placeholder="0.00">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 pb-12">
                        <button type="submit" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-blue-200 transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                            <svg v-if="!form.processing" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                            <svg v-else class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            {{ form.processing ? 'Guardando...' : 'Actualizar Producto' }}
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <div v-if="showImageModal" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black bg-opacity-90 backdrop-blur-sm transition-opacity" @click.self="showImageModal = false">
            <div class="relative animate-fade-in-up max-w-5xl w-full">
                <button @click="showImageModal = false" class="absolute -top-12 right-0 text-white hover:text-gray-300 focus:outline-none">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>

                <img :src="'/storage/' + props.product.image" :alt="props.product.name" class="w-full h-auto max-h-[85vh] object-contain rounded-lg shadow-2xl border border-gray-700">
                
                <div class="text-center mt-4 text-white font-bold text-lg tracking-wide">{{ props.product.name }}</div>
            </div>
        </div>
        </AuthenticatedLayout>
</template>

<style scoped>
.animate-fade-in-up {
    animation: fadeInUp 0.3s ease-out;
}
@keyframes fadeInUp {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
</style>