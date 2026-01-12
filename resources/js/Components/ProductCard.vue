<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    product: Object,
    priceTier: Number // Recibimos el nivel del cliente (1, 2, 3...)
});

const emit = defineEmits(['add-to-cart', 'open-modal']);

// --- ESTADO ---
const selectedMaterial = ref('');
const selectedVariant = ref(null);

// --- LÓGICA DE FILTRADO ---
const uniqueMaterials = computed(() => {
    return [...new Set(props.product.variants.map(v => v.material))];
});

const availableVariantsForMaterial = computed(() => {
    if (!selectedMaterial.value) return [];
    return props.product.variants.filter(v => v.material === selectedMaterial.value);
});

const selectMaterial = (material) => {
    selectedMaterial.value = material;
    const firstVariant = props.product.variants.find(v => v.material === material);
    selectedVariant.value = firstVariant;
};

const selectVariant = (variant) => {
    selectedVariant.value = variant;
};

// --- CORRECCIÓN: PRECIO COMPUTADO ---
const currentPrice = computed(() => {
    // 1. Si no hay variante o no hay cliente seleccionado, no hay precio
    if (!selectedVariant.value || !props.priceTier) return null;
    
    // 2. Buscamos la columna exacta (ej: price_3)
    const columnName = `price_${props.priceTier}`;
    const rawPrice = selectedVariant.value[columnName];

    // 3. BLINDAJE ANTI-NaN:
    // Si el valor es null, undefined o texto vacío, retornamos null inmediatamente
    if (rawPrice === null || rawPrice === undefined || rawPrice === '') {
        return null;
    }
    
    // 4. Intentamos convertir a número
    const finalPrice = parseFloat(rawPrice);

    // 5. Si la conversión falló (NaN), retornamos null
    return isNaN(finalPrice) ? null : finalPrice;
});

const emitAddToCart = () => {
    if (selectedVariant.value && currentPrice.value) {
        emit('add-to-cart', {
            variant_id: selectedVariant.value.id,
            product_name: props.product.name,
            material: selectedVariant.value.material,
            color: selectedVariant.value.color,
            price: currentPrice.value,
            sku: selectedVariant.value.sku
        });
    }
};

const formatMoney = (amount) => {
    if (!amount) return ''; 
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};

// Seleccionar defecto al montar
onMounted(() => {
    if (props.product.variants.length > 0) {
        const cheapest = props.product.variants.sort((a, b) => a.price_1 - b.price_1)[0];
        selectMaterial(cheapest.material);
        selectVariant(cheapest);
    }
});
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col h-full hover:shadow-md transition-shadow">
        
        <div 
        @click="$emit('open-modal', product)" 
        class="h-40 bg-gray-100 flex items-center justify-center overflow-hidden cursor-pointer hover:opacity-90 transition-opacity"
    >
         <img v-if="product.image" :src="`/storage/${product.image}`" class="w-full h-full object-cover">
         </div>

        <div class="p-4 flex-1 flex flex-col">
            <div class="text-xs font-bold text-green-600 uppercase tracking-wide mb-1">{{ product.category.name }}</div>
            <h3 class="font-bold text-gray-800 text-lg leading-tight mb-2">{{ product.name }}</h3>
            
            <div class="mb-3">
                <div class="flex flex-wrap gap-2">
                    <button v-for="material in uniqueMaterials" :key="material" @click="selectMaterial(material)" :class="{'bg-green-600 text-white': selectedMaterial === material, 'bg-gray-100 text-gray-600': selectedMaterial !== material}" class="px-2 py-1 text-xs rounded-md font-medium border border-transparent transition-colors">{{ material }}</button>
                </div>
            </div>

            <div class="mb-4" v-if="selectedMaterial">
                <div class="flex flex-wrap gap-2 items-center">
                    <button 
                        v-for="variant in availableVariantsForMaterial" 
                        :key="variant.id" 
                        @click="selectVariant(variant)" 
                        :class="{'ring-2 ring-offset-1 ring-green-500 scale-110': selectedVariant?.id === variant.id}" 
                        class="w-6 h-6 rounded-full border border-gray-300 bg-gray-500 transition-transform"
                    ></button>
                    <span class="text-xs text-gray-500 ml-2 font-medium">
                        {{ selectedVariant?.color }}
                    </span>
                </div>
            </div>

            <div class="mt-auto pt-4 border-t border-gray-100 flex items-center justify-between min-h-[50px]">
                
                <div>
                    <div v-if="currentPrice">
                        <span class="text-xl font-bold text-gray-800">
                            {{ formatMoney(currentPrice) }}
                        </span>
                    </div>
                    <div v-else class="text-xs text-gray-300 italic h-6">
                        </div>
                </div>
                
                <button 
                    @click="emitAddToCart"
                    :disabled="!currentPrice || selectedVariant?.stock <= 0"
                    :class="!currentPrice || selectedVariant?.stock <= 0 ? 'bg-gray-300 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700 active:scale-95'"
                    class="text-white p-2 rounded-lg shadow-lg transition-all"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                </button>
            </div>
        </div>
    </div>
</template>