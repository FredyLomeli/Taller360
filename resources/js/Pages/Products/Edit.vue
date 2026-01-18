<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    product: Object, 
    categories: Array
});

// --- 1. REGLAS DE NEGOCIO (AJUSTADAS A LA BASE DE DATOS) ---
// Importante: Las llaves deben ser EXACTAS a como están escritas en la BD (Seeders)
const categoryRules = {
    'Roperos':               ['MDF', 'Madera y MDF enchapado', 'Melamina'],
    'Trinchers':             ['Madera', 'Madera y MDF enchapado', 'MDF'],
    'Cómodas y Lokers':      ['MDF', 'Madera y MDF enchapado', 'Melamina'],
    'Recámaras y comedores': ['MDF', 'Madera y MDF enchapado', 'Melamina'], // 'c' minúscula
    'Bases':                 ['MDF', 'Madera']
};

const materialColors = {
    'MDF':                    ['Chocolate', 'Nogal', 'Blanco', 'Gris', 'Cherry', '258'],
    'Madera':                 ['Chocolate', 'Caoba', 'Tabaco', 'Cherry'],
    
    // El seeder usa 'MELAMINA' o 'Melamina'? Normalizamos para asegurar.
    'Melamina':               ['Fresno andino', 'Parota', 'Nogal africano', 'Gris cenizo', 'Gris antracita', 'Tzalam', 'Moka'],
    'MELAMINA':               ['Fresno andino', 'Parota', 'Nogal africano', 'Gris cenizo', 'Gris antracita', 'Tzalam', 'Moka'],
    
    // Ajustado al nombre real de la BD
    'Madera y MDF enchapado': ['Chocolate', 'Nogal', 'Blanco', 'Caoba', 'Tabaco', 'Cherry'] 
};

// --- FORMULARIO ---
const form = useForm({
    _method: 'PUT', // Laravel resource espera PUT para updates (aunque enviemos POST por el archivo)
    name: props.product.name,
    category_id: props.product.category_id,
    measurements: props.product.measurements,
    description: props.product.description,
    image: null,
    
    variants: props.product.variants.map(v => ({
        id: v.id,
        material: v.material,
        color: v.color,
        stock: v.stock,
        sku: v.sku,
        price_1: v.price_1,
        price_2: v.price_2,
        price_3: v.price_3,
        price_4: v.price_4,
        price_5: v.price_5
    }))
});

// --- COMPUTADAS ---

const selectedCategoryName = computed(() => {
    // Usamos '==' (doble igual) para que '5' sea igual a 5 (flexible con tipos)
    const category = props.categories.find(c => c.id == form.category_id);
    return category ? category.name : null;
});

const availableMaterials = computed(() => {
    if (!selectedCategoryName.value) return [];
    // Busca en las reglas. Si no encuentra (por mayúsculas/minúsculas), intenta buscar seguro
    return categoryRules[selectedCategoryName.value] || [];
});

const getColorsForMaterial = (materialName) => {
    if (!materialName) return [];
    return materialColors[materialName] || [];
};

// --- ACCIONES ---

const addVariant = () => {
    form.variants.push({
        id: null, 
        material: '', color: '', stock: 0, sku: '', 
        price_1: 0, price_2: 0, price_3: 0, price_4: 0, price_5: 0 
    });
};

const deleteVariant = (variantId, index) => {
    if (!variantId) {
        form.variants.splice(index, 1);
        return;
    }

    Swal.fire({
        title: '¿Eliminar Variante?',
        text: "Esta acción es irreversible en la base de datos.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('variants.destroy', variantId), {
                preserveScroll: true,
                onSuccess: () => {
                    form.variants.splice(index, 1);
                    Swal.fire('Eliminado', 'Variante eliminada.', 'success');
                },
                onError: (errors) => {
                   Swal.fire('Error', errors.error || 'No se pudo eliminar.', 'error');
                }
            });
        }
    });
};

const submit = () => {
    // 1. Limpieza de números
    form.variants.forEach(variant => {
        const numericFields = ['stock', 'price_1', 'price_2', 'price_3', 'price_4', 'price_5'];
        numericFields.forEach(field => {
            let val = variant[field];
            if (val === '' || val === null || isNaN(val)) {
                variant[field] = 0;
            }
        });
    });

    // 2. Envío Correcto para Inertia Forms con Archivos
    // Usamos post con _method: PUT (simulado arriba) o usamos la ruta update con post
    // NOTA: Para subir archivos en "Edición", Laravel exige usar POST, pero simular PUT.
    // Como definimos _method: 'PUT' en el useForm, usamos form.post aqui.
    
    form.post(route('products.update', props.product.id), {
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Producto Actualizado',
                showConfirmButton: false,
                timer: 1500
            });
        },
        onError: () => {
             Swal.fire('Error', 'Revisa los campos en rojo.', 'error');
        }
    });
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
    <Head title="Editar Producto" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Editar Producto: {{ product.name }}</h2>
                        <Link :href="route('products.inventory')" class="text-gray-500 hover:text-gray-700 text-sm underline">
                            Cancelar
                        </Link>
                    </div>

                    <form @submit.prevent="submit">
                        
                        <div class="bg-gray-50 p-4 rounded-lg mb-6 border border-gray-200">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block font-medium text-sm text-gray-700">Nombre</label>
                                    <input v-model="form.name" type="text" class="w-full border-gray-300 rounded-md shadow-sm focus:border-green-500">
                                    <div v-if="form.errors.name" class="text-red-500 text-xs mt-1 font-bold">{{ form.errors.name }}</div>
                                </div>
                                <div>
                                    <label class="block font-medium text-sm text-gray-700">Categoría</label>
                                    <select v-model="form.category_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-green-500">
                                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                    </select>
                                    <div v-if="form.errors.category_id" class="text-red-500 text-xs mt-1 font-bold">{{ form.errors.category_id }}</div>
                                </div>
                                <div>
                                    <label class="block font-medium text-sm text-gray-700">Medidas</label>
                                    <input v-model="form.measurements" type="text" class="w-full border-gray-300 rounded-md shadow-sm focus:border-green-500">
                                    <div v-if="form.errors.measurements" class="text-red-500 text-xs mt-1 font-bold">{{ form.errors.measurements }}</div>
                                </div>
                                                            <div class="md:col-span-3 mt-4">
                                <label class="block text-sm font-bold text-gray-700 mb-2">Descripción Detallada</label>
                                    <textarea 
                                        v-model="form.description" 
                                        rows="3" 
                                        class="w-full rounded-lg border-gray-300 focus:border-green-500 focus:ring-green-500 shadow-sm"
                                        placeholder="Ej: Incluye correderas telescópicas, garantía de 1 año..."
                                    ></textarea>
                                    <div v-if="form.errors.description" class="text-red-500 text-sm mt-1">{{ form.errors.description }}</div>
                                </div>

                                <div class="md:col-span-3 mt-2 flex items-center gap-4">
                                    <div v-if="product.image" class="w-20 h-20 border rounded overflow-hidden">
                                        <img :src="'/storage/' + product.image" class="object-cover w-full h-full">
                                    </div>
                                    <div class="flex-1">
                                        <label class="block font-medium text-sm text-gray-700">Cambiar Imagen (Opcional)</label>
                                        <input type="file" @input="form.image = $event.target.files[0]" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100"/>
                                        <div v-if="form.errors.image" class="text-red-500 text-xs mt-1 font-bold">{{ form.errors.image }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-sm font-bold text-gray-700 uppercase mb-2">Variantes y Precios</h3>
                            
                            <div v-for="(variant, index) in form.variants" :key="index" class="bg-white border-2 border-gray-100 rounded-lg p-4 mb-4 shadow-sm relative hover:border-green-100">
                                <button type="button" @click="deleteVariant(variant.id, index)" class="absolute top-2 right-2 text-red-400 hover:text-red-600" title="Eliminar variante" v-if="form.variants.length > 1">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>

                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500">Material</label>
                                        <select v-model="variant.material" :disabled="!form.category_id" class="w-full text-sm border-gray-300 rounded-md focus:border-green-500">
                                            <option v-for="mat in availableMaterials" :key="mat" :value="mat">{{ mat }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500">Color</label>
                                        <select v-model="variant.color" :disabled="!variant.material" class="w-full text-sm border-gray-300 rounded-md focus:border-green-500">
                                            <option v-for="col in getColorsForMaterial(variant.material)" :key="col" :value="col">{{ col }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500">Stock</label>
                                        <input v-model="variant.stock" type="number" min="0" @blur="sanitizeNumber(variant, 'stock')" class="w-full text-sm border-gray-300 rounded-md focus:border-green-500">
                                    </div>
                                     <div>
                                        <label class="block text-xs font-bold text-gray-500">SKU</label>
                                        <input v-model="variant.sku" type="text" class="w-full text-sm border-gray-300 rounded-md focus:border-green-500">
                                    </div>
                                </div>

                                <div class="bg-green-50 p-3 rounded-md">
                                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                                        <div><span class="text-[10px] text-gray-500 font-bold">P1</span><input v-model="variant.price_1" lang="en" min="0" @blur="sanitizeNumber(variant, 'price_1')" type="number" step="0.01" class="w-full text-sm border-gray-300 rounded-md"></div>
                                        <div><span class="text-[10px] text-gray-500">P2</span><input v-model="variant.price_2" lang="en" min="0" @blur="sanitizeNumber(variant, 'price_2')" type="number" step="0.01" class="w-full text-sm border-gray-300 rounded-md"></div>
                                        <div><span class="text-[10px] text-gray-500">P3</span><input v-model="variant.price_3" lang="en" min="0" @blur="sanitizeNumber(variant, 'price_3')" type="number" step="0.01" class="w-full text-sm border-gray-300 rounded-md"></div>
                                        <div><span class="text-[10px] text-gray-500">P4</span><input v-model="variant.price_4" lang="en" min="0" @blur="sanitizeNumber(variant, 'price_4')" type="number" step="0.01" class="w-full text-sm border-gray-300 rounded-md"></div>
                                        <div><span class="text-[10px] text-gray-500">P5</span><input v-model="variant.price_5" lang="en" min="0" @blur="sanitizeNumber(variant, 'price_5')" type="number" step="0.01" class="w-full text-sm border-gray-300 rounded-md"></div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" @click="addVariant" class="w-full py-2 border-2 border-dashed border-gray-300 rounded-lg text-gray-500 hover:border-green-500 font-bold">Agregar otra variante</button>
                        </div>

                        <div class="flex justify-end pt-6 border-t border-gray-200">
                            <button type="submit" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg">
                                Actualizar Producto
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>