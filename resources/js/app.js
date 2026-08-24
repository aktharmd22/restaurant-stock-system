import '../css/app.css';
import './bootstrap';

// DM Sans, self-hosted. Loading these from Google would add a third-party
// round trip on a slow mobile connection before any text renders.
import '@fontsource/dm-sans/400.css';
import '@fontsource/dm-sans/500.css';
import '@fontsource/dm-sans/700.css';

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
        color: '#1F5EFF',
        showSpinner: false,
    },
});
