import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { configureEcho } from '@laravel/echo-vue';
import { channelAuthorization } from '@/lib/echoAuth';

configureEcho({
    broadcaster: 'reverb',
    // Phones reach the server by the same host name as the page, e.g. the
    // computer's LAN address, so only override it when set explicitly.
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    channelAuthorization,
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
            case name.startsWith('host/'):
            case name.startsWith('play/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#fcd34d',
    },
});

// Keep <html lang> in step with the page's language after every visit,
// including ones that stay on the same page (like switching language).
router.on('success', (event) => {
    document.documentElement.lang = String(event.detail.page.props.locale);
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
