import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './composables/useAppearance';
import { showToast } from './composables/useToast';

initializeTheme();

// Responses that are not Inertia pages (rate limit, expired session) would otherwise open a raw error page in a modal.
router.on('invalid', (event) => {
    const friendly: Record<number, string> = {
        419: 'Tu sesión expiró. Recarga la página e inténtalo de nuevo.',
        429: 'Vas muy rápido. Espera un momento e inténtalo de nuevo.',
    };
    const message = friendly[event.detail.response.status];

    if (message) {
        event.preventDefault();
        showToast(message, 6000);
    }
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#1F6B52',
    },
});
