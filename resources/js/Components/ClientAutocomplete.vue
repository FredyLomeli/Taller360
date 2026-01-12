<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    clients: Array, // Todos los clientes
    modelValue: Object // El cliente seleccionado actualmente (v-model)
});

const emit = defineEmits(['update:modelValue']);

const search = ref('');
const isOpen = ref(false);
const inputRef = ref(null);

// Filtrar clientes en tiempo real
const filteredClients = computed(() => {
    if (!search.value) return props.clients;
    const term = search.value.toLowerCase();
    return props.clients.filter(c => 
        c.name.toLowerCase().includes(term) ||
        (c.business_name && c.business_name.toLowerCase().includes(term))
    );
});

// Seleccionar un cliente de la lista
const selectClient = (client) => {
    emit('update:modelValue', client);
    search.value = client.name; // Ponemos el nombre en el input visual
    isOpen.value = false;
};

// Si el padre cambia el valor (ej: limpiar venta), actualizamos el input
watch(() => props.modelValue, (newVal) => {
    if (newVal) {
        search.value = newVal.name;
    } else {
        search.value = '';
    }
});

// Cerrar si clic afuera
const closeOnClickOutside = (e) => {
    if (inputRef.value && !inputRef.value.contains(e.target)) {
        isOpen.value = false;
    }
};

onMounted(() => document.addEventListener('click', closeOnClickOutside));
onUnmounted(() => document.removeEventListener('click', closeOnClickOutside));
</script>

<template>
    <div class="relative w-full" ref="inputRef">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">
            Cliente
        </label>
        
        <div class="relative">
            <input 
                type="text"
                v-model="search"
                @focus="isOpen = true"
                placeholder="Escribe para buscar..."
                class="w-full border-gray-300 rounded-lg shadow-sm focus:border-green-500 focus:ring-green-500 text-sm pl-10"
            >
            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            
            <button v-if="modelValue" @click="selectClient(null); search = '';" class="absolute right-2 top-2 text-gray-400 hover:text-red-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <div v-if="isOpen" class="absolute z-50 w-full bg-white mt-1 border border-gray-200 rounded-lg shadow-xl max-h-60 overflow-y-auto">
            <ul v-if="filteredClients.length > 0">
                <li 
                    v-for="client in filteredClients" 
                    :key="client.id"
                    @click="selectClient(client)"
                    class="px-4 py-2 hover:bg-green-50 cursor-pointer border-b border-gray-50 last:border-0"
                >
                    <div class="font-bold text-gray-800 text-sm">{{ client.name }}</div>
                    <div class="text-xs text-gray-500 flex justify-between">
                        <span>{{ client.business_name || 'Particular' }}</span>
                        <span class="text-green-600 font-bold">Nivel {{ client.price_tier }}</span>
                    </div>
                </li>
            </ul>
            <div v-else class="p-4 text-center text-sm text-gray-500">
                No se encontraron clientes.
            </div>
        </div>
    </div>
</template>