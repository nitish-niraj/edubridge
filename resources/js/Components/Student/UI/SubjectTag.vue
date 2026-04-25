<script setup>
import { computed, useAttrs } from 'vue';

const props = defineProps({
  subject: {
    type: String,
    required: true,
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md'].includes(v)
  }
});

const subjectColors = {
  Math:       { bg: '#5BC4E5', text: '#FFFFFF' },
  Science:    { bg: '#4CB87E', text: '#FFFFFF' },
  Physics:    { bg: '#4CB87E', text: '#FFFFFF' },
  Chemistry:  { bg: '#9B72CF', text: '#FFFFFF' },
  Biology:    { bg: '#4CB87E', text: '#FFFFFF' },
  English:    { bg: '#F5C518', text: '#2D2D2D' },
  Hindi:      { bg: '#E8553E', text: '#FFFFFF' },
  Punjabi:    { bg: '#E8553E', text: '#FFFFFF' },
  History:    { bg: '#FFAB76', text: '#2D2D2D' },
  Geography:  { bg: '#5BC4E5', text: '#FFFFFF' },
  Economics:  { bg: '#9B72CF', text: '#FFFFFF' },
  Commerce:   { bg: '#F5C518', text: '#2D2D2D' },
  Computer:   { bg: '#5BC4E5', text: '#FFFFFF' },
  default:    { bg: '#E8553E', text: '#FFFFFF' },
};

const attrs = useAttrs();

const colors = computed(() => {
    return subjectColors[props.subject] || subjectColors.default;
});

const isClickable = computed(() => {
    return Object.keys(attrs).some((key) => key.toLowerCase() === 'onclick');
});
</script>

<template>
    <span
        class="subject-tag"
        :class="[`size-${size}`, { 'is-clickable': isClickable }]"
        :style="{ backgroundColor: colors.bg, color: colors.text }"
        v-bind="attrs"
    >
        {{ subject }}
    </span>
</template>

<style scoped>
.subject-tag {
    display: inline-block;
    font-family: var(--s-font-body, 'Nunito', sans-serif);
    font-weight: 600;
    border-radius: 100px;
    transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    will-change: transform, box-shadow;
    white-space: nowrap;
    cursor: default;
}

.subject-tag.size-sm {
    font-size: 11px;
    padding: 2px 8px;
}

.subject-tag.size-md {
    font-size: 12px;
    padding: 4px 12px;
}

.subject-tag:hover {
    transform: scale(1.08);
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.16);
}

.subject-tag.is-clickable {
    cursor: pointer;
}
</style>
