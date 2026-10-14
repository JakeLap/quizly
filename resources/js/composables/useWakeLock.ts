import { onScopeDispose } from 'vue';

/**
 * Keep the screen on while the page is open, so phones don't fall asleep
 * (and drop their connection) between questions, and TVs don't start a
 * screensaver. Browsers release the lock when the tab is hidden, so it is
 * taken again whenever the page becomes visible.
 */
export function useWakeLock() {
    let lock: WakeLockSentinel | null = null;

    async function acquire() {
        if (
            !('wakeLock' in navigator) ||
            document.visibilityState !== 'visible'
        ) {
            return;
        }

        try {
            lock = await navigator.wakeLock.request('screen');
        } catch {
            // Not allowed (e.g. battery saver); the game works without it.
        }
    }

    function onVisibilityChange() {
        if (document.visibilityState === 'visible') {
            void acquire();
        }
    }

    void acquire();
    document.addEventListener('visibilitychange', onVisibilityChange);

    onScopeDispose(() => {
        document.removeEventListener('visibilitychange', onVisibilityChange);
        void lock?.release();
    });
}
