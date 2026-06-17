<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    users: Array
});

const deleteUser = (userId, name) => {
    Swal.fire({
        title: '¿Eliminar Usuario?',
        text: `Se eliminará el acceso a ${name}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('users.destroy', userId), {
                onSuccess: () => Swal.fire('Eliminado', 'El usuario ha sido eliminado.', 'success'),
                onError: () => Swal.fire('Error', 'No se pudo eliminar.', 'error')
            });
        }
    });
};
</script>

<template>
    <Head title="Usuarios" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Usuarios del Sistema</h2>
                <Link :href="route('users.create')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow">
                    + Nuevo Usuario
                </Link>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rol</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="user in users" :key="user.id">
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">{{ user.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ user.email }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span 
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                        :class="user.role === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'"
                                    >
                                        {{ user.role === 'admin' ? 'Administrador' : 'Vendedor' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <Link :href="route('users.edit', user.id)" class="text-blue-600 hover:text-blue-900">Editar</Link>
                                    <button @click="deleteUser(user.id, user.name)" class="text-red-600 hover:text-red-900 ml-4">Eliminar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>