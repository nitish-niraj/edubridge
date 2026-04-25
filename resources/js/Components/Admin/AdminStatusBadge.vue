<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        default: '',
    },
});

const normalized = computed(() => String(props.status || '').toLowerCase());

const tone = computed(() => {
    const map = {
        active: 'active',
        pending: 'pending',
        suspended: 'suspended',
        verified: 'verified',
    };

    return map[normalized.value] || 'default';
});
</script>

<template>
    <span class="admin-status-badge" :class="`tone-${tone}`">
        {{ normalized || 'unknown' }}
    </span>
</template>

<style scoped>
.admin-status-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 3px 10px;
    font-size: 12px;
    line-height: 1.2;
    font-weight: 700;
    text-transform: capitalize;
    white-space: nowrap;
}

.admin-status-badge.tone-active {
    background: #dcfce7;
    color: #16a34a;
}

.admin-status-badge.tone-pending {
    background: #fef3c7;
    color: #d97706;
}

.admin-status-badge.tone-suspended {
    background: #fee2e2;
    color: #dc2626;
}

.admin-status-badge.tone-verified {
    background: #FFE7DD;
    color: #E8553E;
}

.admin-status-badge.tone-default {
    background: #e2e8f0;
    color: #2D2D2D;
}
</style>
