<script setup>
import { useForm, router, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import {
    validateEmail,
    validateName,
    validatePhone,
    validatePasswordMatch,
    validateSelect,
    passwordStrength,
    strengthLabel,
    strengthColor,
} from '@/composables/useFormValidation';

onMounted(() => {
    document.body.setAttribute('data-portal', 'teacher');
    document.body.style.background = '#FFF8F0';
    document.body.style.margin     = '0';
});

const form = useForm({
    name:                  '',
    email:                 '',
    phone:                 '',
    gender:                '',
    password:              '',
    password_confirmation: '',
});

const page = usePage();

const submitError = computed(() => {
    const firstFormError = Object.values(form.errors || {})[0];
    if (firstFormError) return firstFormError;
    return page.props.errors?.register || null;
});

const genderOptions = [
    { label: 'Male',   value: 'male'   },
    { label: 'Female', value: 'female' },
    { label: 'Other',  value: 'other'  },
];

const passwordPattern = '(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z0-9]).{12,}';

// — Local validation state —
const fieldErrors = ref({
    name: '', email: '', phone: '', gender: '', password_confirmation: '',
});
const showPassword        = ref(false);
const showPasswordConfirm = ref(false);

const pwStrength      = computed(() => passwordStrength(form.password));
const pwStrengthLabel = computed(() => strengthLabel[pwStrength.value]);
const pwStrengthColor = computed(() => strengthColor[pwStrength.value]);

const blurName    = () => { fieldErrors.value.name   = validateName(form.name, 'Full name').error || ''; };
const blurEmail   = () => { fieldErrors.value.email  = validateEmail(form.email).error || ''; };
const blurPhone   = () => { fieldErrors.value.phone  = validatePhone(form.phone).error || ''; };
const blurGender  = () => { fieldErrors.value.gender = validateSelect(form.gender, 'Please select a gender.').error || ''; };
const blurConfirm = () => {
    fieldErrors.value.password_confirmation = validatePasswordMatch(form.password, form.password_confirmation).error || '';
};

const validateAll = () => {
    blurName(); blurEmail(); blurPhone(); blurGender(); blurConfirm();
    return !Object.values(fieldErrors.value).some(Boolean);
};

const submit = () => {
    if (!validateAll()) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    form.post(route('teacher.register.submit'), {
        preserveScroll: true,
        onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
    });
};

const goToStudent = () => router.visit(route('student.register'), { preserveScroll: true });
</script>


<template>
    <div style="min-height:100vh; background:#FFF8F0; display:flex; align-items:center; justify-content:center; padding:40px 20px;">
        <div style="background:#fff; border:1px solid #F0E8E0; border-radius:10px; padding:40px; max-width:520px; width:100%;">
            <h1 style="font-family:'Fredoka One',cursive; font-size:28px; color:#E8553E; margin-bottom:6px;">Register as a Teacher</h1>
            <p style="font-family:'Nunito',sans-serif; font-size:18px; color:#888; margin-bottom:28px;">Share your knowledge with motivated students.</p>

            <div v-if="submitError" style="margin-bottom:20px; background:#FFF3EF; border:1px solid #F2B9AE; color:#AF2F1F; border-radius:10px; padding:12px 14px; font-family:'Nunito',sans-serif; font-size:15px;">
                {{ submitError }}
            </div>

            <!-- Toggle -->
            <div style="display:flex; gap:8px; margin-bottom:32px;">
                <button type="button" @click="goToStudent"
                    style="flex:1; padding:14px; min-height:56px; border:2px solid #E8553E; background:transparent; color:#E8553E; font-family:'Fredoka One',cursive; font-size:18px; border-radius:8px; cursor:pointer;">
                    🎒 Student
                </button>
                <button type="button"
                    style="flex:1; padding:14px; min-height:56px; border:2px solid #E8553E; background:#E8553E; color:#fff; font-family:'Fredoka One',cursive; font-size:18px; border-radius:8px; cursor:pointer;">
                    📖 Teacher
                </button>
            </div>

            <form @submit.prevent="submit" novalidate>
                <!-- Name -->
                <div style="margin-bottom:20px;">
                    <label for="tr-name" style="font-family:'Nunito',sans-serif; font-size:18px; font-weight:bold; color:#333; display:block; margin-bottom:8px;">
                        Full Name <span style="color:#E8553E;">*</span>
                    </label>
                    <input id="tr-name" v-model="form.name" type="text" name="name" autocomplete="name"
                        placeholder="Your full name" required minlength="2" maxlength="100"
                        :aria-invalid="(form.errors.name || fieldErrors.name) ? 'true' : 'false'"
                        aria-describedby="tr-name-error"
                        style="width:100%; padding:14px 16px; border:2px solid #F0E8E0; border-radius:8px; font-family:'Nunito',sans-serif; font-size:18px; min-height:56px; outline:none; box-sizing:border-box;"
                        @blur="blurName"
                        @focus="$event.target.style.borderColor='#E8553E'"
                    />
                    <div id="tr-name-error" role="alert" style="color:#c0392b; font-size:16px; margin-top:6px; min-height:20px;">
                        {{ form.errors.name || fieldErrors.name }}
                    </div>
                </div>

                <!-- Email -->
                <div style="margin-bottom:20px;">
                    <label for="tr-email" style="font-family:'Nunito',sans-serif; font-size:18px; font-weight:bold; color:#333; display:block; margin-bottom:8px;">
                        Email Address <span style="color:#E8553E;">*</span>
                    </label>
                    <input id="tr-email" v-model="form.email" type="email" name="email" autocomplete="username"
                        placeholder="you@example.com" required minlength="5" maxlength="254"
                        :aria-invalid="(form.errors.email || fieldErrors.email) ? 'true' : 'false'"
                        aria-describedby="tr-email-error"
                        style="width:100%; padding:14px 16px; border:2px solid #F0E8E0; border-radius:8px; font-family:'Nunito',sans-serif; font-size:18px; min-height:56px; outline:none; box-sizing:border-box;"
                        @blur="blurEmail"
                        @focus="$event.target.style.borderColor='#E8553E'"
                    />
                    <div id="tr-email-error" role="alert" style="color:#c0392b; font-size:16px; margin-top:6px; min-height:20px;">
                        {{ form.errors.email || fieldErrors.email }}
                    </div>
                </div>

                <!-- Phone -->
                <div style="margin-bottom:20px;">
                    <label for="tr-phone" style="font-family:'Nunito',sans-serif; font-size:18px; font-weight:bold; color:#333; display:block; margin-bottom:8px;">
                        Phone Number <span style="color:#E8553E;">*</span>
                    </label>
                    <input id="tr-phone" v-model="form.phone" type="tel" name="phone"
                        autocomplete="tel" inputmode="tel" placeholder="+91 98765 43210"
                        required maxlength="16"
                        :aria-invalid="(form.errors.phone || fieldErrors.phone) ? 'true' : 'false'"
                        aria-describedby="tr-phone-error"
                        style="width:100%; padding:14px 16px; border:2px solid #F0E8E0; border-radius:8px; font-family:'Nunito',sans-serif; font-size:18px; min-height:56px; outline:none; box-sizing:border-box;"
                        @blur="blurPhone"
                        @focus="$event.target.style.borderColor='#E8553E'"
                    />
                    <div id="tr-phone-error" role="alert" style="color:#c0392b; font-size:16px; margin-top:6px; min-height:20px;">
                        {{ form.errors.phone || fieldErrors.phone }}
                    </div>
                </div>

                <!-- Gender -->
                <div style="margin-bottom:20px;">
                    <label style="font-family:'Nunito',sans-serif; font-size:18px; font-weight:bold; color:#333; display:block; margin-bottom:12px;">
                        Gender <span style="color:#E8553E;">*</span>
                    </label>
                    <div role="radiogroup" aria-describedby="tr-gender-error" style="display:flex; flex-direction:column; gap:10px;">
                        <label v-for="option in genderOptions" :key="option.value"
                            :style="`display:flex; align-items:center; gap:16px; padding:16px 20px; border:2px solid ${form.gender===option.value ? '#E8553E' : '#F0E8E0'}; background:${form.gender===option.value ? '#FFF3EF' : '#fff'}; border-radius:8px; cursor:pointer; min-height:64px; font-family:'Nunito',sans-serif; font-size:18px; color:#333;`">
                            <input
                                type="radio"
                                name="gender"
                                :id="`teacher-gender-${option.value}`"
                                :value="option.value"
                                v-model="form.gender"
                                :required="option.value === genderOptions[0].value"
                                style="accent-color:#E8553E; flex-shrink:0; width:18px; height:18px; border-radius:50%;"
                                @change="blurGender"
                            />
                            {{ option.label }}
                        </label>
                    </div>
                    <div id="tr-gender-error" role="alert" style="color:#c0392b; font-size:16px; margin-top:6px; min-height:20px;">
                        {{ form.errors.gender || fieldErrors.gender }}
                    </div>
                </div>

                <!-- Password -->
                <div style="margin-bottom:20px;">
                    <label for="tr-password" style="font-family:'Nunito',sans-serif; font-size:18px; font-weight:bold; color:#333; display:block; margin-bottom:8px;">
                        Password <span style="color:#E8553E;">*</span>
                    </label>
                    <div style="position:relative;">
                        <input id="tr-password" v-model="form.password" :type="showPassword ? 'text' : 'password'"
                            name="password" autocomplete="new-password" placeholder="Create a strong password"
                            :pattern="passwordPattern" minlength="12" maxlength="128" required
                            :aria-invalid="form.errors.password ? 'true' : 'false'"
                            aria-describedby="tr-password-hint tr-password-error"
                            style="width:100%; padding:14px 48px 14px 16px; border:2px solid #F0E8E0; border-radius:8px; font-family:'Nunito',sans-serif; font-size:18px; min-height:56px; outline:none; box-sizing:border-box;"
                            @focus="$event.target.style.borderColor='#E8553E'"
                            @blur="$event.target.style.borderColor='#F0E8E0'"
                        />
                        <button type="button" @click="showPassword = !showPassword"
                            :aria-label="showPassword ? 'Hide password' : 'Show password'"
                            style="position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:20px; color:#888; padding:4px;">
                            {{ showPassword ? '🙈' : '👁' }}
                        </button>
                    </div>
                    <!-- Strength meter -->
                    <div v-if="form.password" style="margin-top:8px;">
                        <div style="display:flex; gap:4px; margin-bottom:4px;">
                            <div v-for="i in 4" :key="i"
                                :style="`flex:1; height:4px; border-radius:2px; background:${i <= pwStrength ? pwStrengthColor : '#e2e8f0'}; transition:background 0.3s;`">
                            </div>
                        </div>
                        <span :style="`font-family:'Nunito',sans-serif; font-size:13px; font-weight:600; color:${pwStrengthColor};`">{{ pwStrengthLabel }}</span>
                    </div>
                    <div id="tr-password-hint" style="color:#666; font-size:14px; margin-top:6px;">
                        At least 12 characters, including uppercase, lowercase, number, and symbol.
                    </div>
                    <div id="tr-password-error" role="alert" style="color:#c0392b; font-size:16px; margin-top:4px; min-height:20px;">
                        {{ form.errors.password }}
                    </div>
                </div>

                <!-- Confirm Password -->
                <div style="margin-bottom:32px;">
                    <label for="tr-confirm" style="font-family:'Nunito',sans-serif; font-size:18px; font-weight:bold; color:#333; display:block; margin-bottom:8px;">
                        Confirm Password <span style="color:#E8553E;">*</span>
                    </label>
                    <div style="position:relative;">
                        <input id="tr-confirm" v-model="form.password_confirmation"
                            :type="showPasswordConfirm ? 'text' : 'password'"
                            name="password_confirmation" autocomplete="new-password"
                            placeholder="Repeat your password" minlength="12" maxlength="128" required
                            :aria-invalid="(form.errors.password_confirmation || fieldErrors.password_confirmation) ? 'true' : 'false'"
                            aria-describedby="tr-confirm-error"
                            style="width:100%; padding:14px 48px 14px 16px; border:2px solid #F0E8E0; border-radius:8px; font-family:'Nunito',sans-serif; font-size:18px; min-height:56px; outline:none; box-sizing:border-box;"
                            @blur="blurConfirm"
                            @focus="$event.target.style.borderColor='#E8553E'"
                        />
                        <button type="button" @click="showPasswordConfirm = !showPasswordConfirm"
                            :aria-label="showPasswordConfirm ? 'Hide confirm password' : 'Show confirm password'"
                            style="position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:20px; color:#888; padding:4px;">
                            {{ showPasswordConfirm ? '🙈' : '👁' }}
                        </button>
                    </div>
                    <div id="tr-confirm-error" role="alert" style="color:#c0392b; font-size:16px; margin-top:6px; min-height:20px;">
                        {{ form.errors.password_confirmation || fieldErrors.password_confirmation }}
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" :disabled="form.processing"
                    style="width:100%; padding:18px; background:#E8553E; color:#fff; border:none; border-radius:8px; font-family:'Nunito',sans-serif; font-size:20px; font-weight:bold; cursor:pointer; min-height:56px;"
                    :style="form.processing ? 'opacity:0.7;cursor:not-allowed;' : ''">
                    {{ form.processing ? 'Creating Account…' : 'Register as Teacher' }}
                </button>

                <div style="text-align:center; margin-top:20px;">
                    <Link :href="route('login')" style="font-family:'Nunito',sans-serif; font-size:18px; color:#E8553E;">
                        Already have an account? Log in
                    </Link>
                </div>
            </form>

        </div>
    </div>
</template>
