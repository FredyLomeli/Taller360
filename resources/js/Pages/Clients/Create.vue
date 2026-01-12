<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    business_name: '',
    price_tier: 1, // Por defecto precio público
    email: '',
    phones: '',
    street_address: '',
    neighborhood: '',
    city: '',
    state: '',
    delegation: '',
    zip_code: '',
    references: ''
});

const submit = () => {
    form.post(route('clients.store'));
};
</script>

<template>
    <Head title="Nuevo Cliente" />
    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto py-10">
            <div class="bg-white p-8 rounded-lg shadow border">
                <h2 class="text-xl font-bold mb-6 text-gray-800">Registrar Nuevo Cliente</h2>
                
                <form @submit.prevent="submit">
                    <div class="mb-6">
                        <h3 class="text-sm font-bold text-blue-600 uppercase border-b pb-1 mb-3">Identificación y Precio</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700">Nombre Corto (Pila)</label>
                                <input v-model="form.name" type="text" class="w-full border-gray-300 rounded focus:ring-blue-500">
                                <div v-if="form.errors.name" class="text-red-500 text-xs">{{ form.errors.name }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700">Razón Social (Facturación)</label>
                                <input v-model="form.business_name" type="text" class="w-full border-gray-300 rounded focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700">Nivel de Precio Asignado</label>
                                <select v-model="form.price_tier" class="w-full border-gray-300 rounded focus:ring-blue-500 bg-blue-50">
                                    <option :value="1">Precio 1</option>
                                    <option :value="2">Precio 2</option>
                                    <option :value="3">Precio 3</option>
                                    <option :value="4">Precio 4</option>
                                    <option :value="5">Precio 5</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-sm font-bold text-blue-600 uppercase border-b pb-1 mb-3">Contacto</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700">Teléfonos</label>
                                <input v-model="form.phones" type="text" placeholder="Ej: 333-123-4567, 331-..." class="w-full border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700">Correo Electrónico</label>
                                <input v-model="form.email" type="email" class="w-full border-gray-300 rounded">
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-sm font-bold text-blue-600 uppercase border-b pb-1 mb-3">Dirección y Referencias</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-gray-700">Calle y Número</label>
                                <input v-model="form.street_address" type="text" class="w-full border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700">Código Postal</label>
                                <input v-model="form.zip_code" type="text" class="w-full border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700">Colonia</label>
                                <input v-model="form.neighborhood" type="text" class="w-full border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700">Ciudad</label>
                                <input v-model="form.city" type="text" class="w-full border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700">Estado</label>
                                <input v-model="form.state" type="text" class="w-full border-gray-300 rounded">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700">Referencias (Nombres y Teléfonos)</label>
                            <textarea v-model="form.references" rows="3" class="w-full border-gray-300 rounded"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('clients.index')" class="px-4 py-2 text-gray-600 hover:text-gray-900">Cancelar</Link>
                        <button type="submit" :disabled="form.processing" class="bg-blue-600 text-white px-6 py-2 rounded font-bold shadow hover:bg-blue-700">Guardar Cliente</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>