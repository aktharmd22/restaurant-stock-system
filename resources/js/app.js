import '../css/app.css';
import './bootstrap';

// DM Sans, self-hosted. Loading these from Google would add a third-party
// round trip on a slow mobile connection before any text renders.
//
// Latin subset only: the app is English, and the extended subsets were 22 kB
// of font files nobody here will ever see a character of.
import '@fontsource/dm-sans/latin-400.css';
import '@fontsource/dm-sans/latin-500.css';
import '@fontsource/dm-sans/latin-700.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// The business name is admin-editable, so it comes from the server, not the build.
const initialPage = (() => {
    try {
        return JSON.parse(document.getElementById('app')?.dataset.page ?? '{}');
    } catch {
        return {};
    }
})();

const appName =
    initialPage?.props?.business?.name || import.meta.env.VITE_APP_NAME || 'Restaurant Stock';

// Lets branch staff add the app to their home screen, and gives them an
// honest "no internet" page instead of the browser's error.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Not fatal - the app works fine without it.
        });
    });
}

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#1E3A8A',
        showSpinner: false,
    },
});
