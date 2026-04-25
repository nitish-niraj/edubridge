<script setup>

const props = defineProps({
  label: String,
  modelValue: {
    type: [String, Number],
    default: ''
  },
  type: {
    type: String,
    default: 'text'
  },
  placeholder: String,
  error: String,
  icon: String
});

const emit = defineEmits(['update:modelValue']);

function onInput(event) {
  emit('update:modelValue', event.target.value);
}
</script>

<template>
  <div class="s-input-wrapper" :class="{ 'has-error': Boolean(error) }">
    <label v-if="label" class="s-input-label">{{ label }}</label>
    
    <div class="s-input-container" :class="{ 'has-icon': Boolean($slots.icon || icon) }">
      <div v-if="$slots.icon || icon" class="s-input-icon">
        <slot name="icon">
          <span v-if="icon">{{ icon }}</span>
        </slot>
      </div>

      <input
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        class="s-input-field"
        @input="onInput"
        v-bind="$attrs"
      />
      
      <span class="s-input-glow" aria-hidden="true"></span>
    </div>
    
    <transition name="slide-fade">
      <p v-if="error" class="s-input-error">{{ error }}</p>
    </transition>
  </div>
</template>

<style scoped>
.s-input-wrapper {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
}

.s-input-label {
  font-family: var(--s-font-body, 'Nunito', sans-serif);
  font-weight: 600;
  font-size: 16px;
  color: #2D2D2D;
  margin: 0;
}

.s-input-container {
  position: relative;
  width: 100%;
}

.s-input-field {
  width: 100%;
  height: 52px;
  padding: 0 16px;
  background: #FFFFFF;
  border: 2px solid #E8E8E8;
  border-radius: 14px;
  font-family: var(--s-font-body, 'Nunito', sans-serif);
  font-size: 16px;
  color: #2D2D2D;
  outline: none;
  transition: border-color 0.2s ease;
  position: relative;
  z-index: 3;
  box-sizing: border-box;
}

.s-input-field::placeholder {
  color: #9CA3AF;
}

.s-input-container.has-icon .s-input-field {
  padding-left: 44px; /* Room for icon */
}

.s-input-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #9CA3AF;
  transition: color 0.2s ease;
  z-index: 4;
  display: flex;
  align-items: center;
  justify-content: center;
}

.s-input-glow {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  border-radius: 14px;
  box-shadow: 0 0 0 4px rgba(232, 85, 62, 0.12);
  transition: transform 0.2s ease, opacity 0.2s ease;
  pointer-events: none;
  z-index: 2;
  transform: scale(0);
  opacity: 0;
  transform-origin: center;
}

.s-input-container:focus-within .s-input-field {
  border-color: #E8553E;
}

.s-input-container:focus-within .s-input-icon {
  color: #E8553E;
}

.s-input-container:focus-within .s-input-glow {
  transform: scale(1);
  opacity: 1;
}

.has-error .s-input-field {
  border-color: #DC2626;
}

.has-error .s-input-container:focus-within .s-input-glow {
  box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.12);
}

.has-error .s-input-icon {
  color: #DC2626;
}

.s-input-error {
  font-family: var(--s-font-body, 'Nunito', sans-serif);
  font-size: 14px;
  color: #DC2626;
  margin: 0;
  padding-left: 4px;
}

/* Error Slide-Fade Animation */
.slide-fade-enter-active {
  transition: all 0.3s var(--s-spring);
}
.slide-fade-leave-active {
  transition: all 0.2s ease-in;
}
.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateY(-4px);
  opacity: 0;
}
</style>
