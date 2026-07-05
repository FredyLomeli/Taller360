<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, nextTick } from 'vue';

const props = defineProps({
    productionQueue: Object,
    reportDate: String,
    weekRange: Object
});

onMounted(() => {
    nextTick(() => {
        setTimeout(() => {
            window.print();
        }, 800);
    });
});
</script>

<template>
    <Head title="Reporte de Producción" />
    
    <div class="max-w-4xl mx-auto p-4 bg-white text-black min-h-screen">
        
        <!-- Encabezado más compacto -->
        <div class="border-b-2 border-black pb-2 mb-4 flex justify-between items-end">
            <div>
                <h1 class="text-2xl font-black uppercase tracking-tight">Orden de Producción</h1>
                <p class="text-xs font-bold text-gray-600">Semana del {{ weekRange.start }} al {{ weekRange.end }}</p>
            </div>
            <div class="text-right text-[10px]">
                <p><strong>Emisión:</strong> {{ reportDate }}</p>
                <p><strong>Firma:</strong> _______________________</p>
            </div>
        </div>

        <!-- Tabla ultra compacta para maximizar artículos por página -->
        <table class="w-full text-xs border-collapse border border-black">
            <thead>
                <tr class="bg-gray-200">
                    <th class="border border-black px-1 py-1 text-left">Modelo / Material</th>
                    <th class="border border-black px-1 py-1 text-center w-10 text-[10px] leading-tight">Req.</th>
                    <th class="border border-black px-1 py-1 text-center w-10 text-[10px] leading-tight">Bodega</th>
                    <th class="border border-black px-1 py-1 text-center w-10 text-[10px] leading-tight">Fab.</th>
                    <th class="border border-black px-1 py-1 text-center w-10 text-[10px] leading-tight">Check</th>
                    <th class="border border-black px-1 py-1 text-center w-16 text-[10px]">Fecha</th>
                    <th class="border border-black px-1 py-1 text-left min-w-[120px] text-[10px]">Notas / Comentarios</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(group, key) in productionQueue" :key="key">
                    <td class="border border-black px-2 py-1.5">
                        <span class="font-bold uppercase text-[11px] leading-none block">{{ group.name }}</span>
                        <span class="text-[9px] text-gray-600 block mt-0.5">Mat: {{ group.material }}</span>
                    </td>
                    <td class="border border-black px-1 py-1.5 text-center font-bold">{{ group.total_needed }}</td>
                    <td class="border border-black px-1 py-1.5 text-center">{{ group.in_stock }}</td>
                    <td class="border border-black px-1 py-1.5 text-center font-black">{{ group.pending_to_fabricate }}</td>
                    <td class="border border-black px-1 py-1.5 text-center align-middle">
                        <div class="w-4 h-4 border border-black rounded mx-auto"></div>
                    </td>
                    <td class="border border-black px-1 py-1.5 text-center"></td>
                    <td class="border border-black px-1 py-1.5"></td>
                </tr>
            </tbody>
        </table>

        <!-- Notas al pie -->
        <div class="mt-4 text-[10px] text-gray-500 text-center">
            Documento para control interno. Las piezas "En Bodega" deben prepararse para embarque.
        </div>
    </div>
</template>

<style scoped>
@media print {
    body { background-color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    /* Eliminamos 'landscape' y dejamos márgenes pequeños para que quepan más filas */
    @page { margin: 0.5cm; size: portrait; } 
}
</style>