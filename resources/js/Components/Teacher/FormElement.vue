<template>
    <div class="flex flex-col mb-8">
        <label :for="id" class="block font-arial font-bold text-[#111827] text-[20px] mb-2 leading-tight">
            {{ label }}
        </label>
        <span v-if="helpText" class="text-[#6B7280] font-arial text-[18px] mb-4 leading-snug">
            {{ helpText }}
        </span>
        
        <slot>
            <input 
                :id="id"
                :type="type"
                :value="modelValue"
                @input="$emit('update:modelValue', $event.target.value)"
                class="w-full bg-white border border-[#D1D5DB] rounded-[4px] px-4 font-arial text-[20px] text-[#111827] min-h-[56px] focus:outline-none focus:ring-2 focus:ring-[#3D6B4F] focus:border-[#3D6B4F] shadow-sm transition-shadow duration-0 placeholder:text-[#9CA3AF]"
                v-bind="$attrs"
            />
        </slot>
    </div>
</template>

<script setup>
defineProps({
    id: String,
    label: String,
    helpText: String,
    modelValue: [String, Number],
    type: {
        type: String,
        default: 'text'
    }
});
defineEmits(['update:modelValue']);
</script>
