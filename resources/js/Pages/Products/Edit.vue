<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    product: Object, // El producto a editar
    categories: Array
});

// --- REGLAS DE NEGOCIO (Las mismas de Create.vue) ---
const categoryRules = {
    'Roperos': ['MDF', 'Madera', 'Melamina'],
    'Trinchers': ['Madera', 'MDF', 'MDF Enchapado'],
    'Cómodas y Lokers': ['MDF', 'MDF Enchapado', 'Melamina'],
    'Recámaras y Comedores': ['MDF', 'MDF Enchapado', 'Melamina'],
    'Bases': ['MDF', 'Madera']
};
const materialColors = {
    'MDF': ['Chocolate', 'Nogal', 'Blanco', 'Gris', 'Cherry', '258'],
    'Madera': ['Chocolate', 'Caoba', 'Tabaco', 'Cherry'],
    'Melamina': ['Fresno andino', 'Parota', 'Nogal africano', 'Gris cenizo', 'Gris antracita', 'Tzalam', 'Moka'],
    'MDF Enchapado': ['Chocolate', 'Nogal', 'Blanco']
};

// --- FORMULARIO (Pre-llenado) ---
const form = useForm({
    _method: 'POST', // Truco para enviar archivos en updates
    name: props.product.name,
    category_id: props.product.category_id,
    measurements: props.product.measurements,
    description: props.product.description,
    image: null, // Solo se llena si cambian la imagen
    
    // Mapeamos las variantes existentes
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

// --- COMPUTADAS Y FUNCIONES ---
const selectedCategoryName = computed(() => {
    const category = props.categories.find(c => c.id === form.category_id);
    return category ? category.name : null;
});

const availableMaterials = computed(() => {
    if (!selectedCategoryName.value) return [];
    return categoryRules[selectedCategoryName.value] || [];
});

const getColorsForMaterial = (materialName) => {
    if (!materialName) return [];
    return materialColors[materialName] || [];
};

const addVariant = () => {
    form.variants.push({id: null, material: '', color: '', stock: 0, sku: '', price_1: '', price_2: '', price_3: '', price_4: '', price_5: '' });
};

const removeVariant = (index) => {
    const variant = form.variants[index];

    // CASO 1: Es una variante nueva (aún no se guarda en BD)
    // No tiene ID o su ID es nulo. Solo la quitamos del array visual.
    if (!variant.id) {
        form.variants.splice(index, 1);
        return;
    }

    // CASO 2: Es una variante real (ya existe en BD)
    // Preguntamos antes de disparar la petición al servidor.
    if (confirm(`¿Estás seguro de eliminar permanentemente la variante ${variant.material} - ${variant.color}?`)) {
        
        router.delete(route('variants.destroy', variant.id), {
            preserveScroll: true,
            onSuccess: () => {
                // Si el servidor la borró con éxito, la quitamos de la lista visual
                // Nota: Inertia usualmente recarga los props, pero esto hace que la UI se sienta instantánea
                form.variants.splice(index, 1);
            },
            onError: (errors) => {
                // Si falla (por ejemplo, porque ya tiene ventas), mostramos el error
                if(errors.error) alert(errors.error);
            }
        });
    }
};

const submit = () => {
    // Enviamos a la ruta UPDATE
    router.post(route('products.update', props.product.id), form);
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
                                </div>
                                <div>
                                    <label class="block font-medium text-sm text-gray-700">Categoría</label>
                                    <select v-model="form.category_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-green-500">
                                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-medium text-sm text-gray-700">Medidas</label>
                                    <input v-model="form.measurements" type="text" class="w-full border-gray-300 rounded-md shadow-sm focus:border-green-500">
                                </div>

                                <div class="md:col-span-3 mt-2 flex items-center gap-4">
                                    <div v-if="product.image" class="w-20 h-20 border rounded overflow-hidden">
                                        <img :src="'/storage/' + product.image" class="object-cover w-full h-full">
                                    </div>
                                    <div class="flex-1">
                                        <label class="block font-medium text-sm text-gray-700">Cambiar Imagen (Opcional)</label>
                                        <input type="file" @input="form.image = $event.target.files[0]" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100"/>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-sm font-bold text-gray-700 uppercase mb-2">Variantes y Precios</h3>
                            
                            <div v-for="(variant, index) in form.variants" :key="index" class="bg-white border-2 border-gray-100 rounded-lg p-4 mb-4 shadow-sm relative hover:border-green-100">
                                <button type="button" @click="removeVariant(index)" class="absolute top-2 right-2 text-red-400 hover:text-red-600" v-if="form.variants.length > 1">
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
                                        <input v-model="variant.stock" type="number" class="w-full text-sm border-gray-300 rounded-md focus:border-green-500">
                                    </div>
                                     <div>
                                        <label class="block text-xs font-bold text-gray-500">SKU</label>
                                        <input v-model="variant.sku" type="text" class="w-full text-sm border-gray-300 rounded-md focus:border-green-500">
                                    </div>
                                </div>

                                <div class="bg-green-50 p-3 rounded-md">
                                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                                        <div><span class="text-[10px] text-gray-500 font-bold">P1</span><input v-model="variant.price_1" type="number" step="0.01" class="w-full text-sm border-gray-300 rounded-md"></div>
                                        <div><span class="text-[10px] text-gray-500">P2</span><input v-model="variant.price_2" type="number" step="0.01" class="w-full text-sm border-gray-300 rounded-md"></div>
                                        <div><span class="text-[10px] text-gray-500">P3</span><input v-model="variant.price_3" type="number" step="0.01" class="w-full text-sm border-gray-300 rounded-md"></div>
                                        <div><span class="text-[10px] text-gray-500">P4</span><input v-model="variant.price_4" type="number" step="0.01" class="w-full text-sm border-gray-300 rounded-md"></div>
                                        <div><span class="text-[10px] text-gray-500">P5</span><input v-model="variant.price_5" type="number" step="0.01" class="w-full text-sm border-gray-300 rounded-md"></div>
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