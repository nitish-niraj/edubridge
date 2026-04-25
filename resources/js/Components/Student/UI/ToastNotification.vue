<script setup>
import { useToast } from '@/composables/useToast';

const { toasts, removeToast } = useToast();

const getIcon = (type) => {
  switch (type) {
    case 'success': return '✓';
    case 'error': return '✕';
    case 'warning': return '⚠';
    case 'info':
    default: return 'i';
  }
};
</script>

<template>
  <div class="toast-container">
    <transition-group name="toast-list" tag="div" class="toast-wrapper">
      <div 
        v-for="toast in toasts" 
        :key="toast.id" 
        class="toast-item"
        :class="`toast--${toast.type}`"
      >
        <div class="toast-content">
          <span class="toast-icon">{{ getIcon(toast.type) }}</span>
          <p class="toast-message">{{ toast.message }}</p>
          <button type="button" class="toast-close" @click="removeToast(toast.id)">×</button>
        </div>
        <div 
          class="toast-progress" 
          :style="{ animationDuration: `${toast.duration}ms` }"
        ></div>
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-container {
  position: fixed;
  top: 24px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 9999;
  display: flex;
  flex-direction: column;
  pointer-events: none;
}

.toast-wrapper {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: center;
}

.toast-item {
  position: relative;
  overflow: hidden;
  border-radius: 50px;
  pointer-events: auto;
  min-width: 300px;
  max-width: 90vw;
  box-shadow: var(--s-shadow-float);
}

/* Colors matching student spec */
.toast--success {
  background-color: var(--s-mint);
  color: var(--s-white);
}
.toast--error {
  background-color: var(--s-coral);
  color: var(--s-white);
}
.toast--info {
  background-color: var(--s-sky);
  color: var(--s-white);
}
.toast--warning {
  background-color: var(--s-yellow);
  color: var(--s-text);
}

.toast-content {
  display: flex;
  align-items: center;
  padding: 14px 24px;
  gap: 12px;
}

.toast-icon {
  font-weight: bold;
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
}

.toast--warning .toast-icon {
  background: rgba(0, 0, 0, 0.1);
}

.toast-message {
  font-family: var(--s-font-body);
  font-weight: 600;
  font-size: 16px;
  margin: 0;
  flex-grow: 1;
}

.toast-close {
  background: transparent;
  border: none;
  color: inherit;
  font-size: 20px;
  cursor: pointer;
  opacity: 0.7;
  transition: opacity 0.2s;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.toast-close:hover {
  opacity: 1;
}

.toast-progress {
  position: absolute;
  bottom: 0;
  left: 0;
  height: 3px;
  background: rgba(255, 255, 255, 0.5);
  animation: shrink linear forwards;
}

.toast--warning .toast-progress {
  background: rgba(0, 0, 0, 0.2);
}

@keyframes shrink {
  from { width: 100%; }
  to { width: 0; }
}

/* Vue Transition Group Classes */
.toast-list-enter-active {
  transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.toast-list-leave-active {
  transition: transform 0.25s ease-in, opacity 0.25s ease-in;
}
.toast-list-enter-from {
  opacity: 0;
  transform: translateY(-120%);
}
.toast-list-leave-to {
  opacity: 0;
  transform: translateY(-120%);
}
/* Ensure smooth sliding when items are removed */
.toast-list-move {
  transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}
</style>
