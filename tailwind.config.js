import forms from '@tailwindcss/forms';

export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                forest: {
                    50: '#f2f7f4',
                    100: '#dfede5',
                    200: '#c0dbcc',
                    300: '#93c2a9',
                    400: '#62a382',
                    500: '#3d8663',
                    600: '#2d6a4f',
                    700: '#245440',
                    800: '#1e4334',
                    900: '#19372b',
                    950: '#0d2018',
                },
            },
        },
    },
    plugins: [forms],
};
