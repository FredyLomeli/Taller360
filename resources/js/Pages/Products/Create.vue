<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    categories: Array
});

// --- REGLAS DE NEGOCIO (Configuración) ---

// 1. Qué materiales permite cada categoría
const categoryRules = {
    'Roperos': ['MDF', 'Madera', 'Melamina'],
    'Trinchers': ['Madera', 'MDF', 'MDF Enchapado'],
    'Cómodas y Lokers': ['MDF', 'MDF Enchapado', 'Melamina'],
    'Recámaras y Comedores': ['MDF', 'MDF Enchapado', 'Melamina'],
    'Bases': ['MDF', 'Madera']
};

// 2. Qué colores permite cada material
const materialColors = {
    'MDF': ['Chocolate', 'Nogal', 'Blanco', 'Gris', 'Cherry', '258'],
    'Madera': ['Chocolate', 'Caoba', 'Tabaco', 'Cherry'],
    'Melamina': ['Fresno andino', 'Parota', 'Nogal africano', 'Gris cenizo', 'Gris antracita', 'Tzalam', 'Moka'],
    'MDF Enchapado': ['Chocolate', 'Nogal', 'Blanco'] // (Asumí estos, puedes editarlos)
};

// --- FORMULARIO ---
const form = useForm({
    name: '',
    category_id: '',
    measurements: '',
    description: '',
    image: null,
    variants: [
        { material: '', color: '', stock: 0, sku: '', price_1: '', price_2: '', price_3: '', price_4: '', price_5: '' }
    ]
});

// --- LÓGICA COMPUTADA ---

// Obtener el nombre de la categoría seleccionada actualmente
const selectedCategoryName = computed(() => {
    const category = props.categories.find(c => c.id === form.category_id);
    return category ? category.name : null;
});

// Obtener la lista de materiales permitidos según la categoría seleccionada
const availableMaterials = computed(() => {
    if (!selectedCategoryName.value) return [];
    // Si la categoría existe en nuestras reglas, devolvemos sus materiales, si no, devolvemos array vacío
    return categoryRules[selectedCategoryName.value] || [];
});

// Función para obtener colores según el material de UNA FILA específica
const getColorsForMaterial = (materialName) => {
    if (!materialName) return [];
    return materialColors[materialName] || [];
};

const addVariant = () => {
    form.variants.push({
        material: '', 
        color: '', 
        stock: 0,     // <--- CERO
        sku: '', 
        price_1: 0,   // <--- CERO
        price_2: 0, 
        price_3: 0, 
        price_4: 0, 
        price_5: 0 
    });
};

const removeVariant = (index) => {
    if (form.variants.length > 1) {
        form.variants.splice(index, 1);
    }
};

const submit = () => {
    // LIMPIEZA DE SEGURIDAD
    form.variants.forEach(variant => {
        const numericFields = ['stock', 'price_1', 'price_2', 'price_3', 'price_4', 'price_5'];
        
        numericFields.forEach(field => {
            let val = variant[field];
            if (val === '' || val === null || isNaN(val)) {
                variant[field] = 0;
            }
        });
    });

    // Enviamos
    form.post(route('products.store'));
};

const sanitizeNumber = (item, field) => {
    let value = item[field];
    if (value === '' || value === null || value === undefined || isNaN(value)) {
        item[field] = 0;
    } else {
        item[field] = Math.abs(parseFloat(value));
    }
};
</script>

<template>
    <Head title="Nuevo Producto" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Alta de Nuevo Producto</h2>
                        <Link :href="route('products.index')" class="text-gray-500 hover:text-gray-700 text-sm underline">
                            Cancelar y volver
                        </Link>
                    </div>

                    <form @submit.prevent="submit">
                        
                        <div class="bg-gray-50 p-4 rounded-lg mb-6 border border-gray-200">
                            <h3 class="text-sm font-bold text-gray-700 uppercase mb-4 border-b pb-2">Datos Generales</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block font-medium text-sm text-gray-700">Nombre del Modelo</label>
                                    <input v-model="form.name" type="text" class="w-full border-gray-300 rounded-md shadow-sm focus:border-green-500 focus:ring-green-500" placeholder="Ej: Ropero California">
                                    <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
                                </div>
                                <div>
                                    <label class="block font-medium text-sm text-gray-700">Categoría</label>
                                    <select v-model="form.category_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-green-500 focus:ring-green-500">
                                        <option value="" disabled>Seleccione...</option>
                                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                    </select>
                                     <div v-if="form.errors.category_id" class="text-red-500 text-xs mt-1">{{ form.errors.category_id }}</div>
                                </div>
                                <div>
                                    <label class="block font-medium text-sm text-gray-700">Medidas</label>
                                    <input v-model="form.measurements" type="text" class="w-full border-gray-300 rounded-md shadow-sm focus:border-green-500 focus:ring-green-500" placeholder="Ej: 180x120cm">
                                </div>
                            </div>
                            <div class="md:col-span-3 mt-4">
                                <label class="block font-medium text-sm text-gray-700">Fotografía del Producto</label>
                                <input 
                                    type="file" 
                                    @input="form.image = $event.target.files[0]" 
                                    class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100"
                                />
                                <div v-if="form.errors.image" class="text-red-500 text-xs mt-1">{{ form.errors.image }}</div>
                            </div>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-sm font-bold text-gray-700 uppercase mb-2">Variantes</h3>
                            
                            <div v-if="!form.category_id" class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                                <p class="text-sm text-yellow-700">Primero selecciona una <b>Categoría</b> arriba para ver los materiales disponibles.</p>
                            </div>

                            <div v-for="(variant, index) in form.variants" :key="index" class="bg-white border-2 border-gray-100 rounded-lg p-4 mb-4 shadow-sm relative hover:border-green-100 transition-colors">
                                
                                <button type="button" @click="removeVariant(index)" class="absolute top-2 right-2 text-red-400 hover:text-red-600" v-if="form.variants.length > 1">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>

                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500">Material</label>
                                        <select 
                                            v-model="variant.material" 
                                            :disabled="!form.category_id"
                                            class="w-full text-sm border-gray-300 rounded-md focus:border-green-500 focus:ring-green-500 disabled:bg-gray-100"
                                        >
                                            <option value="" disabled>Elegir...</option>
                                            <option v-for="mat in availableMaterials" :key="mat" :value="mat">
                                                {{ mat }}
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-gray-500">Color</label>
                                        <select 
                                            v-model="variant.color" 
                                            :disabled="!variant.material"
                                            class="w-full text-sm border-gray-300 rounded-md focus:border-green-500 focus:ring-green-500 disabled:bg-gray-100"
                                        >
                                            <option value="" disabled>Elegir...</option>
                                            <option v-for="col in getColorsForMaterial(variant.material)" :key="col" :value="col">
                                                {{ col }}
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-gray-500">Stock Inicial</label>
                                        <input v-model="variant.stock" type="number" min="0" @blur="sanitizeNumber(variant, 'stock')" class="w-full text-sm border-gray-300 rounded-md focus:border-green-500 focus:ring-green-500">
                                    </div>
                                     <div>
                                        <label class="block text-xs font-bold text-gray-500">Código (SKU)</label>
                                        <input v-model="variant.sku" type="text" class="w-full text-sm border-gray-300 rounded-md focus:border-green-500 focus:ring-green-500">
                                    </div>
                                </div>

                                <div class="bg-green-50 p-3 rounded-md">
                                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                                        <div>
                                            <span class="text-[10px] text-gray-500 font-bold">Precio 1</span>
                                            <input v-model="variant.price_1" type="number"  min="0" @blur="sanitizeNumber(variant, 'price_1')"  step="0.01" class="w-full text-sm border-gray-300 rounded-md focus:border-green-500 focus:ring-green-500" placeholder="$">
                                        </div>
                                        <div>
                                            <span class="text-[10px] text-gray-500">Precio 2</span>
                                            <input v-model="variant.price_2" type="number"  min="0" @blur="sanitizeNumber(variant, 'price_2')"  step="0.01" class="w-full text-sm border-gray-300 rounded-md focus:border-green-500 focus:ring-green-500" placeholder="$">
                                        </div>
                                        <div>
                                            <span class="text-[10px] text-gray-500">Precio 3</span>
                                            <input v-model="variant.price_3" type="number"  min="0" @blur="sanitizeNumber(variant, 'price_3')"  step="0.01" class="w-full text-sm border-gray-300 rounded-md focus:border-green-500 focus:ring-green-500" placeholder="$">
                                        </div>
                                        <div>
                                            <span class="text-[10px] text-gray-500">Precio 4</span>
                                            <input v-model="variant.price_4" type="number"  min="0" @blur="sanitizeNumber(variant, 'price_4')"  step="0.01" class="w-full text-sm border-gray-300 rounded-md focus:border-green-500 focus:ring-green-500" placeholder="$">
                                        </div>
                                        <div>
                                            <span class="text-[10px] text-gray-500">Precio 5</span>
                                            <input v-model="variant.price_5" type="number"  min="0" @blur="sanitizeNumber(variant, 'price_5')"  step="0.01" class="w-full text-sm border-gray-300 rounded-md focus:border-green-500 focus:ring-green-500" placeholder="$">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="button" @click="addVariant" class="w-full py-2 border-2 border-dashed border-gray-300 rounded-lg text-gray-500 hover:border-green-500 hover:text-green-600 transition-colors font-bold flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                Agregar otra variante
                            </button>
                        </div>

                        <div class="flex justify-end pt-6 border-t border-gray-200">
                            <button type="submit" :disabled="form.processing" class="bg-green-700 hover:bg-green-800 text-white font-bold py-3 px-6 rounded-lg shadow-lg disabled:opacity-50 transition-colors">
                                Guardar Producto
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>