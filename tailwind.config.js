import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/**
 * Brand palette taken from the existing infinitenaturals.com theme:
 *   gold  #D5B981 (accents, icons, borders)
 *   umber #6e1900 (deep brown, used for primary buttons and links)
 * Fonts: Prata (headings) and Open Sans (body), as on the current site.
 */
/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/Enums/*.php',
    ],

    safelist: [
        { pattern: /(bg|text|ring)-(green|amber|red|gray|blue)-(50|100|600|700|800)/ },
    ],

    theme: {
        extend: {
            colors: {
                gold: {
                    50: '#fbf8f1', 100: '#f6efdf', 200: '#ecdfc2', 300: '#e1cba0',
                    400: '#d5b981', 500: '#c6a263', 600: '#a8854a', 700: '#86683a', 800: '#6b5330', 900: '#58452a',
                },
                umber: {
                    50: '#fbf1ee', 100: '#f5ddd5', 200: '#e9b6a6', 500: '#a1300f',
                    600: '#86230a', 700: '#6e1900', 800: '#561300', 900: '#3f0e00',
                },
                ink: '#211a16',
                cream: '#fbf8f3',
            },
            fontFamily: {
                sans: ['"Open Sans"', ...defaultTheme.fontFamily.sans],
                serif: ['Prata', ...defaultTheme.fontFamily.serif],
            },
            typography: ({ theme }) => ({
                store: {
                    css: {
                        '--tw-prose-body': theme('colors.stone.700'),
                        '--tw-prose-headings': theme('colors.ink'),
                        '--tw-prose-links': theme('colors.umber.700'),
                        '--tw-prose-bold': theme('colors.ink'),
                        '--tw-prose-bullets': theme('colors.gold.500'),
                    },
                },
            }),
        },
    },

    plugins: [forms, typography],
};
