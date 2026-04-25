import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans:    ['Nunito', ...defaultTheme.fontFamily.sans],
                fredoka: ['Fredoka One', 'cursive'],
                inter:   ['Inter', ...defaultTheme.fontFamily.sans],
                georgia: ['Georgia', 'serif'],
                arial:   ['Arial', 'sans-serif'],
            },
            colors: {
                coral:  '#E8553E',
                yellow: '#F5C518',
                mint:   '#4CB87E',
                peach:  '#FFAB76',
                cream:  '#FFF8F0',
                sage:   '#3D6B4F',
                'off-white': '#FAFAF8',
                navy:   '#1E3A5F',
                'blue-accent': '#2563EB',
            },
            borderRadius: {
                pill: '999px',
                card: '20px',
            },
            boxShadow: {
                'card-tinted': '0 4px 20px rgba(232, 85, 62, 0.08)',
            },
        },
    },

    plugins: [forms],
};
