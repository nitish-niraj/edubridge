<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';

const page = usePage();
const userId = computed(() => page.props.user_id);
const userRole = computed(() => page.props.role || 'student');
const isTeacher = computed(() => userRole.value === 'teacher');
const statusMessage = computed(() => page.props.status || '');

const digits = ref(['', '', '', '', '', '']);
const inputs = ref([]);
const timer = ref(60);
const canResend = ref(false);
const successMsg = ref('');
const errorMsg = ref('');

let countdown = null;

onMounted(() => {
    document.body.setAttribute('data-portal', isTeacher.value ? 'teacher' : 'student');
    document.body.style.background = isTeacher.value ? '#FFF8F0' : '#FFF8F0';
    document.body.style.margin = '0';

    if (statusMessage.value) {
        successMsg.value = statusMessage.value;
    }

    if (!userId.value) {
        errorMsg.value = 'Verification session expired. Please register or login again.';
    }

    // Focus first box
    if (inputs.value[0]) inputs.value[0].focus();

    countdown = setInterval(() => {
        if (timer.value > 0) {
            timer.value--;
        } else {
            canResend.value = true;
            clearInterval(countdown);
        }
    }, 1000);
});

onUnmounted(() => {
    if (countdown) {
        clearInterval(countdown);
    }
});

const handleInput = (index, event) => {
    const val = event.target.value.replace(/\D/, '');
    digits.value[index] = val.slice(-1);
    if (val && index < 5) {
        inputs.value[index + 1]?.focus();
    }
};

const handleKeydown = (index, event) => {
    if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
        inputs.value[index - 1]?.focus();
    }
};

const form = useForm({ otp: '', user_id: '' });

const launchConfetti = async () => {
    try {
        const confettiModule = await import('canvas-confetti');
        const confetti = confettiModule.default;

        confetti({
            particleCount: 120,
            spread: 70,
            origin: { y: 0.6 },
            colors: ['#E8553E', '#F5C518', '#4CB87E', '#FFAB76'],
        });
    } catch (e) {
        // Graceful fallback when confetti package is not available.
    }
};

const verifyOtp = () => {
    errorMsg.value = '';

    if (!userId.value) {
        errorMsg.value = 'Verification session expired. Please register or login again.';
        return;
    }

    const otp = digits.value.join('');
    if (otp.length < 6) {
        errorMsg.value = 'Please enter all 6 digits.';
        return;
    }
    form.user_id = userId.value;
    form.otp = otp;
    form.post(route('verify.otp.submit'), {
        onSuccess: async () => {
            if (!isTeacher.value) {
                await launchConfetti();
            }
        },
        onError: (errors) => {
            errorMsg.value = errors.otp || errors.user_id || Object.values(errors)[0] || 'Invalid or expired code. Please try again.';
        },
    });
};

const resendOtp = () => {
    if (!canResend.value) return;
    canResend.value = false;
    timer.value = 60;
    const resendForm = useForm({ user_id: userId.value });
    resendForm.post(route('verify.otp.resend'), {
        onSuccess: () => {
            successMsg.value = 'Code resent successfully!';
            countdown = setInterval(() => {
                if (timer.value > 0) timer.value--;
                else { canResend.value = true; clearInterval(countdown); }
            }, 1000);
        },
        onError: () => { errorMsg.value = 'Failed to resend. Try again.'; canResend.value = true; },
    });
};
</script>

<template>
    <!-- STUDENT OTP design -->
    <div v-if="!isTeacher" style="min-height:100vh; background:#FFF8F0; display:flex; align-items:center; justify-content:center; padding:20px;">
        <div style="background:#fff; border-radius:20px; box-shadow:0 4px 20px rgba(232,85,62,0.10); padding:48px 40px; max-width:480px; width:100%; text-align:center;">
            <div style="font-size:60px; margin-bottom:16px;">📬</div>
            <h1 style="font-family:'Fredoka One',cursive; font-size:28px; color:#E8553E; margin-bottom:8px;">Check your inbox!</h1>
            <p style="font-family:Nunito,sans-serif; font-size:16px; color:#888; margin-bottom:32px;">
                We sent a 6-digit code. Enter it below to verify your account.
            </p>

            <!-- 6 digit boxes -->
            <div style="display:flex; gap:10px; justify-content:center; margin-bottom:24px;">
                <input
                    v-for="(digit, idx) in digits"
                    :key="idx"
                    :ref="el => inputs[idx] = el"
                    v-model="digits[idx]"
                    type="text"
                    inputmode="numeric"
                    maxlength="1"
                    @input="handleInput(idx, $event)"
                    @keydown="handleKeydown(idx, $event)"
                    style="width:60px; height:72px; text-align:center; font-family:'Fredoka One',cursive; font-size:32px; border:2px solid #F0DDD5; border-radius:12px; outline:none; color:#E8553E;"
                    @focus="$event.target.style.borderColor='#E8553E'; $event.target.style.background='#FFF3EF'"
                    @blur="$event.target.style.borderColor='#F0DDD5'; $event.target.style.background='#fff'"
                />
            </div>

            <!-- Error / Success -->
            <div v-if="errorMsg" style="color:#E8553E; font-family:Nunito,sans-serif; font-size:15px; margin-bottom:12px; background:#FFF3EF; padding:10px; border-radius:10px;">{{ errorMsg }}</div>
            <div v-if="successMsg" style="color:#4CB87E; font-family:Nunito,sans-serif; font-size:15px; margin-bottom:12px;">{{ successMsg }}</div>

            <!-- Timer -->
            <div style="font-family:Nunito,sans-serif; font-size:14px; color:#aaa; margin-bottom:20px;">
                <span v-if="!canResend">Resend in {{ timer }}s</span>
                <button v-else @click="resendOtp" type="button"
                    style="background:none; border:none; color:#E8553E; font-family:Nunito,sans-serif; font-size:14px; cursor:pointer; text-decoration:underline; padding:0;">
                    Resend Code
                </button>
            </div>

            <!-- Verify button -->
            <button @click="verifyOtp" :disabled="form.processing"
                style="width:100%; padding:16px; background:#E8553E; color:#fff; border:none; border-radius:999px; font-family:'Fredoka One',cursive; font-size:20px; cursor:pointer;">
                {{ form.processing ? 'Verifying...' : 'Verify ✓' }}
            </button>
        </div>
    </div>

    <!-- TEACHER OTP design -->
    <div v-else class="teacher-otp-shell">
        <div class="teacher-otp-glow teacher-otp-glow--top" aria-hidden="true"></div>
        <div class="teacher-otp-glow teacher-otp-glow--bottom" aria-hidden="true"></div>

        <div class="teacher-otp-card">
            <p class="teacher-otp-kicker">Secure verification</p>
            <h1 class="teacher-otp-title">Enter the 6-digit code</h1>
            <p class="teacher-otp-subtitle">
                We sent a verification code to your email address.
            </p>

            <div class="teacher-otp-inputs">
                <input
                    v-for="(digit, idx) in digits"
                    :key="idx"
                    :ref="el => inputs[idx] = el"
                    v-model="digits[idx]"
                    type="text"
                    inputmode="numeric"
                    maxlength="1"
                    autocomplete="one-time-code"
                    class="teacher-otp-input"
                    @input="handleInput(idx, $event)"
                    @keydown="handleKeydown(idx, $event)"
                />
            </div>

            <div v-if="errorMsg" class="teacher-otp-alert teacher-otp-alert--error">{{ errorMsg }}</div>
            <div v-if="successMsg" class="teacher-otp-alert teacher-otp-alert--success">{{ successMsg }}</div>

            <button
                @click="verifyOtp"
                :disabled="form.processing"
                class="teacher-otp-verify"
            >
                {{ form.processing ? 'Verifying...' : 'Verify Code' }}
            </button>

            <div class="teacher-otp-resend-row">
                <span v-if="!canResend" class="teacher-otp-resend-note">Resend code in {{ timer }}s</span>
                <button v-else @click="resendOtp" type="button" class="teacher-otp-resend-button">
                    Resend code
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.teacher-otp-shell {
    position: relative;
    isolation: isolate;
    min-height: 100vh;
    background:
        radial-gradient(circle at 18% 16%, rgba(245, 181, 29, 0.2), transparent 38%),
        radial-gradient(circle at 84% 82%, rgba(38, 93, 151, 0.14), transparent 42%),
        #fff8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    overflow: hidden;
}

.teacher-otp-glow {
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
    z-index: -1;
    filter: blur(2px);
}

.teacher-otp-glow--top {
    width: 360px;
    height: 360px;
    top: -170px;
    right: -110px;
    background: radial-gradient(circle at 35% 35%, rgba(43, 95, 152, 0.2), rgba(43, 95, 152, 0));
}

.teacher-otp-glow--bottom {
    width: 340px;
    height: 340px;
    bottom: -170px;
    left: -120px;
    background: radial-gradient(circle at 65% 65%, rgba(232, 85, 62, 0.24), rgba(232, 85, 62, 0));
}

.teacher-otp-card {
    width: min(100%, 620px);
    padding: clamp(24px, 4vw, 44px);
    border-radius: 24px;
    border: 1px solid #d9e6f4;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(255, 255, 255, 0.93));
    box-shadow:
        0 24px 44px rgba(23, 51, 81, 0.18),
        inset 0 1px 0 rgba(255, 255, 255, 0.8);
    text-align: center;
}

.teacher-otp-kicker {
    margin: 0 0 10px;
    font-family: 'Nunito', sans-serif;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #2f5f98;
}

.teacher-otp-title {
    margin: 0;
    font-family: 'Fredoka One', cursive;
    font-size: clamp(2rem, 4vw, 2.6rem);
    color: #e8553e;
    line-height: 1.06;
}

.teacher-otp-subtitle {
    margin: 14px auto 28px;
    max-width: 480px;
    font-family: 'Nunito', sans-serif;
    font-size: clamp(1rem, 2.1vw, 1.25rem);
    color: #475569;
    line-height: 1.4;
}

.teacher-otp-inputs {
    display: flex;
    justify-content: center;
    gap: clamp(7px, 1.8vw, 12px);
    margin-bottom: 22px;
}

.teacher-otp-input {
    width: clamp(44px, 10vw, 72px);
    height: clamp(56px, 12vw, 80px);
    border: 2px solid #d4e3f3;
    border-radius: 14px;
    background: #ffffff;
    text-align: center;
    font-family: 'Fredoka One', cursive;
    font-size: clamp(1.55rem, 3.4vw, 2rem);
    color: #e8553e;
    outline: none;
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}

.teacher-otp-input:focus,
.teacher-otp-input:focus-visible {
    border-color: #2f68c6;
    box-shadow: 0 0 0 4px rgba(47, 104, 198, 0.16);
    transform: translateY(-1px);
}

.teacher-otp-alert {
    margin: 0 0 16px;
    padding: 12px 14px;
    border-radius: 12px;
    font-family: 'Nunito', sans-serif;
    font-size: clamp(0.98rem, 1.9vw, 1.1rem);
    line-height: 1.38;
}

.teacher-otp-alert--error {
    border: 1px solid #f1c3bb;
    background: #fff3ef;
    color: #b93826;
}

.teacher-otp-alert--success {
    border: 1px solid #cde9de;
    background: #effaf5;
    color: #1f7f57;
}

.teacher-otp-verify {
    width: 100%;
    min-height: 58px;
    border: none;
    border-radius: 16px;
    background: linear-gradient(140deg, #e8553e 0%, #cf3f2d 100%);
    box-shadow: 0 14px 24px rgba(192, 60, 40, 0.26);
    color: #ffffff;
    font-family: 'Nunito', sans-serif;
    font-size: clamp(1.05rem, 2.2vw, 1.35rem);
    font-weight: 800;
    cursor: pointer;
    transition: transform .18s ease, box-shadow .18s ease, opacity .18s ease;
}

.teacher-otp-verify:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 18px 30px rgba(192, 60, 40, 0.3);
}

.teacher-otp-verify:disabled {
    opacity: 0.72;
    cursor: not-allowed;
}

.teacher-otp-resend-row {
    margin-top: 16px;
    min-height: 28px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.teacher-otp-resend-note {
    font-family: 'Nunito', sans-serif;
    font-size: clamp(0.95rem, 1.9vw, 1.05rem);
    color: #596d82;
}

.teacher-otp-resend-button {
    border: none;
    background: none;
    color: #2f68c6;
    font-family: 'Nunito', sans-serif;
    font-size: clamp(0.95rem, 1.9vw, 1.06rem);
    font-weight: 700;
    text-decoration: underline;
    cursor: pointer;
    padding: 0;
}

@media (max-width: 640px) {
    .teacher-otp-shell {
        padding: 14px;
    }

    .teacher-otp-card {
        border-radius: 18px;
    }

    .teacher-otp-inputs {
        gap: 6px;
    }
}
</style>
