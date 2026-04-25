<script setup>
import { useForm, router, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import {
    validateEmail,
    validateName,
    validatePhone,
    validatePasswordMatch,
    passwordStrength,
    strengthLabel,
    strengthColor,
    validateSelect,
} from '@/composables/useFormValidation';

defineProps({
    status: { type: String, default: null },
});

const page = usePage();
const googleError  = computed(() => page.props.errors?.google || null);
const accountError = computed(() => page.props.errors?.email  || null);
const submitError  = computed(() => {
    const firstFormError = Object.values(form.errors || {})[0];
    return firstFormError || null;
});

onMounted(() => {
    document.body.setAttribute('data-portal', 'student');
    document.body.style.margin     = '0';
    document.body.style.background = '#FFF8F0';
});

const form = useForm({
    name:                  '',
    email:                 '',
    phone:                 '',
    password:              '',
    password_confirmation: '',
    class_grade:           '',
    school_name:           '',
});

// — Local validation errors —
const fieldErrors = ref({
    name: '', email: '', phone: '', password: '', password_confirmation: '', class_grade: '',
});
const showPassword        = ref(false);
const showPasswordConfirm = ref(false);

// Strength meter
const pwStrength      = computed(() => passwordStrength(form.password));
const pwStrengthLabel = computed(() => strengthLabel[pwStrength.value]);
const pwStrengthColor = computed(() => strengthColor[pwStrength.value]);

const blurName     = () => { fieldErrors.value.name  = validateName(form.name,  'Full name').error  || ''; };
const blurEmail    = () => { fieldErrors.value.email = validateEmail(form.email).error || ''; };
const blurPhone    = () => { fieldErrors.value.phone = validatePhone(form.phone).error || ''; };
const blurConfirm  = () => {
    fieldErrors.value.password_confirmation = validatePasswordMatch(form.password, form.password_confirmation).error || '';
};
const blurGrade    = () => { fieldErrors.value.class_grade = validateSelect(form.class_grade, 'Please select your class/grade.').error || ''; };

const passwordPattern = '(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z0-9]).{12,}';

const validateAll = () => {
    blurName(); blurEmail(); blurPhone(); blurGrade(); blurConfirm();
    return !Object.values(fieldErrors.value).some(Boolean);
};

const submit = () => {
    if (!validateAll()) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    form.post(route('student.register.submit'), {
        preserveScroll: true,
        onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
    });
};

const goToTeacher = () => router.visit(route('teacher.register'), { preserveScroll: true });

const grades = [
    'Class 1','Class 2','Class 3','Class 4','Class 5','Class 6',
    'Class 7','Class 8','Class 9','Class 10','Class 11','Class 12',
    'Undergraduate','Postgraduate',
];
</script>


<template>
    <div style="min-height:100vh; background:#FFF8F0; display:flex; align-items:stretch; margin:0;">
        <!-- Left: Illustration -->
        <div class="hidden md:flex" style="width:55%; background: linear-gradient(135deg,#E8553E 0%,#FFAB76 50%,#F5C518 100%); align-items:center; justify-content:center; flex-direction:column; padding:48px;">
            <div style="font-size:120px; opacity:0.3;">📚</div>
            <h2 style="font-family:'Fredoka One',cursive; font-size:36px; color:#fff; text-align:center; margin-top:24px; text-shadow:0 2px 8px rgba(0,0,0,0.15);">
                Learn from the best teachers!
            </h2>
            <p style="font-family:Nunito,sans-serif; font-size:18px; color:rgba(255,255,255,0.85); text-align:center; margin-top:12px; max-width:380px;">
                Connect with experienced retired teachers for live, one-on-one tutoring sessions.
            </p>
        </div>

        <!-- Right: Registration Card -->
        <div style="flex:1; display:flex; align-items:center; justify-content:center; padding:32px;">
            <div style="background:#fff; border-radius:20px; box-shadow:0 4px 20px rgba(232,85,62,0.10); padding:40px; width:100%; max-width:480px;">
                <h1 style="font-family:'Fredoka One',cursive; font-size:32px; color:#E8553E; text-align:center; margin-bottom:8px;">
                    Join EduBridge 🎒
                </h1>
                <p style="font-family:Nunito,sans-serif; font-size:16px; color:#888; text-align:center; margin-bottom:24px;">
                    Start learning today!
                </p>

                <div v-if="googleError || accountError" style="background:#FFF3EF; border:1px solid #E8553E; border-radius:10px; padding:12px 14px; margin-bottom:18px; font-family:Nunito,sans-serif; font-size:14px; color:#E8553E;">
                    {{ googleError || accountError }}
                </div>

                <div v-else-if="submitError" style="background:#FFF3EF; border:1px solid #E8553E; border-radius:10px; padding:12px 14px; margin-bottom:18px; font-family:Nunito,sans-serif; font-size:14px; color:#E8553E;">
                    {{ submitError }}
                </div>

                <!-- Toggle: Student / Teacher -->
                <div style="display:flex; gap:8px; margin-bottom:28px;">
                    <button type="button"
                        style="flex:1; padding:12px; border-radius:999px; border:2px solid #E8553E; background:#E8553E; color:#fff; font-family:'Fredoka One',cursive; font-size:16px; cursor:pointer;">
                        🎒 I'm a Student
                    </button>
                    <button type="button" @click="goToTeacher"
                        style="flex:1; padding:12px; border-radius:999px; border:2px solid #E8553E; background:transparent; color:#E8553E; font-family:'Fredoka One',cursive; font-size:16px; cursor:pointer;">
                        📖 I'm a Teacher
                    </button>
                </div>

                <form @submit.prevent="submit" novalidate>
                    <!-- Name -->
                    <div style="margin-bottom:16px;">
                        <label for="sr-name" style="font-family:Nunito,sans-serif; font-size:14px; font-weight:600; color:#555; display:block; margin-bottom:6px;">
                            Full Name <span style="color:#E8553E;">*</span>
                        </label>
                        <input id="sr-name" v-model="form.name" name="name" type="text" autocomplete="name"
                            placeholder="Your full name" required minlength="2" maxlength="100"
                            :aria-invalid="(form.errors.name || fieldErrors.name) ? 'true' : 'false'"
                            aria-describedby="sr-name-error"
                            style="width:100%; padding:12px 16px; border:2px solid #F0DDD5; border-radius:14px; font-family:Nunito,sans-serif; font-size:16px; outline:none; box-sizing:border-box;"
                            @blur="blurName"
                            @focus="$event.target.style.borderColor='#E8553E'"
                        />
                        <div id="sr-name-error" role="alert" style="color:#E8553E; font-size:13px; margin-top:4px; min-height:18px;">
                            {{ form.errors.name || fieldErrors.name }}
                        </div>
                    </div>

                    <!-- Email -->
                    <div style="margin-bottom:16px;">
                        <label for="sr-email" style="font-family:Nunito,sans-serif; font-size:14px; font-weight:600; color:#555; display:block; margin-bottom:6px;">
                            Email Address <span style="color:#E8553E;">*</span>
                        </label>
                        <input id="sr-email" v-model="form.email" name="email" type="email" autocomplete="username"
                            placeholder="you@example.com" required minlength="5" maxlength="254"
                            :aria-invalid="(form.errors.email || fieldErrors.email) ? 'true' : 'false'"
                            aria-describedby="sr-email-error"
                            style="width:100%; padding:12px 16px; border:2px solid #F0DDD5; border-radius:14px; font-family:Nunito,sans-serif; font-size:16px; outline:none; box-sizing:border-box;"
                            @blur="blurEmail"
                            @focus="$event.target.style.borderColor='#E8553E'"
                        />
                        <div id="sr-email-error" role="alert" style="color:#E8553E; font-size:13px; margin-top:4px; min-height:18px;">
                            {{ form.errors.email || fieldErrors.email }}
                        </div>
                    </div>

                    <!-- Phone -->
                    <div style="margin-bottom:16px;">
                        <label for="sr-phone" style="font-family:Nunito,sans-serif; font-size:14px; font-weight:600; color:#555; display:block; margin-bottom:6px;">
                            Phone Number <span style="color:#E8553E;">*</span>
                        </label>
                        <input id="sr-phone" v-model="form.phone" name="phone" type="tel"
                            autocomplete="tel" inputmode="tel" placeholder="+91 98765 43210"
                            required maxlength="16"
                            :aria-invalid="(form.errors.phone || fieldErrors.phone) ? 'true' : 'false'"
                            aria-describedby="sr-phone-error"
                            style="width:100%; padding:12px 16px; border:2px solid #F0DDD5; border-radius:14px; font-family:Nunito,sans-serif; font-size:16px; outline:none; box-sizing:border-box;"
                            @blur="blurPhone"
                            @focus="$event.target.style.borderColor='#E8553E'"
                        />
                        <div id="sr-phone-error" role="alert" style="color:#E8553E; font-size:13px; margin-top:4px; min-height:18px;">
                            {{ form.errors.phone || fieldErrors.phone }}
                        </div>
                    </div>

                    <!-- Class/Grade -->
                    <div style="margin-bottom:16px;">
                        <label for="sr-grade" style="font-family:Nunito,sans-serif; font-size:14px; font-weight:600; color:#555; display:block; margin-bottom:6px;">
                            Class / Grade <span style="color:#E8553E;">*</span>
                        </label>
                        <select id="sr-grade" v-model="form.class_grade" name="class_grade" required
                            :aria-invalid="(form.errors.class_grade || fieldErrors.class_grade) ? 'true' : 'false'"
                            aria-describedby="sr-grade-error"
                            style="width:100%; padding:12px 16px; border:2px solid #F0DDD5; border-radius:14px; font-family:Nunito,sans-serif; font-size:16px; outline:none; box-sizing:border-box; background:#fff;"
                            @change="blurGrade"
                        >
                            <option value="">Select your class</option>
                            <option v-for="g in grades" :key="g" :value="g">{{ g }}</option>
                        </select>
                        <div id="sr-grade-error" role="alert" style="color:#E8553E; font-size:13px; margin-top:4px; min-height:18px;">
                            {{ form.errors.class_grade || fieldErrors.class_grade }}
                        </div>
                    </div>

                    <!-- School Name -->
                    <div style="margin-bottom:16px;">
                        <label for="sr-school" style="font-family:Nunito,sans-serif; font-size:14px; font-weight:600; color:#555; display:block; margin-bottom:6px;">School / College Name</label>
                        <input id="sr-school" v-model="form.school_name" name="school_name" type="text"
                            placeholder="Your school or college" maxlength="150"
                            style="width:100%; padding:12px 16px; border:2px solid #F0DDD5; border-radius:14px; font-family:Nunito,sans-serif; font-size:16px; outline:none; box-sizing:border-box;"
                            @focus="$event.target.style.borderColor='#E8553E'"
                            @blur="$event.target.style.borderColor='#F0DDD5'"
                        />
                    </div>

                    <!-- Password -->
                    <div style="margin-bottom:16px;">
                        <label for="sr-password" style="font-family:Nunito,sans-serif; font-size:14px; font-weight:600; color:#555; display:block; margin-bottom:6px;">
                            Password <span style="color:#E8553E;">*</span>
                        </label>
                        <div style="position:relative;">
                            <input id="sr-password" v-model="form.password" name="password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="new-password" placeholder="Create a password"
                                :pattern="passwordPattern" minlength="12" maxlength="128" required
                                :aria-invalid="form.errors.password ? 'true' : 'false'"
                                aria-describedby="sr-password-hint sr-password-error"
                                style="width:100%; padding:12px 48px 12px 16px; border:2px solid #F0DDD5; border-radius:14px; font-family:Nunito,sans-serif; font-size:16px; outline:none; box-sizing:border-box;"
                                @focus="$event.target.style.borderColor='#E8553E'"
                                @blur="$event.target.style.borderColor='#F0DDD5'"
                            />
                            <button type="button" @click="showPassword = !showPassword"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                style="position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:18px; color:#888; padding:4px;">
                                {{ showPassword ? '🙈' : '👁' }}
                            </button>
                        </div>
                        <!-- Strength meter (Rulebook §4) -->
                        <div v-if="form.password" style="margin-top:8px;">
                            <div style="display:flex; gap:4px; margin-bottom:4px;">
                                <div v-for="i in 4" :key="i"
                                    :style="`flex:1; height:4px; border-radius:2px; background:${i <= pwStrength ? pwStrengthColor : '#e2e8f0'}; transition:background 0.3s;`">
                                </div>
                            </div>
                            <div :style="`font-family:Nunito,sans-serif; font-size:13px; color:${pwStrengthColor}; font-weight:600;`">
                                {{ pwStrengthLabel }}
                            </div>
                        </div>
                        <div id="sr-password-hint" style="color:#666; font-size:13px; margin-top:4px;">
                            At least 12 characters, including uppercase, lowercase, number, and symbol.
                        </div>
                        <div id="sr-password-error" role="alert" style="color:#E8553E; font-size:13px; margin-top:2px; min-height:16px;">
                            {{ form.errors.password }}
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div style="margin-bottom:24px;">
                        <label for="sr-confirm" style="font-family:Nunito,sans-serif; font-size:14px; font-weight:600; color:#555; display:block; margin-bottom:6px;">
                            Confirm Password <span style="color:#E8553E;">*</span>
                        </label>
                        <div style="position:relative;">
                            <input id="sr-confirm" v-model="form.password_confirmation" name="password_confirmation"
                                :type="showPasswordConfirm ? 'text' : 'password'"
                                autocomplete="new-password" placeholder="Repeat your password"
                                minlength="12" maxlength="128" required
                                :aria-invalid="(form.errors.password_confirmation || fieldErrors.password_confirmation) ? 'true' : 'false'"
                                aria-describedby="sr-confirm-error"
                                style="width:100%; padding:12px 48px 12px 16px; border:2px solid #F0DDD5; border-radius:14px; font-family:Nunito,sans-serif; font-size:16px; outline:none; box-sizing:border-box;"
                                @blur="blurConfirm"
                                @focus="$event.target.style.borderColor='#E8553E'"
                            />
                            <button type="button" @click="showPasswordConfirm = !showPasswordConfirm"
                                :aria-label="showPasswordConfirm ? 'Hide confirm password' : 'Show confirm password'"
                                style="position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:18px; color:#888; padding:4px;">
                                {{ showPasswordConfirm ? '🙈' : '👁' }}
                            </button>
                        </div>
                        <div id="sr-confirm-error" role="alert" style="color:#E8553E; font-size:13px; margin-top:4px; min-height:18px;">
                            {{ form.errors.password_confirmation || fieldErrors.password_confirmation }}
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" :disabled="form.processing"
                        style="width:100%; padding:16px; background:#E8553E; color:#fff; border:none; border-radius:999px; font-family:'Fredoka One',cursive; font-size:20px; cursor:pointer; transition:opacity 0.2s;"
                        :style="form.processing ? 'opacity:0.7;cursor:not-allowed;' : ''">
                        {{ form.processing ? 'Creating Account…' : 'Create Account 🚀' }}
                    </button>

                    <!-- Google OAuth -->
                    <div style="text-align:center; margin-top:20px;">
                        <div style="font-family:Nunito,sans-serif; font-size:14px; color:#aaa; margin-bottom:12px;">OR</div>
                        <a :href="route('auth.google', { source: 'register' })"
                            style="display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:12px; border:2px solid #F0DDD5; border-radius:999px; font-family:Nunito,sans-serif; font-size:16px; color:#555; text-decoration:none; background:#fff;">
                            <img src="https://www.google.com/favicon.ico" width="18" height="18" alt="Google">
                            Continue with Google
                        </a>
                    </div>

                    <div style="text-align:center; margin-top:16px;">
                        <Link :href="route('login')" style="font-family:Nunito,sans-serif; font-size:14px; color:#E8553E; text-decoration:none;">
                            Already have an account? Log in
                        </Link>
                    </div>
                </form>

            </div>
        </div>
    </div>
</template>
