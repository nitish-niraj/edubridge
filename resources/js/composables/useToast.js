import { ref } from 'vue';

const toasts = ref([]);
let toastIdCounter = 0;
const timers = new Map();

const clearToastTimer = (id) => {
  if (!timers.has(id)) return;
  window.clearTimeout(timers.get(id));
  timers.delete(id);
};

export function useToast() {
  const addToast = ({ message, type = 'info', duration = 4000 }) => {
    const id = toastIdCounter++;
    const toast = {
      id,
      message,
      type,
      duration
    };
    
    toasts.value.push(toast);
    
    if (duration > 0) {
      const timer = window.setTimeout(() => {
        removeToast(id);
      }, duration);
      timers.set(id, timer);
    }
    
    return id;
  };

  const removeToast = (id) => {
    clearToastTimer(id);

    const index = toasts.value.findIndex(t => t.id === id);
    if (index !== -1) {
      toasts.value.splice(index, 1);
    }
  };

  const success = (message, duration) => addToast({ message, type: 'success', duration });
  const error = (message, duration) => addToast({ message, type: 'error', duration });
  const info = (message, duration) => addToast({ message, type: 'info', duration });
  const warning = (message, duration) => addToast({ message, type: 'warning', duration });
  const clearAll = () => {
    toasts.value.forEach((toast) => clearToastTimer(toast.id));
    toasts.value = [];
  };

  return {
    toasts,
    addToast,
    removeToast,
    success,
    error,
    info,
    warning,
    clearAll
  };
}
