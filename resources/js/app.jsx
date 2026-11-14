import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import PageLoading from './Components/PageLoading';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'School Management System';
const pages = import.meta.glob('./Pages/**/*.jsx');

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./Pages/${name}.jsx`, pages),
    setup({ el, App, props }) {
        createRoot(el).render(
            <>
                <App {...props} />
                <PageLoading />
            </>,
        );
    },
    progress: {
        color: '#0f766e',
    },
});
