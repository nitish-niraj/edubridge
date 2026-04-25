<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

onMounted(() => {
    document.body.setAttribute('data-portal', 'student');
});

const currentStep = ref(0);

const steps = [
    {
        emoji:    '🔍',
        headline: 'Find your perfect teacher 🔍',
        body:     'Browse hundreds of experienced retired teachers. Filter by subject, language, and budget.',
    },
    {
        emoji:    '💬',
        headline: 'Chat before you book 💬',
        body:     'Have a free introductory chat with any teacher before committing to a session.',
    },
    {
        emoji:    '🎥',
        headline: 'Learn live on video 🎥',
        body:     'Join live one-on-one video sessions from the comfort of your home.',
    },
    {
        emoji:    '🌟',
        headline: 'Rate & grow 🌟',
        body:     'After each session, rate your teacher and track your learning progress.',
    },
];

const next = () => {
    if (currentStep.value < steps.length - 1) {
        currentStep.value++;
    } else {
        complete();
    }
};

const complete = () => {
    router.post(route('student.onboarding.complete'));
};
</script>

<template>
    <StudentLayout>
        <div class="onboarding-page">
            <div class="onboarding-card">
                <div class="step-emoji">{{ steps[currentStep].emoji }}</div>

                <h1>{{ steps[currentStep].headline }}</h1>

                <p>
                    {{ steps[currentStep].body }}
                </p>

                <div class="step-row">
                    <div
                        v-for="(_, i) in steps"
                        :key="i"
                        class="step-dot"
                        :class="{ active: i === currentStep }"
                    />
                </div>

                <button class="primary-btn" @click="next">
                    {{ currentStep < steps.length - 1 ? 'Next →' : "Let's Begin!" }}
                </button>

                <button class="skip-btn" @click="complete" type="button">
                    Skip
                </button>
            </div>
        </div>
    </StudentLayout>
</template>

<style scoped>
.onboarding-page {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 24px;
    background:
        radial-gradient(900px 420px at 90% -10%, rgba(245, 197, 24, 0.18), transparent 60%),
        radial-gradient(700px 380px at -15% 25%, rgba(232, 85, 62, 0.15), transparent 55%),
        #fff8f0;
}

.onboarding-card {
    width: min(540px, 100%);
    border-radius: 20px;
    background: linear-gradient(145deg, #ffffff 0%, #fff7f2 100%);
    border: 1px solid #f3ddd4;
    box-shadow: 0 10px 30px rgba(232, 85, 62, 0.12);
    text-align: center;
    padding: 36px 28px;
}

.step-emoji {
    font-size: 68px;
    margin-bottom: 12px;
}

h1 {
    margin: 0 0 10px;
    font-family: 'Fredoka One', cursive;
    font-size: clamp(24px, 3.7vw, 30px);
    color: #e8553e;
    line-height: 1.3;
}

p {
    margin: 0 0 22px;
    font-family: Nunito, sans-serif;
    font-size: 16px;
    color: #59667a;
    line-height: 1.6;
}

.step-row {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-bottom: 20px;
}

.step-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #f0ddd5;
    transition: all 220ms ease;
}

.step-dot.active {
    width: 24px;
    background: #e8553e;
}

.primary-btn,
.skip-btn {
    border: none;
    cursor: pointer;
}

.primary-btn {
    width: 100%;
    min-height: 48px;
    padding: 12px 16px;
    border-radius: 999px;
    background: #e8553e;
    color: #fff;
    font-family: Nunito, sans-serif;
    font-size: 18px;
    font-weight: 800;
}

.skip-btn {
    margin-top: 12px;
    background: transparent;
    color: #98a2b3;
    text-decoration: underline;
    font-family: Nunito, sans-serif;
    font-size: 15px;
}

@media (max-width: 640px) {
    .onboarding-page {
        padding: 14px;
        place-items: start;
    }

    .onboarding-card {
        margin-top: 12px;
        padding: 24px 18px;
    }

    .step-emoji {
        font-size: 56px;
        margin-bottom: 10px;
    }

    h1 {
        font-size: 24px;
    }

    p {
        font-size: 15px;
        line-height: 1.55;
        margin-bottom: 18px;
    }

    .step-row {
        margin-bottom: 16px;
    }

    .primary-btn {
        min-height: 46px;
        font-size: 16px;
    }
}
</style>
