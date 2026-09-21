import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                cream: {
                    DEFAULT: '#efe6dd',
                    50: '#faf7f4',
                    100: '#efe6dd',
                    200: '#e4d5c5',
                },
                cherry: {
                    DEFAULT: '#9a0002',
                    50: '#fbe9e9',
                    600: '#9a0002',
                    700: '#800002',
                    800: '#650001',
                    900: '#4a0001',
                },
            },
        },
    },

    plugins: [forms],
};
