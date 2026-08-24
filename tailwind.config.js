import forms from '@tailwindcss/forms';

/**
 * The whole visual language lives here. No component may hard-code a hex value.
 * Light theme only - there is deliberately no dark: variant anywhere in this app.
 */
export default {
    content: [
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],

    theme: {
        // Replaced, not extended: sizes below 14px must not be reachable.
        fontSize: {
            helper: ['14px', { lineHeight: '20px' }],
            xs: ['14px', { lineHeight: '20px' }],
            sm: ['14px', { lineHeight: '20px' }],
            body: ['16px', { lineHeight: '24px' }],
            base: ['16px', { lineHeight: '24px' }],
            heading: ['18px', { lineHeight: '26px', fontWeight: '500' }],
            lg: ['18px', { lineHeight: '26px' }],
            qty: ['22px', { lineHeight: '28px', fontWeight: '500' }],
            xl: ['22px', { lineHeight: '28px' }],
            title: ['24px', { lineHeight: '32px', fontWeight: '700' }],
            '2xl': ['24px', { lineHeight: '32px' }],
            stat: ['32px', { lineHeight: '40px', fontWeight: '700' }],
            '3xl': ['32px', { lineHeight: '40px' }],
            '4xl': ['40px', { lineHeight: '48px' }],
        },

        extend: {
            fontFamily: {
                sans: ['"DM Sans"', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
            },

            colors: {
                page: '#F6F7F9',
                surface: '#FFFFFF',
                line: '#E8EAED',

                ink: {
                    DEFAULT: '#16181D', // text primary
                    soft: '#6B7280',    // text secondary
                    muted: '#9CA3AF',   // text muted
                },

                primary: {
                    DEFAULT: '#1F5EFF',
                    light: '#EBF1FF',
                    dark: '#1A4FD8', // pressed state only
                },

                // Status colours. Each one is always paired with an icon and a
                // word in the UI - colour never carries meaning on its own.
                waiting: { DEFAULT: '#B45309', bg: '#FEF3E2' },
                approved: { DEFAULT: '#15803D', bg: '#E9F7EE' },
                partial: { DEFAULT: '#C2410C', bg: '#FFF1E7' },
                rejected: { DEFAULT: '#B91C1C', bg: '#FDECEC' },
            },

            borderRadius: {
                card: '14px',
                control: '10px',
            },

            boxShadow: {
                // Only things that float get a shadow: dropdowns, sheets, toasts.
                float: '0 10px 30px -12px rgba(22, 24, 29, 0.22), 0 2px 8px -2px rgba(22, 24, 29, 0.08)',
                sheet: '0 -12px 34px -14px rgba(22, 24, 29, 0.28)',
                focus: '0 0 0 4px rgba(31, 94, 255, 0.16)',
            },

            minHeight: { touch: '48px', control: '52px' },
            minWidth: { touch: '48px' },
            spacing: { touch: '48px', card: '16px', 'card-lg': '20px' },

            transitionDuration: { toast: '200ms', sheet: '250ms' },

            keyframes: {
                'slide-up': {
                    from: { transform: 'translateY(100%)' },
                    to: { transform: 'translateY(0)' },
                },
                'toast-in': {
                    from: { transform: 'translateY(16px)', opacity: '0' },
                    to: { transform: 'translateY(0)', opacity: '1' },
                },
                shimmer: {
                    '100%': { transform: 'translateX(100%)' },
                },
            },

            animation: {
                'slide-up': 'slide-up 250ms cubic-bezier(0.32, 0.72, 0, 1)',
                'toast-in': 'toast-in 200ms ease-out',
            },
        },
    },

    plugins: [forms],
};
