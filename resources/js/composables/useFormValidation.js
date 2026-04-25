/**
 * useFormValidation.js
 *
 * Shared, reusable validation helpers for every form in EduBridge.
 * All validators return { valid: boolean, error: string|null }.
 *
 * Rulebook reference: docs/form-validation-rulebook.md
 */

// ─── Email ────────────────────────────────────────────────────────────────────
export function validateEmail(value) {
    if (!value || String(value).trim() === '')
        return { valid: false, error: 'Email address is required.' };

    const trimmed = String(value).trim();
    if (trimmed.length > 254)
        return { valid: false, error: 'Email address must be 254 characters or fewer.' };

    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    if (!regex.test(trimmed))
        return { valid: false, error: 'Please enter a valid email address.' };

    return { valid: true, error: null };
}

// ─── Password ─────────────────────────────────────────────────────────────────
const COMMON_PASSWORDS = [
    'password', 'password123', 'qwerty', '12345678', '123456789',
    '1234567890', 'letmein', 'abc123', 'pass1234', 'passw0rd',
];

export function validatePassword(value) {
    if (!value || String(value).trim() === '')
        return { valid: false, error: 'Password is required.' };

    const v = String(value);

    if (v.length < 8)
        return { valid: false, error: 'Password must be at least 8 characters long.' };

    if (v.length > 128)
        return { valid: false, error: 'Password must be 128 characters or fewer.' };

    if (!/[A-Z]/.test(v))
        return { valid: false, error: 'Password must contain at least one uppercase letter.' };

    if (!/[a-z]/.test(v))
        return { valid: false, error: 'Password must contain at least one lowercase letter.' };

    if (!/[0-9]/.test(v))
        return { valid: false, error: 'Password must contain at least one number.' };

    if (!/[^A-Za-z0-9]/.test(v))
        return { valid: false, error: 'Password must contain at least one special character (e.g. !@#$%).' };

    if (COMMON_PASSWORDS.includes(v.toLowerCase()))
        return { valid: false, error: 'This password is too common. Please choose a stronger one.' };

    return { valid: true, error: null };
}

/** Strength score 0–4 for visual meter */
export function passwordStrength(value) {
    if (!value) return 0;
    let score = 0;
    if (value.length >= 8) score++;
    if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
    if (/[0-9]/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;
    return score;
}

export const strengthLabel = ['', 'Weak', 'Fair', 'Good', 'Strong'];
export const strengthColor = ['', '#e74c3c', '#e67e22', '#f1c40f', '#27ae60'];

// ─── Password Confirmation ────────────────────────────────────────────────────
export function validatePasswordMatch(password, confirmation) {
    if (!confirmation || String(confirmation).trim() === '')
        return { valid: false, error: 'Please confirm your password.' };

    if (password !== confirmation)
        return { valid: false, error: 'Passwords do not match.' };

    return { valid: true, error: null };
}

// ─── Name ─────────────────────────────────────────────────────────────────────
export function validateName(value, label = 'Name') {
    if (!value || String(value).trim() === '')
        return { valid: false, error: `${label} is required.` };

    const trimmed = String(value).trim();

    if (trimmed.length < 2)
        return { valid: false, error: `${label} must be at least 2 characters.` };

    if (trimmed.length > 100)
        return { valid: false, error: `${label} must be 100 characters or fewer.` };

    // Allow letters (including accented), spaces, hyphens, apostrophes
    if (/[0-9]/.test(trimmed))
        return { valid: false, error: `${label} should not contain numbers.` };

    return { valid: true, error: null };
}

// ─── Phone ───────────────────────────────────────────────────────────────────
export function validatePhone(value, required = true) {
    if (!value || String(value).trim() === '') {
        if (required) return { valid: false, error: 'Phone number is required.' };
        return { valid: true, error: null };
    }

    // Strip formatting characters for length check
    const digits = String(value).replace(/[\s\-().]/g, '');

    // Allow + prefix for international format (E.164)
    const normalized = digits.startsWith('+') ? digits : digits;
    const digitOnly = normalized.replace('+', '');

    if (digitOnly.length < 7 || digitOnly.length > 15)
        return { valid: false, error: 'Phone number must be between 7 and 15 digits.' };

    if (!/^\+?[0-9]{7,15}$/.test(digits))
        return { valid: false, error: 'Please enter a valid phone number (digits only, optionally starting with +).' };

    return { valid: true, error: null };
}

// ─── Required / Text ─────────────────────────────────────────────────────────
export function validateRequired(value, label = 'This field') {
    if (value === null || value === undefined || String(value).trim() === '')
        return { valid: false, error: `${label} is required.` };

    return { valid: true, error: null };
}

// ─── String length ────────────────────────────────────────────────────────────
export function validateLength(value, min, max, label = 'This field') {
    const len = String(value ?? '').trim().length;

    if (min && len < min)
        return { valid: false, error: `${label} must be at least ${min} characters.` };

    if (max && len > max)
        return { valid: false, error: `${label} must be ${max} characters or fewer.` };

    return { valid: true, error: null };
}

// ─── Dropdown / Select ────────────────────────────────────────────────────────
export function validateSelect(value, label = 'Please select an option') {
    if (!value || String(value).trim() === '')
        return { valid: false, error: label };

    return { valid: true, error: null };
}

// ─── Number range ─────────────────────────────────────────────────────────────
export function validateNumberRange(value, min, max, label = 'Value') {
    const num = Number(value);

    if (value === '' || value === null || value === undefined || isNaN(num))
        return { valid: false, error: `${label} is required.` };

    if (min !== undefined && num < min)
        return { valid: false, error: `${label} must be at least ${min}.` };

    if (max !== undefined && num > max)
        return { valid: false, error: `${label} must be ${max} or fewer.` };

    return { valid: true, error: null };
}

// ─── File ─────────────────────────────────────────────────────────────────────
export function validateFile(file, { maxMB = 2, allowedTypes = null } = {}) {
    if (!file) return { valid: true, error: null };

    const maxBytes = maxMB * 1024 * 1024;
    if (file.size > maxBytes)
        return { valid: false, error: `File must be ${maxMB} MB or smaller. Your file is ${(file.size / 1024 / 1024).toFixed(1)} MB.` };

    if (allowedTypes && allowedTypes.length) {
        const ext = '.' + file.name.split('.').pop().toLowerCase();
        if (!allowedTypes.includes(ext))
            return { valid: false, error: `Allowed file types: ${allowedTypes.join(', ')}` };
    }

    return { valid: true, error: null };
}

// ─── Time comparison ─────────────────────────────────────────────────────────
export function validateTimeRange(start, end) {
    if (!start || !end) return { valid: true, error: null };

    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    const startMinutes = sh * 60 + sm;
    const endMinutes   = eh * 60 + em;

    if (endMinutes <= startMinutes)
        return { valid: false, error: 'End time must be after start time.' };

    return { valid: true, error: null };
}

// ─── Textarea character counter helper ────────────────────────────────────────
export function charCount(value, max) {
    const len = String(value ?? '').length;
    return `${len}/${max} characters`;
}
