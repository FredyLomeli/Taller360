<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    settings: Object // Recibimos { company_name: '...', allow_negative_stock: '0', ... }
});

// Inicializamos el formulario con los datos que vienen de la BD
const form = useForm({
    company_name: props.settings.company_name || '',
    company_rfc: props.settings.company_rfc || '',
    company_address: props.settings.company_address || '',
    company_phone: props.settings.company_phone || '',
    notification_emails: props.settings.notification_emails || '',
    ticket_footer_text: props.settings.ticket_footer_text || '',
    
    // Checkbox: Convertimos el string '1'/'0' a booleano real
    allow_negative_stock: props.settings.allow_negative_stock === '1',
    
    // Campo especial para el archivo (null al inicio)
    company_logo: null 
});

// Previsualización del Logo
const logoPreview = ref(props.settings.company_logo ? `/storage/${props.settings.company_logo}` : null);

// Al seleccionar archivo
const handleLogoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.company_logo = file;
        logoPreview.value = URL.createObjectURL(file); // Previsualizar al instante
    }
};

const submit = () => {
    // Convertimos el booleano a '1' o '0' para que el backend lo entienda fácil
    // (Aunque Laravel puede manejar booleanos, ser explícitos ayuda con Key-Value)
    form.transform((data) => ({
        ...data,
        allow_negative_stock: data.allow_negative_stock ? '1' : '0',
    })).post(route('settings.update'), {
        onSuccess: () => {
            Swal.fire({
                title: 'Guardado',
                text: 'La configuración se actualizó correctamente.',
                icon: 'success',
                confirmButtonColor: '#16a34a',
                timer: 1500
            });
            // Opcional: recargar para asegurar que el logo nuevo se vea si hubo cambios raros
            // router.reload();
        },
        onError: () => {
            Swal.fire('Error', 'Revisa los campos e intenta de nuevo.', 'error');
        }
    });
};
</script>

<template>
    <Head title="Configuración" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Configuración del Sistema</h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                
                <form @submit.prevent="submit" class="space-y-6">
                    
                    <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center border-b pb-2">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            Identidad de la Empresa
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="col-span-1 flex flex-col items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition-colors relative">
                                <label class="cursor-pointer flex flex-col items-center w-full">
                                    <div v-if="logoPreview" class="mb-2">
                                        <img :src="logoPreview" class="h-32 object-contain" alt="Logo Actual">
                                    </div>
                                    <div v-else class="text-gray-400 mb-2">
                                        <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <span class="text-sm text-blue-600 font-bold">Seleccionar Logo</span>
                                    <span class="text-xs text-gray-400 mt-1">(PNG, JPG max 1MB)</span>
                                    <input type="file" @change="handleLogoChange" class="hidden" accept="image/*">
                                </label>
                            </div>

                            <div class="col-span-2 space-y-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700">Nombre Comercial</label>
                                    <input v-model="form.company_name" type="text" class="w-full border-gray-300 rounded focus:ring-green-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700">RFC / Tax ID</label>
                                    <input v-model="form.company_rfc" type="text" class="w-full border-gray-300 rounded focus:ring-green-500">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700">Teléfono</label>
                                        <input v-model="form.company_phone" type="text" class="w-full border-gray-300 rounded focus:ring-green-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700">Dirección</label>
                                        <input v-model="form.company_address" type="text" placeholder="Calle, Número, Ciudad" class="w-full border-gray-300 rounded focus:ring-green-500">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center border-b pb-2">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Reglas de Negocio
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-100">
                                <div>
                                    <span class="block font-bold text-gray-800">Vender sin Stock</span>
                                    <span class="text-sm text-gray-500">Permitir ventas aunque el inventario sea 0.</span>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="form.allow_negative_stock" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                </label>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Correos de Notificación (Admins)</label>
                                <textarea v-model="form.notification_emails" rows="3" placeholder="admin@empresa.com, gerente@empresa.com" class="w-full border-gray-300 rounded text-sm"></textarea>
                                <p class="text-xs text-gray-400 mt-1">Separa los correos con comas.</p>
                            </div>
                        </div>

                         <div class="mt-4">
                            <label class="block text-sm font-bold text-gray-700">Mensaje Pie de Ticket</label>
                            <input v-model="form.ticket_footer_text" type="text" class="w-full border-gray-300 rounded">
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="bg-green-700 hover:bg-green-800 text-white font-bold py-3 px-8 rounded-lg shadow-lg flex items-center transition-all"
                        >
                            <span v-if="form.processing">Guardando...</span>
                            <span v-else>Guardar Cambios</span>
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </AuthenticatedLayout>
</template>