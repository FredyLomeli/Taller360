<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    categories: Array,
    materials: Array,
    colors: Array,
});

// Pestaña activa ('colors', 'materials', 'categories')
const activeTab = ref('colors');

// ====================================================
//                     MODALES
// ====================================================

// --- Modal Colores ---
const showColorModal = ref(false);
const editingColor = ref(null);
const colorForm = useForm({
    name: '',
    hex: '#000000',
    activo: true,
});

const openCreateColorModal = () => {
    editingColor.value = null;
    colorForm.reset();
    colorForm.hex = '#3B2314';
    colorForm.activo = true;
    showColorModal.value = true;
};

const openEditColorModal = (color) => {
    editingColor.value = color;
    colorForm.name = color.name;
    colorForm.hex = color.hex;
    colorForm.activo = color.activo;
    showColorModal.value = true;
};

const submitColorForm = () => {
    if (editingColor.value) {
        colorForm.put(route('catalogs.colors.update', editingColor.value.id), {
            onSuccess: () => {
                showColorModal.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Color Actualizado',
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
            onError: (errors) => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al actualizar',
                    text: errors.name || 'Verifique los datos ingresados.',
                    confirmButtonColor: '#16a34a',
                });
            },
        });
    } else {
        colorForm.post(route('catalogs.colors.store'), {
            onSuccess: () => {
                showColorModal.value = false;
                colorForm.reset();
                Swal.fire({
                    icon: 'success',
                    title: 'Color Creado',
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
            onError: (errors) => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al crear',
                    text: errors.name || errors.hex || 'Verifique los datos.',
                    confirmButtonColor: '#16a34a',
                });
            },
        });
    }
};

const toggleColorGlobal = (color) => {
    router.put(route('catalogs.colors.update', color.id), {
        name: color.name,
        hex: color.hex,
        activo: !color.activo,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: color.activo ? 'Color Desactivado' : 'Color Activado',
                timer: 1200,
                showConfirmButton: false,
            });
        },
    });
};

// --- Modal Materiales ---
const showMaterialModal = ref(false);
const editingMaterial = ref(null);
const materialForm = useForm({
    name: '',
    color_obligatorio: false,
    stock_por_color: false,
    activo: true,
});

const openCreateMaterialModal = () => {
    editingMaterial.value = null;
    materialForm.reset();
    materialForm.color_obligatorio = false;
    materialForm.stock_por_color = false;
    materialForm.activo = true;
    showMaterialModal.value = true;
};

const openEditMaterialModal = (material) => {
    editingMaterial.value = material;
    materialForm.name = material.name;
    materialForm.color_obligatorio = Boolean(material.color_obligatorio);
    materialForm.stock_por_color = Boolean(material.stock_por_color);
    materialForm.activo = Boolean(material.activo);
    showMaterialModal.value = true;
};

const submitMaterialForm = () => {
    if (editingMaterial.value) {
        materialForm.put(route('catalogs.materials.update', editingMaterial.value.id), {
            onSuccess: () => {
                showMaterialModal.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Material Actualizado',
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
        });
    } else {
        materialForm.post(route('catalogs.materials.store'), {
            onSuccess: () => {
                showMaterialModal.value = false;
                materialForm.reset();
                Swal.fire({
                    icon: 'success',
                    title: 'Material Creado',
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
        });
    }
};

// --- Modal Asignar Colores a Material ---
const showAssignColorsModal = ref(false);
const targetMaterial = ref(null);
const selectedColorIds = ref([]);

const openAssignColorsModal = (material) => {
    targetMaterial.value = material;
    selectedColorIds.value = material.colors.map(c => c.id);
    showAssignColorsModal.value = true;
};

const submitAssignColors = () => {
    if (!targetMaterial.value) return;
    router.post(route('catalogs.materials.sync-colors', targetMaterial.value.id), {
        color_ids: selectedColorIds.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAssignColorsModal.value = false;
            Swal.fire({
                icon: 'success',
                title: 'Colores Asignados',
                timer: 1500,
                showConfirmButton: false,
            });
        },
    });
};

const toggleColorInMaterial = (material, color) => {
    const currentStatus = Boolean(color.pivot?.activo);
    router.post(route('catalogs.materials.colors.toggle', [material.id, color.id]), {
        activo: !currentStatus,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                icon: 'info',
                title: !currentStatus ? 'Color Habilitado en Material' : 'Color Descontinuado en Material',
                text: !currentStatus ? `Se reactivó ${color.name} para ${material.name}` : `Se descontinuó ${color.name} para ${material.name} (sigue en histórico)`,
                timer: 1500,
                showConfirmButton: false,
            });
        },
    });
};

// --- Modal Categorías ---
const showCategoryModal = ref(false);
const editingCategory = ref(null);
const categoryForm = useForm({
    name: '',
    activo: true,
});

const openCreateCategoryModal = () => {
    editingCategory.value = null;
    categoryForm.reset();
    categoryForm.activo = true;
    showCategoryModal.value = true;
};

const openEditCategoryModal = (cat) => {
    editingCategory.value = cat;
    categoryForm.name = cat.name;
    categoryForm.activo = Boolean(cat.activo);
    showCategoryModal.value = true;
};

const submitCategoryForm = () => {
    if (editingCategory.value) {
        categoryForm.put(route('catalogs.categories.update', editingCategory.value.id), {
            onSuccess: () => {
                showCategoryModal.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Categoría Actualizada',
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
        });
    } else {
        categoryForm.post(route('catalogs.categories.store'), {
            onSuccess: () => {
                showCategoryModal.value = false;
                categoryForm.reset();
                Swal.fire({
                    icon: 'success',
                    title: 'Categoría Creada',
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
        });
    }
};

// --- Modal Asignar Materiales a Categoría ---
const showAssignMaterialsModal = ref(false);
const targetCategory = ref(null);
const selectedMaterialIds = ref([]);

const openAssignMaterialsModal = (category) => {
    targetCategory.value = category;
    selectedMaterialIds.value = category.materials ? category.materials.map(m => m.id) : [];
    showAssignMaterialsModal.value = true;
};

const submitAssignMaterials = () => {
    if (!targetCategory.value) return;
    router.post(route('catalogs.categories.sync-materials', targetCategory.value.id), {
        material_ids: selectedMaterialIds.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAssignMaterialsModal.value = false;
            Swal.fire({
                icon: 'success',
                title: 'Materiales Asignados a la Categoría',
                timer: 1500,
                showConfirmButton: false,
            });
        },
    });
};
</script>

<template>
    <Head title="Administración de Catálogos" />

    <AuthenticatedLayout>
        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Encabezado Principal -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <span class="p-2 bg-green-100 text-green-800 rounded-lg">🎨</span>
                        Catálogos Oficiales y Materiales
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Control centralizado de colores de fábrica, especificación de materiales y categorías para producción y ventas.
                    </p>
                </div>
            </div>

            <!-- Navegación de Pestañas -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-8">
                    <button
                        @click="activeTab = 'colors'"
                        class="py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition"
                        :class="activeTab === 'colors' ? 'border-green-600 text-green-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    >
                        <span>🎨 Colores Globales</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                            {{ colors.length }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'materials'"
                        class="py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition"
                        :class="activeTab === 'materials' ? 'border-green-600 text-green-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    >
                        <span>🪵 Materiales y Paletas</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                            {{ materials.length }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'categories'"
                        class="py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition"
                        :class="activeTab === 'categories' ? 'border-green-600 text-green-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    >
                        <span>📁 Categorías de Muebles</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                            {{ categories.length }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- ========================================== -->
            <!--            PESTAÑA 1: COLORES              -->
            <!-- ========================================== -->
            <div v-if="activeTab === 'colors'" class="space-y-6">
                <div class="flex justify-between items-center bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Catálogo de Colores de Fábrica</h2>
                        <p class="text-xs text-gray-500">
                            Los nombres con historial comercial o de producción tienen candado de renombrado 🔒 para proteger la integridad histórica.
                        </p>
                    </div>
                    <button
                        @click="openCreateColorModal"
                        class="inline-flex items-center px-4 py-2 bg-green-700 hover:bg-green-800 text-white text-sm font-semibold rounded-lg shadow-sm transition"
                    >
                        + Nuevo Color
                    </button>
                </div>

                <!-- Grid de Colores -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <div
                        v-for="color in colors"
                        :key="color.id"
                        class="bg-white rounded-xl border p-4 shadow-sm flex flex-col justify-between transition hover:shadow-md"
                        :class="color.activo ? 'border-gray-200' : 'border-gray-200 bg-gray-50/70 opacity-75'"
                    >
                        <div class="flex items-start gap-3">
                            <!-- Muestra visual del Color -->
                            <div
                                class="w-12 h-12 rounded-xl shadow-inner border border-gray-300 flex-shrink-0 flex items-center justify-center font-bold text-xs"
                                :style="{ backgroundColor: color.hex }"
                            >
                                <span v-if="color.hex === '#FFFFFF'" class="text-gray-400">#FFF</span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <h3 class="font-bold text-gray-900 truncate">{{ color.name }}</h3>
                                    <span
                                        v-if="color.has_history"
                                        title="Protegido: Este color tiene registros en ventas o inventario"
                                        class="text-xs bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded font-medium flex items-center gap-1"
                                    >
                                        🔒 Con Historial
                                    </span>
                                </div>
                                <p class="text-xs font-mono text-gray-500 mt-0.5">{{ color.hex }}</p>
                                <div class="mt-2">
                                    <span
                                        class="inline-block px-2 py-0.5 text-xs font-semibold rounded-full"
                                        :class="color.activo ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700'"
                                    >
                                        {{ color.activo ? 'Activo' : 'Desactivado' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Acciones -->
                        <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs">
                            <button
                                @click="openEditColorModal(color)"
                                class="text-green-700 hover:text-green-900 font-semibold"
                            >
                                Editar
                            </button>
                            <button
                                @click="toggleColorGlobal(color)"
                                class="font-semibold transition"
                                :class="color.activo ? 'text-red-600 hover:text-red-800' : 'text-blue-600 hover:text-blue-800'"
                            >
                                {{ color.activo ? 'Desactivar' : 'Activar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!--          PESTAÑA 2: MATERIALES             -->
            <!-- ========================================== -->
            <div v-if="activeTab === 'materials'" class="space-y-6">
                <div class="flex justify-between items-center bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Especificación de Materiales y Paletas</h2>
                        <p class="text-xs text-gray-500">
                            Define qué materiales exigen color en ventas, cuáles dividen su stock atómicamente y qué tonos están permitidos o descontinuados.
                        </p>
                    </div>
                    <button
                        @click="openCreateMaterialModal"
                        class="inline-flex items-center px-4 py-2 bg-green-700 hover:bg-green-800 text-white text-sm font-semibold rounded-lg shadow-sm transition"
                    >
                        + Nuevo Material
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div
                        v-for="mat in materials"
                        :key="mat.id"
                        class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-4"
                    >
                        <!-- Cabecera Material -->
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">{{ mat.name }}</h3>
                                <div class="flex gap-2 mt-1.5 flex-wrap">
                                    <span
                                        class="px-2 py-0.5 text-xs font-semibold rounded"
                                        :class="mat.color_obligatorio ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ mat.color_obligatorio ? 'Color Obligatorio en Venta' : 'Color Opcional en Venta' }}
                                    </span>
                                    <span
                                        class="px-2 py-0.5 text-xs font-semibold rounded"
                                        :class="mat.stock_por_color ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ mat.stock_por_color ? 'Stock Dividido por Color' : 'Stock Global' }}
                                    </span>
                                </div>
                            </div>
                            <button
                                @click="openEditMaterialModal(mat)"
                                class="text-xs text-green-700 hover:text-green-900 font-semibold px-2 py-1 bg-green-50 rounded"
                            >
                                Configurar
                            </button>
                        </div>

                        <!-- Paleta de Colores de este material -->
                        <div class="pt-3 border-t border-gray-100">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    Paleta Asignada ({{ mat.colors.length }})
                                </h4>
                                <button
                                    @click="openAssignColorsModal(mat)"
                                    class="text-xs text-blue-600 hover:text-blue-800 font-semibold"
                                >
                                    + Agregar/Quitar Colores
                                </button>
                            </div>

                            <!-- Lista de Colores dentro del Material con toggle de descontinuación -->
                            <div v-if="mat.colors.length > 0" class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                <div
                                    v-for="c in mat.colors"
                                    :key="c.id"
                                    class="flex items-center justify-between p-2 rounded-lg border text-xs"
                                    :class="c.pivot?.activo ? 'bg-gray-50 border-gray-200' : 'bg-red-50/60 border-red-200'"
                                >
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="w-4 h-4 rounded-full border border-gray-300"
                                            :style="{ backgroundColor: c.hex }"
                                        ></div>
                                        <span class="font-medium" :class="c.pivot?.activo ? 'text-gray-800' : 'text-gray-500 line-through'">
                                            {{ c.name }}
                                        </span>
                                        <span v-if="!c.pivot?.activo" class="text-2xs bg-red-100 text-red-700 px-1 rounded font-bold">
                                            Descontinuado en este material
                                        </span>
                                    </div>
                                    <button
                                        @click="toggleColorInMaterial(mat, c)"
                                        class="font-semibold px-2 py-0.5 rounded transition text-2xs"
                                        :class="c.pivot?.activo ? 'bg-gray-200 text-gray-700 hover:bg-red-100 hover:text-red-700' : 'bg-green-600 text-white hover:bg-green-700'"
                                    >
                                        {{ c.pivot?.activo ? 'Descontinuar' : 'Habilitar' }}
                                    </button>
                                </div>
                            </div>
                            <div v-else class="text-xs text-gray-400 italic py-2">
                                Sin colores asignados. Se venderá sin selección de tono.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!--          PESTAÑA 3: CATEGORÍAS             -->
            <!-- ========================================== -->
            <div v-if="activeTab === 'categories'" class="space-y-6">
                <div class="flex justify-between items-center bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Categorías de Productos y Materiales Permitidos</h2>
                        <p class="text-xs text-gray-500">
                            Regla Firme A11: Cada categoría especifica qué materiales permite fabricar o comercializar.
                        </p>
                    </div>
                    <button
                        @click="openCreateCategoryModal"
                        class="inline-flex items-center px-4 py-2 bg-green-700 hover:bg-green-800 text-white text-sm font-semibold rounded-lg shadow-sm transition"
                    >
                        + Nueva Categoría
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="cat in categories"
                        :key="cat.id"
                        class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex justify-between items-start">
                                <h3 class="font-bold text-gray-900 text-base">{{ cat.name }}</h3>
                                <button
                                    @click="openEditCategoryModal(cat)"
                                    class="text-xs text-green-700 font-semibold"
                                >
                                    Editar
                                </button>
                            </div>
                            <div class="mt-3">
                                <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Materiales Permitidos:
                                </span>
                                <div class="flex flex-wrap gap-1.5 mt-1.5">
                                    <span
                                        v-for="m in cat.materials"
                                        :key="m.id"
                                        class="px-2 py-0.5 text-xs bg-green-50 text-green-800 border border-green-200 rounded-md font-medium"
                                    >
                                        {{ m.name }}
                                    </span>
                                    <span v-if="!cat.materials || cat.materials.length === 0" class="text-xs text-gray-400 italic">
                                        Ninguno asignado
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-gray-100 flex justify-between items-center text-xs">
                            <span :class="cat.activo ? 'text-green-700 font-bold' : 'text-gray-400 font-medium'">
                                {{ cat.activo ? '● Activa' : '○ Inactiva' }}
                            </span>
                            <button
                                @click="openAssignMaterialsModal(cat)"
                                class="text-blue-600 hover:text-blue-800 font-semibold"
                            >
                                Asignar Materiales
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==================================================== -->
            <!--             MODAL: CREAR / EDITAR COLOR              -->
            <!-- ==================================================== -->
            <div v-if="showColorModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                    <h3 class="text-lg font-bold text-gray-900">
                        {{ editingColor ? 'Editar Color' : 'Nuevo Color de Fábrica' }}
                    </h3>

                    <!-- Candado de renombrado advertencia -->
                    <div
                        v-if="editingColor && editingColor.has_history"
                        class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex items-start gap-2"
                    >
                        <span class="text-base">🔒</span>
                        <div>
                            <strong>Nombre bloqueado por historial (Regla A5):</strong>
                            Este color tiene ventas o existencias históricas registradas. Se prohíbe renombrarlo para evitar inconsistencias en reportes. Puedes actualizar su tono HEX o desactivarlo.
                        </div>
                    </div>

                    <form @submit.prevent="submitColorForm" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Nombre del Color
                            </label>
                            <input
                                v-model="colorForm.name"
                                type="text"
                                :disabled="Boolean(editingColor && editingColor.has_history)"
                                placeholder="Ej: Nogal Africano"
                                class="w-full text-sm rounded-lg border-gray-300 focus:ring-green-600 focus:border-green-600 disabled:bg-gray-100 disabled:text-gray-500"
                                required
                            />
                            <p v-if="colorForm.errors.name" class="text-xs text-red-600 mt-1">
                                {{ colorForm.errors.name }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Código Hexadecimal (#RRGGBB)
                            </label>
                            <div class="flex items-center gap-3">
                                <input
                                    v-model="colorForm.hex"
                                    type="color"
                                    class="w-12 h-10 p-0 rounded-lg border cursor-pointer border-gray-300"
                                />
                                <input
                                    v-model="colorForm.hex"
                                    type="text"
                                    placeholder="#4A1525"
                                    class="flex-1 text-sm font-mono uppercase rounded-lg border-gray-300 focus:ring-green-600 focus:border-green-600"
                                    pattern="^#[0-9A-Fa-f]{6}$"
                                    required
                                />
                            </div>
                            <p v-if="colorForm.errors.hex" class="text-xs text-red-600 mt-1">
                                {{ colorForm.errors.hex }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            <input
                                v-model="colorForm.activo"
                                id="color_activo"
                                type="checkbox"
                                class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                            />
                            <label for="color_activo" class="text-sm font-medium text-gray-700">
                                Color Activo para Producción y Ventas
                            </label>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <button
                                type="button"
                                @click="showColorModal = false"
                                class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="colorForm.processing"
                                class="px-4 py-2 text-sm bg-green-700 hover:bg-green-800 text-white font-bold rounded-lg transition"
                            >
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ==================================================== -->
            <!--            MODAL: CREAR / EDITAR MATERIAL            -->
            <!-- ==================================================== -->
            <div v-if="showMaterialModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                    <h3 class="text-lg font-bold text-gray-900">
                        {{ editingMaterial ? 'Configurar Material' : 'Nuevo Material' }}
                    </h3>

                    <form @submit.prevent="submitMaterialForm" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Nombre del Material
                            </label>
                            <input
                                v-model="materialForm.name"
                                type="text"
                                placeholder="Ej: Melamina, Pino, MDF"
                                class="w-full text-sm rounded-lg border-gray-300 focus:ring-green-600 focus:border-green-600"
                                required
                            />
                        </div>

                        <div class="space-y-3 pt-2">
                            <div class="flex items-start gap-2">
                                <input
                                    v-model="materialForm.color_obligatorio"
                                    id="mat_color_req"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-green-600 focus:ring-green-500 mt-1"
                                />
                                <label for="mat_color_req" class="text-xs text-gray-700">
                                    <strong class="text-gray-900">Color Obligatorio en Punto de Venta</strong>
                                    <p class="text-gray-500">Exige al vendedor seleccionar un color al vender muebles de este material.</p>
                                </label>
                            </div>

                            <div class="flex items-start gap-2">
                                <input
                                    v-model="materialForm.stock_por_color"
                                    id="mat_stock_color"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-green-600 focus:ring-green-500 mt-1"
                                />
                                <label for="mat_stock_color" class="text-xs text-gray-700">
                                    <strong class="text-gray-900">Inventario Atómico por Color</strong>
                                    <p class="text-gray-500">El stock físico se divide por color en almacén (ej: Melamina). Si se desactiva, el stock es global por variante.</p>
                                </label>
                            </div>

                            <div class="flex items-center gap-2">
                                <input
                                    v-model="materialForm.activo"
                                    id="mat_activo"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                                />
                                <label for="mat_activo" class="text-xs font-medium text-gray-700">
                                    Material Activo en Catálogo
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <button
                                type="button"
                                @click="showMaterialModal = false"
                                class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="materialForm.processing"
                                class="px-4 py-2 text-sm bg-green-700 hover:bg-green-800 text-white font-bold rounded-lg transition"
                            >
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ==================================================== -->
            <!--       MODAL: ASIGNAR COLORES A MATERIAL (PALETA)     -->
            <!-- ==================================================== -->
            <div v-if="showAssignColorsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4 max-h-[90vh] flex flex-col">
                    <h3 class="text-lg font-bold text-gray-900">
                        Asignar Paleta de Colores a {{ targetMaterial?.name }}
                    </h3>
                    <p class="text-xs text-gray-500">
                        Selecciona qué colores del catálogo global estarán disponibles para este material.
                    </p>

                    <div class="flex-1 overflow-y-auto space-y-2 pr-1 border-y py-3">
                        <label
                            v-for="color in colors"
                            :key="color.id"
                            class="flex items-center justify-between p-2.5 rounded-lg border hover:bg-gray-50 cursor-pointer"
                            :class="selectedColorIds.includes(color.id) ? 'border-green-500 bg-green-50/40' : 'border-gray-200'"
                        >
                            <div class="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    :value="color.id"
                                    v-model="selectedColorIds"
                                    class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                                />
                                <div
                                    class="w-5 h-5 rounded-full border border-gray-300"
                                    :style="{ backgroundColor: color.hex }"
                                ></div>
                                <span class="text-sm font-semibold text-gray-800">{{ color.name }}</span>
                            </div>
                            <span class="text-xs font-mono text-gray-400">{{ color.hex }}</span>
                        </label>
                    </div>

                    <div class="flex justify-between items-center pt-2">
                        <span class="text-xs text-gray-500">
                            {{ selectedColorIds.length }} seleccionados
                        </span>
                        <div class="flex gap-3">
                            <button
                                type="button"
                                @click="showAssignColorsModal = false"
                                class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                @click="submitAssignColors"
                                class="px-4 py-2 text-sm bg-green-700 hover:bg-green-800 text-white font-bold rounded-lg transition"
                            >
                                Guardar Paleta
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==================================================== -->
            <!--           MODAL: CREAR / EDITAR CATEGORÍA            -->
            <!-- ==================================================== -->
            <div v-if="showCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                    <h3 class="text-lg font-bold text-gray-900">
                        {{ editingCategory ? 'Editar Categoría' : 'Nueva Categoría' }}
                    </h3>

                    <form @submit.prevent="submitCategoryForm" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Nombre de la Categoría
                            </label>
                            <input
                                v-model="categoryForm.name"
                                type="text"
                                placeholder="Ej: Roperos, Comedores"
                                class="w-full text-sm rounded-lg border-gray-300 focus:ring-green-600 focus:border-green-600"
                                required
                            />
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            <input
                                v-model="categoryForm.activo"
                                id="cat_activo"
                                type="checkbox"
                                class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                            />
                            <label for="cat_activo" class="text-sm font-medium text-gray-700">
                                Categoría Activa
                            </label>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <button
                                type="button"
                                @click="showCategoryModal = false"
                                class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="categoryForm.processing"
                                class="px-4 py-2 text-sm bg-green-700 hover:bg-green-800 text-white font-bold rounded-lg transition"
                            >
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ==================================================== -->
            <!--     MODAL: ASIGNAR MATERIALES A CATEGORÍA (A11)      -->
            <!-- ==================================================== -->
            <div v-if="showAssignMaterialsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
                    <h3 class="text-lg font-bold text-gray-900">
                        Materiales Permitidos para {{ targetCategory?.name }}
                    </h3>
                    <p class="text-xs text-gray-500">
                        Marca los materiales con los que se puede fabricar y comercializar esta categoría de mueble.
                    </p>

                    <div class="space-y-2 border-y py-3 max-h-64 overflow-y-auto">
                        <label
                            v-for="mat in materials"
                            :key="mat.id"
                            class="flex items-center justify-between p-3 rounded-lg border hover:bg-gray-50 cursor-pointer"
                            :class="selectedMaterialIds.includes(mat.id) ? 'border-green-500 bg-green-50/50' : 'border-gray-200'"
                        >
                            <div class="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    :value="mat.id"
                                    v-model="selectedMaterialIds"
                                    class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                                />
                                <span class="text-sm font-semibold text-gray-900">{{ mat.name }}</span>
                            </div>
                            <span class="text-xs text-gray-500">
                                {{ mat.color_obligatorio ? 'Color requerido' : 'Color opcional' }}
                            </span>
                        </label>
                    </div>

                    <div class="flex justify-between items-center pt-2">
                        <span class="text-xs text-gray-500">
                            {{ selectedMaterialIds.length }} materiales asignados
                        </span>
                        <div class="flex gap-3">
                            <button
                                type="button"
                                @click="showAssignMaterialsModal = false"
                                class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                @click="submitAssignMaterials"
                                class="px-4 py-2 text-sm bg-green-700 hover:bg-green-800 text-white font-bold rounded-lg transition"
                            >
                                Guardar Asignación
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
