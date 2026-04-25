<script setup>
import { ChevronDownIcon } from '@heroicons/vue/24/solid';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    columns: {
        type: Array,
        required: true,
    },
    rows: {
        type: Array,
        default: () => [],
    },
    rowKey: {
        type: String,
        default: 'id',
    },
    selectable: {
        type: Boolean,
        default: false,
    },
    selectedKeys: {
        type: Array,
        default: () => [],
    },
    selectedRowKey: {
        type: [Number, String],
        default: null,
    },
    sortState: {
        type: Object,
        default: () => ({ key: null, direction: 'asc' }),
    },
    emptyText: {
        type: String,
        default: 'No records found.',
    },
});

const emit = defineEmits(['update:selectedKeys', 'sort-change', 'row-click']);

const internalSort = ref({ key: null, direction: 'asc' });
const flashingRowKey = ref(null);
const cascadeActive = ref(false);
const cascadeToken = ref(0);
const rowElements = ref([]);

watch(
    () => props.sortState,
    (nextSort) => {
        if (!nextSort || !nextSort.key) {
            return;
        }

        internalSort.value = {
            key: nextSort.key,
            direction: nextSort.direction === 'desc' ? 'desc' : 'asc',
        };
    },
    { deep: true, immediate: true }
);

watch(
    () => props.rows,
    () => {
        rowElements.value = [];
    }
);

const activeSort = computed(() => {
    if (props.sortState?.key) {
        return {
            key: props.sortState.key,
            direction: props.sortState.direction === 'desc' ? 'desc' : 'asc',
        };
    }

    return internalSort.value;
});

const modelSelectedKeys = computed({
    get: () => props.selectedKeys,
    set: (nextKeys) => emit('update:selectedKeys', nextKeys),
});

const normalizedRows = computed(() => (Array.isArray(props.rows) ? props.rows : []));

const getRowKey = (row) => row?.[props.rowKey];

const sortedRows = computed(() => {
    const sortKey = activeSort.value?.key;

    if (!sortKey) {
        return normalizedRows.value;
    }

    const column = props.columns.find((entry) => entry.key === sortKey);
    const accessor = typeof column?.sortAccessor === 'function'
        ? column.sortAccessor
        : (row) => row?.[sortKey];

    const sorted = [...normalizedRows.value].sort((left, right) => {
        const leftValue = accessor(left);
        const rightValue = accessor(right);

        if (leftValue == null && rightValue == null) return 0;
        if (leftValue == null) return -1;
        if (rightValue == null) return 1;

        if (typeof leftValue === 'number' && typeof rightValue === 'number') {
            return leftValue - rightValue;
        }

        return String(leftValue).localeCompare(String(rightValue), undefined, { sensitivity: 'base', numeric: true });
    });

    return activeSort.value.direction === 'desc' ? sorted.reverse() : sorted;
});

const selectedSet = computed(() => new Set(modelSelectedKeys.value));

const selectedRows = computed(() => sortedRows.value.filter((row) => selectedSet.value.has(getRowKey(row))));

const hasBulkSelection = computed(() => Boolean(props.selectable && modelSelectedKeys.value.length));

const allVisibleSelected = computed(() => {
    if (!sortedRows.value.length) {
        return false;
    }

    return sortedRows.value.every((row) => selectedSet.value.has(getRowKey(row)));
});

const isSortable = (column) => Boolean(column?.sortable);

const sortDirectionFor = (column) => {
    if (activeSort.value.key !== column.key) {
        return null;
    }

    return activeSort.value.direction;
};

const triggerCascade = () => {
    cascadeToken.value += 1;
    cascadeActive.value = true;

    const duration = sortedRows.value.length * 20 + 240;
    window.setTimeout(() => {
        if (cascadeToken.value > 0) {
            cascadeActive.value = false;
        }
    }, duration);
};

const toggleSort = (column) => {
    if (!isSortable(column)) {
        return;
    }

    const nextDirection = activeSort.value.key === column.key && activeSort.value.direction === 'asc'
        ? 'desc'
        : 'asc';

    internalSort.value = { key: column.key, direction: nextDirection };
    emit('sort-change', { key: column.key, direction: nextDirection });
};

const toggleRow = (rowKey) => {
    const nextSet = new Set(modelSelectedKeys.value);

    if (nextSet.has(rowKey)) {
        nextSet.delete(rowKey);
    } else {
        nextSet.add(rowKey);
    }

    modelSelectedKeys.value = Array.from(nextSet);
};

const toggleAllVisible = () => {
    const visibleKeys = sortedRows.value.map((row) => getRowKey(row));
    const nextSet = new Set(modelSelectedKeys.value);

    if (allVisibleSelected.value) {
        visibleKeys.forEach((rowKey) => nextSet.delete(rowKey));
    } else {
        visibleKeys.forEach((rowKey) => nextSet.add(rowKey));
    }

    modelSelectedKeys.value = Array.from(nextSet);
    triggerCascade();
};

const setRowRef = (element, index) => {
    if (element) {
        rowElements.value[index] = element;
    }
};

const onRowClick = (row, event) => {
    const rowKey = getRowKey(row);
    flashingRowKey.value = rowKey;

    window.setTimeout(() => {
        if (flashingRowKey.value === rowKey) {
            flashingRowKey.value = null;
        }
    }, 220);

    emit('row-click', row, event);
};

const onRowKeydown = (event, row, index) => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        onRowClick(row, event);
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        rowElements.value[index + 1]?.focus();
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        rowElements.value[index - 1]?.focus();
    }
};

const checkboxCascadeStyle = (index) => {
    if (!cascadeActive.value) {
        return {};
    }

    return {
        animationDelay: `${index * 20}ms`,
    };
};

const getCellValue = (row, column) => {
    if (typeof column.value === 'function') {
        return column.value(row);
    }

    return row?.[column.key] ?? '';
};
</script>

<template>
    <div class="admin-table-shell" :class="{ 'bulk-active': hasBulkSelection }">
        <div class="admin-table-wrap" :class="{ 'bulk-active': hasBulkSelection }">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th v-if="selectable" class="checkbox-column">
                            <label class="admin-checkbox" data-prevent-row-click>
                                <input
                                    type="checkbox"
                                    :checked="allVisibleSelected"
                                    aria-label="Select all rows"
                                    @change="toggleAllVisible"
                                />
                                <span class="admin-checkbox-box">
                                    <svg viewBox="0 0 12 10" fill="none" aria-hidden="true">
                                        <path d="M1 5L4.2 8.2L11 1.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                            </label>
                        </th>

                        <th
                            v-for="column in columns"
                            :key="column.key"
                            :style="column.width ? { width: column.width } : undefined"
                            :class="[
                                column.align ? `align-${column.align}` : '',
                                isSortable(column) ? 'sortable' : '',
                            ]"
                        >
                            <button
                                v-if="isSortable(column)"
                                type="button"
                                class="sort-button"
                                @click="toggleSort(column)"
                            >
                                <span>{{ column.label }}</span>
                                <ChevronDownIcon
                                    class="sort-arrow"
                                    :class="{
                                        active: sortDirectionFor(column),
                                        descending: sortDirectionFor(column) === 'desc',
                                    }"
                                />
                            </button>
                            <span v-else>{{ column.label }}</span>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-if="!sortedRows.length">
                        <td :colspan="columns.length + (selectable ? 1 : 0)" class="empty-row">
                            {{ emptyText }}
                        </td>
                    </tr>

                    <tr
                        v-for="(row, index) in sortedRows"
                        :key="getRowKey(row)"
                        :ref="(element) => setRowRef(element, index)"
                        class="data-row"
                        :class="{
                            selected: selectedRowKey === getRowKey(row) || selectedSet.has(getRowKey(row)),
                            flashing: flashingRowKey === getRowKey(row),
                        }"
                        tabindex="0"
                        @click="onRowClick(row, $event)"
                        @keydown="onRowKeydown($event, row, index)"
                    >
                        <td v-if="selectable" class="checkbox-column" @click.stop>
                            <label class="admin-checkbox" data-prevent-row-click>
                                <input
                                    type="checkbox"
                                    :checked="selectedSet.has(getRowKey(row))"
                                    :aria-label="`Select row ${index + 1}`"
                                    @change="toggleRow(getRowKey(row))"
                                />
                                <span
                                    class="admin-checkbox-box"
                                    :class="{ cascading: cascadeActive }"
                                    :style="checkboxCascadeStyle(index)"
                                >
                                    <svg viewBox="0 0 12 10" fill="none" aria-hidden="true">
                                        <path d="M1 5L4.2 8.2L11 1.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                            </label>
                        </td>

                        <td
                            v-for="column in columns"
                            :key="`${getRowKey(row)}-${column.key}`"
                            :class="column.align ? `align-${column.align}` : ''"
                        >
                            <slot
                                :name="`cell-${column.key}`"
                                :row="row"
                                :value="getCellValue(row, column)"
                                :column="column"
                            >
                                {{ getCellValue(row, column) }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Transition name="admin-bulk-toolbar">
            <div v-if="selectable && modelSelectedKeys.length" class="admin-bulk-toolbar">
                <div class="bulk-count">{{ modelSelectedKeys.length }} selected</div>
                <div class="bulk-actions">
                    <slot name="bulk-actions" :selected-keys="modelSelectedKeys" :selected-rows="selectedRows" />
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.admin-table-shell {
    border: 1px solid #F0E8E0;
    border-radius: 8px;
    background: #ffffff;
    overflow: hidden;
}

.admin-table-wrap {
    overflow-x: auto;
    padding-bottom: 0;
    transition: padding-bottom 200ms ease-out;
}

.admin-table-wrap.bulk-active {
    padding-bottom: 56px;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.admin-table thead tr {
    background: #FFF8F0;
    border-bottom: 1px solid #e2e8f0;
}

.admin-table th {
    padding: 12px 16px;
    text-align: left;
    font-size: 12px;
    line-height: 1.2;
    font-weight: 600;
    color: #9CA3AF;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    white-space: nowrap;
}

.admin-table th.align-right,
.admin-table td.align-right {
    text-align: right;
}

.admin-table th.align-center,
.admin-table td.align-center {
    text-align: center;
}

.admin-table th.checkbox-column,
.admin-table td.checkbox-column {
    width: 40px;
    min-width: 40px;
    padding: 12px 10px;
}

.sort-button {
    border: none;
    background: transparent;
    padding: 0;
    font: inherit;
    color: inherit;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
}

.sort-arrow {
    width: 13px;
    height: 13px;
    color: #9CA3AF;
    transition: transform 200ms ease, color 200ms ease;
    transform: rotate(0deg);
}

.sort-arrow.active {
    color: #475569;
}

.sort-arrow.descending {
    transform: rotate(180deg);
}

.data-row {
    border-bottom: 1px solid #f1f5f9;
    min-height: 52px;
    transition: background-color 100ms ease;
    cursor: pointer;
}

.data-row:hover {
    background: #FFF8F0;
}

.data-row.selected {
    background: #eff6ff;
}

.data-row.flashing {
    animation: row-flash 220ms ease;
}

.data-row:focus-visible {
    outline: 2px solid #E8553E;
    outline-offset: -2px;
}

.admin-table td {
    padding: 12px 16px;
    font-size: 14px;
    color: #2D2D2D;
    vertical-align: middle;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.empty-row {
    text-align: center;
    color: #9CA3AF;
    font-size: 14px;
    padding: 22px 16px;
}

.admin-checkbox {
    position: relative;
    width: 16px;
    height: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.admin-checkbox input {
    position: absolute;
    inset: 0;
    margin: 0;
    opacity: 0;
    cursor: pointer;
}

.admin-checkbox-box {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    border: 2px solid #F0E8E0;
    background: #ffffff;
    color: transparent;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: border-color 150ms ease, background-color 150ms ease, color 150ms ease;
}

.admin-checkbox-box svg {
    width: 10px;
    height: 10px;
}

.admin-checkbox input:checked + .admin-checkbox-box {
    border-color: #E8553E;
    background: #E8553E;
    color: #ffffff;
}

.admin-checkbox-box.cascading {
    animation: checkbox-cascade 180ms ease;
}

.admin-bulk-toolbar {
    position: fixed;
    left: 240px;
    right: 0;
    bottom: 0;
    z-index: 85;
    height: 56px;
    border-top: 1px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    gap: 12px;
}

.bulk-count {
    font-size: 14px;
    color: #2D2D2D;
    font-weight: 600;
}

.bulk-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.admin-bulk-toolbar-enter-active,
.admin-bulk-toolbar-leave-active {
    transition: transform 200ms ease-out;
}

.admin-bulk-toolbar-enter-from,
.admin-bulk-toolbar-leave-to {
    transform: translateY(100%);
}

@keyframes row-flash {
    0% {
        background: #eff6ff;
    }
    100% {
        background: transparent;
    }
}

@keyframes checkbox-cascade {
    0% {
        transform: scale(1);
    }
    40% {
        transform: scale(1.12);
    }
    100% {
        transform: scale(1);
    }
}
</style>
