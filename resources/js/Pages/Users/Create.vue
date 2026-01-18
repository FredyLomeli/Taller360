<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    role: 'vendedor', // Valor por defecto
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('users.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Crear Usuario" />

    <AuthenticatedLayout>
        <div class="max-w-2xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">Registrar Nuevo Empleado</h2>

                    <form @submit.prevent="submit">
                        
                        <div class="mb-4">
                            <label class="block font-medium text-sm text-gray-700">Nombre Completo</label>
                            <input v-model="form.name" type="text" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 mt-1" required>
                            <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div class="mb-4">
                            <label class="block font-medium text-sm text-gray-700">Correo Electrónico (Login)</label>
                            <input v-model="form.email" type="email" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 mt-1" required>
                            <div v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</div>
                        </div>

                        <div class="mb-4">
                            <label class="block font-medium text-sm text-gray-700">Rol de Acceso</label>
                            <select v-model="form.role" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 mt-1">
                                <option value="vendedor">Vendedor (Solo Ventas)</option>
                                <option value="admin">Administrador (Acceso Total)</option>
                            </select>
                            <div v-if="form.errors.role" class="text-red-500 text-xs mt-1">{{ form.errors.role }}</div>
                        </div>

                        <div class="mb-4">
                            <label class="block font-medium text-sm text-gray-700">Contraseña</label>
                            <input v-model="form.password" type="password" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 mt-1" required>
                            <div v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</div>
                        </div>

                        <div class="mb-6">
                            <label class="block font-medium text-sm text-gray-700">Confirmar Contraseña</label>
                            <input v-model="form.password_confirmation" type="password" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 mt-1" required>
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <Link :href="route('users.index')" class="text-sm text-gray-600 hover:text-gray-900 underline mr-4">
                                Cancelar
                            </Link>

                            <button type="submit" :disabled="form.processing" class="bg-blue-800 hover:bg-blue-900 text-white font-bold py-2 px-4 rounded shadow disabled:opacity-50">
                                Registrar Usuario
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>