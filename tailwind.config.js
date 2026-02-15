import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './resources/js/**/*.vue',
    ],
    safelist: [
        'bg-yellow-100',
        'text-yellow-800',
        'border-yellow-200',
        'bg-red-100',
        'text-red-800',
        'border-red-200',
        'bg-green-100',
        'text-green-800',
        'border-green-200',
        'bg-gray-100',
        'text-gray-700',
        'border-gray-200',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
