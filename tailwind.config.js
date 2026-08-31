import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            // AD-16 offline deployment: no webfont is fetched at runtime, so the
            // stack must be things the target machine already has. Segoe UI is
            // the first hit on the Windows target; the rest are fallbacks.
            fontFamily: {
                sans: ['Segoe UI', 'system-ui', '-apple-system', 'Roboto', ...defaultTheme.fontFamily.sans],
                mono: ['Cascadia Mono', 'Consolas', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                brand: {
                    50: '#EFF6FF',
                    100: '#DBEAFE',
                    200: '#BFDBFE',
                    600: '#2563EB',
                    700: '#1E40AF',
                    800: '#1E3A8A',
                    900: '#172554',
                },
                canvas: '#F1F5F9',
                surface: '#FFFFFF',
                ink: {
                    DEFAULT: '#0F172A',
                    muted: '#475569',
                    faint: '#64748B',
                },
                line: {
                    DEFAULT: '#E2E8F0',
                    strong: '#CBD5E1',
                },
                ok: { fg: '#15803D', bg: '#F0FDF4', br: '#BBF7D0' },
                warn: { fg: '#B45309', bg: '#FFFBEB', br: '#FDE68A' },
                bad: { fg: '#B91C1C', bg: '#FEF2F2', br: '#FECACA' },
                info: { fg: '#1D4ED8', bg: '#EFF6FF', br: '#BFDBFE' },
            },
            // Density 8/10 (dense dashboard): tighter rhythm than the default.
            borderRadius: {
                card: '0.5rem',
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(15 23 42 / 0.06), 0 1px 3px 0 rgb(15 23 42 / 0.04)',
                pop: '0 4px 12px -2px rgb(15 23 42 / 0.12)',
            },
        },
    },
    plugins: [],
};
