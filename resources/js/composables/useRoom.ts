import { router } from '@inertiajs/vue3';
import { useConnectionStatus, useEchoPresence } from '@laravel/echo-vue';
import { useEventListener, useIntervalFn } from '@vueuse/core';
import { computed, onScopeDispose, ref, watch } from 'vue';
import type { RoomMember, RoomState } from '@/types/room';

/** If nothing arrives for this long, check in with the server anyway. */
const QUIET_RESYNC_MS = 30_000;

/** How long after a phase should have ended before suspecting a missed update. */
const OVERDUE_RESYNC_MS = 2_000;

/** Never resync more often than this. */
const MIN_RESYNC_GAP_MS = 3_000;

/** How long someone can be gone (reloading, reconnecting) before they count as offline. */
const LEAVE_GRACE_MS = 3_000;

/**
 * Live room state: starts from the page props and follows the server's
 * `room.updated` broadcasts, keeping whichever snapshot is newest.
 *
 * @param reloadProps The page props to fetch again when resyncing.
 * @param onPlayerLeft Called when a player's connection drops.
 */
export function useRoom(
    initial: () => RoomState,
    reloadProps: string[] = ['room'],
    onPlayerLeft?: (playerId: number) => void,
) {
    const room = ref<RoomState>(initial());
    let lastUpdateAt = Date.now();
    let lastResyncAt = 0;

    // Countdowns run on server time. Each snapshot carries the server's clock;
    // it took a moment to arrive, so every sample slightly underestimates the
    // offset and the largest one seen is the most accurate.
    const clockOffset = ref(room.value.serverNow - Date.now());
    const now = ref(Date.now() + clockOffset.value);

    useIntervalFn(() => (now.value = Date.now() + clockOffset.value), 50);

    function apply(next: RoomState) {
        clockOffset.value = Math.max(
            clockOffset.value,
            next.serverNow - Date.now(),
        );

        if (next.code === room.value.code) {
            lastUpdateAt = Date.now();
        }

        if (
            next.code !== room.value.code ||
            next.version < room.value.version
        ) {
            return;
        }

        const languageChanged = next.locale !== room.value.locale;
        room.value = next;

        // UI strings come with the page props, so fetch them in the new language.
        if (languageChanged) {
            router.reload();
        }
    }

    // Inertia responses (e.g. after the host changes a setting) bring fresh props too.
    watch(initial, apply);

    const { channel } = useEchoPresence<RoomState>(
        `room.${room.value.code}`,
        '.room.updated',
        apply,
    );

    const members = ref<RoomMember[]>([]);
    /** Members who just left, by id: removed once the grace period runs out. */
    const departures = new Map<string, number>();
    /** Whether the member list has arrived, so "offline" isn't shown while connecting. */
    const presenceReady = ref(false);

    channel()
        .here((present: RoomMember[]) => {
            members.value = present;
            presenceReady.value = true;
        })
        .joining((member: RoomMember) => {
            window.clearTimeout(departures.get(member.id));
            departures.delete(member.id);

            members.value = [
                ...members.value.filter(({ id }) => id !== member.id),
                member,
            ];
        })
        .leaving((member: RoomMember) => {
            // A page reload or a quick reconnect leaves and rejoins within a
            // moment: only count the member as gone if they stay away.
            window.clearTimeout(departures.get(member.id));
            departures.set(
                member.id,
                window.setTimeout(() => {
                    departures.delete(member.id);
                    members.value = members.value.filter(
                        ({ id }) => id !== member.id,
                    );

                    if (
                        member.role === 'player' &&
                        member.playerId !== undefined
                    ) {
                        onPlayerLeft?.(member.playerId);
                    }
                }, LEAVE_GRACE_MS),
            );
        });

    onScopeDispose(() =>
        departures.forEach((timer) => window.clearTimeout(timer)),
    );

    function resync() {
        lastResyncAt = Date.now();
        router.reload({ only: reloadProps });
    }

    // Broadcasts sent while the socket was down are lost, so catch up after reconnecting.
    const status = useConnectionStatus();

    watch(status, (now, before) => {
        if (now === 'connected' && before && before !== 'connected') {
            resync();
        }
    });

    // Phones pause pages in the background and may miss updates meanwhile.
    useEventListener(document, 'visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            resync();
        }
    });

    // A broadcast can also go missing while the socket stays up (e.g. the
    // server couldn't reach Reverb). Catch up if the current phase should
    // have ended a while ago, or if the room has been silent for long.
    useIntervalFn(() => {
        if (['finished', 'abandoned'].includes(room.value.status)) {
            return;
        }

        const overdue =
            room.value.nextAt !== null &&
            now.value > room.value.nextAt + OVERDUE_RESYNC_MS;
        const quiet = Date.now() - lastUpdateAt > QUIET_RESYNC_MS;

        if (
            (overdue || quiet) &&
            Date.now() - lastResyncAt > MIN_RESYNC_GAP_MS
        ) {
            resync();
        }
    }, 1_000);

    const onlinePlayerIds = computed(
        () =>
            new Set(
                members.value
                    .filter((member) => member.role === 'player')
                    .map((member) => member.playerId),
            ),
    );

    return {
        room,
        /** The current server time in ms, updated every 50 ms. */
        now,
        connection: status,
        presenceReady,
        hostOnline: computed(() =>
            members.value.some((member) => member.role === 'host'),
        ),
        isOnline: (playerId: number) => onlinePlayerIds.value.has(playerId),
    };
}
