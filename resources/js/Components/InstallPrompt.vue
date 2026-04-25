<script setup>
import { onMounted, ref } from 'vue';

const showPrompt = ref(false);
let deferredPrompt = null;

onMounted(() => {
    // Check visit count
    let visits = parseInt(localStorage.getItem('pwa_visits') || '0', 10);
    visits++;
    localStorage.setItem('pwa_visits', visits.toString());

    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent the mini-infobar from appearing on mobile
        e.preventDefault();
        // Stash the event so it can be triggered later.
        deferredPrompt = e;

        if (visits >= 3) {
            showPrompt.value = true;
        }
    });
});

const installApp = async () => {
    if (!deferredPrompt) return;
    
    // Show the install prompt
    deferredPrompt.prompt();
    
    // Wait for the user to respond to the prompt
    const { outcome } = await deferredPrompt.userChoice;
    
    // We no longer need the prompt. Clear it up.
    deferredPrompt = null;
    showPrompt.value = false;
};

const dismissPrompt = () => {
    showPrompt.value = false;
};
</script>

<template>
    <div v-if="showPrompt" class="install-prompt-banner">
        <div class="content">
            <span class="icon">📱</span>
            <span>Add EduBridge to your home screen for quick access!</span>
        </div>
        <div class="actions">
            <button @click="installApp" class="btn-add">Add</button>
            <button @click="dismissPrompt" class="btn-dismiss">Not Now</button>
        </div>
    </div>
</template>

<style scoped>
.install-prompt-banner {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    background: #E8553E;
    color: white;
    padding: 16px 24px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(232, 85, 62, 0.4);
    display: flex;
    align-items: center;
    gap: 24px;
    z-index: 10000;
    max-width: 90vw;
    font-family: 'Nunito', sans-serif;
}

.content {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 16px;
    font-weight: 600;
}

.icon {
    font-size: 24px;
}

.actions {
    display: flex;
    gap: 12px;
}

button {
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
}

.btn-add {
    background: white;
    color: #E8553E;
}

.btn-add:hover {
    background: #fdfdfd;
    transform: translateY(-1px);
}

.btn-dismiss {
    background: transparent;
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.5);
}

.btn-dismiss:hover {
    background: rgba(255, 255, 255, 0.1);
}

@media (max-width: 600px) {
    .install-prompt-banner {
        flex-direction: column;
        text-align: center;
        width: calc(100% - 32px);
        bottom: 16px;
    }
}
</style>
