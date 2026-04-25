<template>
    <div class="bg-white rounded-md border border-[#E2E8F0] shadow-sm overflow-hidden font-inter text-[13px]">
        <table class="min-w-full divide-y divide-[#E2E8F0]">
            <thead class="bg-[#F8FAFC]">
                <tr>
                    <th v-for="(col, i) in columns" :key="i" scope="col" class="px-6 py-3.5 text-left font-medium text-[#64748B] uppercase tracking-wider text-[11px]">
                        {{ col.label }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#E2E8F0] bg-white">
                <tr v-for="(row, idx) in data" :key="row.id || idx" 
                    @click="$emit('row-click', row)"
                    class="transition-colors duration-100 ease-in-out hover:bg-[#F1F5F9] cursor-pointer"
                    :class="{'bg-[#F1F5F9]': selectedRowId === row.id}">
                    
                    <td v-for="(col, i) in columns" :key="i" class="px-6 py-4 whitespace-nowrap">
                        <slot :name="`cell-${col.key}`" :row="row">
                            <span class="text-[#0F172A]">{{ row[col.key] }}</span>
                        </slot>
                    </td>
                </tr>
            </tbody>
        </table>
        <div v-if="data.length === 0" class="p-8 text-center text-[#64748B] text-[13px]">
            No records found.
        </div>
    </div>
</template>

<script setup>
defineProps({
    columns: Array, // [{ key: 'name', label: 'Name' }]
    data: Array,
    selectedRowId: [String, Number]
});

defineEmits(['row-click']);
</script>
