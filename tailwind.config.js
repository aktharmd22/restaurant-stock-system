import forms from '@tailwindcss/forms';

/**
 * The whole visual language lives here. No component may hard-code a hex value.
 * Light theme only - there is deliberately no dark: variant anywhere in the app,
 * apart from the admin sidebar, which is a deliberate anchor for the layout.
 */
export default {
    content: [
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],

    theme: {
        // Replaced, not extended, so the scale is the only one available.
        fontSize: {
            micro: ['12px', { lineHeight: '16px' }],
            helper: ['13px', { lineHeight: '18px' }],
            xs: ['12px', { lineHeight: '16px' }],
            sm: ['13px', { lineHeight: '18px' }],
            body: ['14px', { lineHeight: '20px' }],
            base: ['14px', { lineHeight: '20px' }],
            heading: ['16px', { lineHeight: '22px', fontWeight: '600' }],
            lg: ['16px', { lineHeight: '22px' }],
            qty: ['18px', { lineHeight: '24px', fontWeight: '600' }],
            xl: ['18px', { lineHeight: '24px' }],
            title: ['20px', { lineHeight: '28px', fontWeight: '700' }],
            '2xl': ['20px', { lineHeight: '28px' }],
            stat: ['28px', { lineHeight: '34px', fontWeight: '700' }],
            '3xl': ['24px', { lineHeight: '30px' }],
            '4xl': ['32px', { lineHeight: '38px' }],
        },

        extend: {
            fontFamily: {
                sans: ['"DM Sans"', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
            },

            colors: {
                page: '#F5F6F8',
                surface: '#FFFFFF',
                line: '#ECEEF2',

                ink: {
                    DEFAULT: '#16181D',
                    soft: '#6B7280',
                    muted: '#9CA3AF',
                },

                primary: {
                    DEFAULT: '#1E3A8A',
                    light: '#E9EDF9',
                    dark: '#16295F',
                },

                // The admin sidebar. Dark on purpose: it anchors the layout and
                // pushes every screen's content forward.
                shell: {
                    DEFAULT: '#0F1D40',
                    soft: '#1B2C57',
                    line: '#27396B',
                    text: '#9CA9C6',
                },

                // Status. Colour never travels alone - every pill also carries
                // an icon and a word.
                waiting: { DEFAULT: '#B45309', bg: '#FEF3E2' },
                approved: { DEFAULT: '#15803D', bg: '#E9F7EE' },
                partial: { DEFAULT: '#C2410C', bg: '#FFF1E7' },
                rejected: { DEFAULT: '#B91C1C', bg: '#FDECEC' },

                // Soft tiles behind stat-card icons.
                tile: {
                    violet: '#EFE9FE',
                    'violet-ink': '#6D3BEB',
                    blue: '#E9EDF9',
                    'blue-ink': '#1E3A8A',
                    cyan: '#E1F4FB',
                    'cyan-ink': '#0E7C9B',
                    green: '#E6F6EC',
                    'green-ink': '#15803D',
                    amber: '#FEF3E2',
                    'amber-ink': '#B45309',
                    rose: '#FDECEC',
                    'rose-ink': '#B91C1C',
                },
            },

            borderRadius: {
                card: '16px',
                control: '10px',
            },

            boxShadow: {
                card: '0 1px 2px rgba(22, 24, 29, 0.04)',
                float: '0 10px 30px -12px rgba(22, 24, 29, 0.22), 0 2px 8px -2px rgba(22, 24, 29, 0.08)',
                sheet: '0 -12px 34px -14px rgba(22, 24, 29, 0.28)',
                focus: '0 0 0 4px rgba(30, 58, 138, 0.16)',
            },

            // Fingers do not get smaller when the type does.
            minHeight: { touch: '44px', control: '44px', tap: '48px' },
            minWidth: { touch: '44px' },
            spacing: { touch: '44px', card: '16px', 'card-lg': '20px', sidebar: '248px' },

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
                shimmer: { '100%': { transform: 'translateX(100%)' } },
                'draw-in': { from: { opacity: '0' }, to: { opacity: '1' } },
            },

            animation: {
                'slide-up': 'slide-up 250ms cubic-bezier(0.32, 0.72, 0, 1)',
                'toast-in': 'toast-in 200ms ease-out',
                'draw-in': 'draw-in 400ms ease-out',
            },
        },
    },

    plugins: [forms],
};
