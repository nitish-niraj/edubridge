<script setup>
// Barrel export file for student UI
export { default as SButton } from './SButton.vue'
export { default as SInput } from './SInput.vue'
export { default as SubjectTag } from './SubjectTag.vue'
export { default as ToastNotification } from './ToastNotification.vue'
// StarRating is in Components/Shared, so we pull it from there if needed here, 
// though usually better to import directly from Shared.
