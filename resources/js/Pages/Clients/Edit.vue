<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    client: Object, // El cliente a editar recibido desde el controlador
});

const form = useForm({
    name: props.client.name,
    business_name: props.client.business_name,
    price_tier: props.client.price_tier,
    email: props.client.email,
    phones: props.client.phones,
    street_address: props.client.street_address,
    neighborhood: props.client.neighborhood,
    city: props.client.city,
    state: props.client.state,
    delegation: props.client.delegation,
    zip_code: props.client.zip_code,
    references: props.client.references
});

const submit = () => {
    form.put(route('clients.update', props.client.id));
};
</script>

<template>
    <Head title="Editar Cliente" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                    <div class="p-8 border-b border-gray-200">
                        
                        <div class="flex justify-between items-center mb-8">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">Editar Cliente</h2>
                                <p class="text-sm text-gray-500">Editando a: <span class="font-bold text-blue-600">{{ client.name }}</span></p>
                            </div>
                            <Link :href="route('clients.index')" class="text-sm text-gray-500 hover:text-gray-700 underline">
                                Volver al listado
                            </Link>
                        </div>
                        
                        <form @submit.prevent="submit">
                            
                            <div class="mb-8">
                                <h3 class="text-xs font-bold text-blue-600 uppercase tracking-widest border-b border-gray-100 pb-2 mb-4">
                                    Identificación y Precio
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="col-span-1">
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Nombre Corto (Pila) <span class="text-red-500">*</span></label>
                                        <input v-model="form.name" type="text" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <div v-if="form.errors.name" class="text-red-500 text-xs mt-1 font-bold">{{ form.errors.name }}</div>
                                    </div>

                                    <div class="col-span-1">
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Razón Social (Facturación)</label>
                                        <input v-model="form.business_name" type="text" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <div v-if="form.errors.business_name" class="text-red-500 text-xs mt-1">{{ form.errors.business_name }}</div>
                                    </div>

                                    <div class="col-span-1 md:col-span-2">
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Nivel de Precio Asignado <span class="text-red-500">*</span></label>
                                        
                                        <select v-model="form.price_tier" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 bg-blue-50/50">
                                            <option :value="1">Lista A </option>
                                            <option :value="2">Lista B </option>
                                            <option :value="3">Lista C </option>
                                            <option :value="4">Lista D </option>
                                            <option :value="5">Lista E </option>
                                        </select>

                                        <div v-if="form.errors.price_tier" class="text-red-500 text-xs mt-1">{{ form.errors.price_tier }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-8">
                                <h3 class="text-xs font-bold text-blue-600 uppercase tracking-widest border-b border-gray-100 pb-2 mb-4">
                                    Información de Contacto
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Teléfonos <span class="text-red-500">*</span></label>
                                        <input v-model="form.phones" type="text" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <div v-if="form.errors.phones" class="text-red-500 text-xs mt-1">{{ form.errors.phones }}</div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Correo Electrónico <span class="text-red-500">*</span></label>
                                        <input v-model="form.email" type="email" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <div v-if="form.errors.email" class="text-red-500 text-xs mt-1 font-bold">{{ form.errors.email }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-8">
                                <h3 class="text-xs font-bold text-blue-600 uppercase tracking-widest border-b border-gray-100 pb-2 mb-4">
                                    Dirección de Entrega
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                                    <div class="md:col-span-4">
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Calle y Número</label>
                                        <input v-model="form.street_address" type="text" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Código Postal</label>
                                        <input v-model="form.zip_code" type="text" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500">
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Colonia</label>
                                        <input v-model="form.neighborhood" type="text" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Ciudad / Municipio</label>
                                        <input v-model="form.city" type="text" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Estado</label>
                                        <input v-model="form.state" type="text" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500">
                                    </div>

                                    <div class="md:col-span-6 mt-2">
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Referencias de Ubicación</label>
                                        <textarea v-model="form.references" rows="2" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end items-center gap-4 border-t border-gray-100 pt-6">
                                <Link :href="route('clients.index')" class="text-gray-500 font-medium hover:text-gray-800 transition-colors">
                                    Cancelar
                                </Link>
                                <button type="submit" 
                                    :disabled="form.processing" 
                                    class="bg-blue-600 text-white px-6 py-3 rounded-lg font-bold shadow-md hover:bg-blue-700 transition-all transform active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed flex items-center">
                                    <svg v-if="form.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ form.processing ? 'Guardando Cambios...' : 'Actualizar Cliente' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>