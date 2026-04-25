<script setup>
const props = defineProps({
    preset: {
        type: String,
        default: '30d',
    },
    from: {
        type: String,
        default: '',
    },
    to: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:preset', 'update:from', 'update:to', 'change']);

const presets = [
    { key: 'today', label: 'Today' },
    { key: '7d', label: '7D' },
    { key: '30d', label: '30D' },
    { key: '90d', label: '90D' },
];

const onPresetSelect = (key) => {
    emit('update:preset', key);
    emit('change', { source: 'preset', value: key });
};

const onFromInput = (event) => {
    emit('update:from', event.target.value);
    emit('update:preset', 'custom');
    emit('change', { source: 'from', value: event.target.value });
};

const onToInput = (event) => {
    emit('update:to', event.target.value);
    emit('update:preset', 'custom');
    emit('change', { source: 'to', value: event.target.value });
};
</script>

<template>
    <div class="admin-date-range-picker">
        <div class="preset-row">
            <button
                v-for="item in presets"
                :key="item.key"
                type="button"
                class="preset-button"
                :class="{ active: preset === item.key }"
                @click="onPresetSelect(item.key)"
            >
                {{ item.label }}
            </button>
        </div>

        <div class="custom-row">
            <label>
                <span>From</span>
                <input class="date-field" type="date" :value="from" @input="onFromInput" />
            </label>
            <label>
                <span>To</span>
                <input class="date-field" type="date" :value="to" @input="onToInput" />
            </label>
        </div>
    </div>
</template>

<style scoped>
.admin-date-range-picker {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.preset-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.preset-button {
    min-height: 34px;
    border: 1px solid #F0E8E0;
    border-radius: 8px;
    background: #ffffff;
    color: #2D2D2D;
    padding: 0 12px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}

.preset-button.active {
    border-color: #E8553E;
    background: #eff6ff;
    color: #D44433;
}

.custom-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.custom-row label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #9CA3AF;
}

.date-field {
    min-height: 34px;
    width: 136px;
    border: 1px solid #F0E8E0;
    border-radius: 8px;
    background: #ffffff;
    padding: 0 8px;
    font-size: 12px;
    color: #2D2D2D;
}

@media (max-width: 900px) {
    .custom-row {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
