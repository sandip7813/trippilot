import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, createSSRApp, h } from 'vue';
import type { DefineComponent } from 'vue';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        const pages = import.meta.glob<{ default: DefineComponent }>(
            './pages/**/*.vue',
        );

        const page = pages[`./pages/${name}.vue`];

        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }

        return page();
    },
    setup({ el, App, props, plugin }) {
        const app = el?.hasAttribute('data-server-rendered')
            ? createSSRApp({ render: () => h(App, props) })
            : createApp({ render: () => h(App, props) });

        app.use(plugin).mount(el);
    },
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

initializeTheme();
initializeFlashToast();
