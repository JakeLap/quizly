import { computed, onScopeDispose, watch } from 'vue';
import type { Ref } from 'vue';
import {
    phoneSoundOn,
    play,
    setMusic,
    unlockOnInteraction,
    vibrate,
} from '@/lib/sound';
import type { SoundName } from '@/lib/sound';
import type { RoomState } from '@/types/room';

/** Count down out loud for this many seconds at the end of a question. */
const TICK_SECONDS = 5;

/** How late the "answers open" cue may still come, e.g. after a reload. */
const OPEN_SOUND_WINDOW_MS = 600;

/** Which phase the room is in: a change of phase plays its sound. */
function phaseOf(room: RoomState) {
    return `${room.match}:${room.status}:${room.round?.number ?? 0}`;
}

/** Sounds for one round's timeline: answers opening, then the countdown. */
function useRoundClock(
    room: Ref<RoomState>,
    now: Ref<number>,
    { ticks, onOpen }: { ticks: boolean; onOpen: () => void },
) {
    let opened = '';
    let lastTick = 0;

    watch(now, (time) => {
        const round = room.value.round;

        if (room.value.status !== 'question' || !round || room.value.paused) {
            return;
        }

        const key = `${room.value.match}:${round.number}`;

        if (opened !== key && time >= round.opensAt) {
            opened = key;
            lastTick = 0;

            if (time - round.opensAt < OPEN_SOUND_WINDOW_MS) {
                onOpen();
            }
        }

        const secondsLeft = Math.ceil((round.endsAt - time) / 1000);

        if (
            ticks &&
            time >= round.opensAt &&
            secondsLeft >= 1 &&
            secondsLeft <= TICK_SECONDS &&
            secondsLeft !== lastTick
        ) {
            lastTick = secondsLeft;
            play('tick', secondsLeft);
        }
    });
}

/**
 * The screen's sounds and music: the room's soundtrack, heard by everyone.
 */
export function useHostSounds(room: Ref<RoomState>, now: Ref<number>) {
    unlockOnInteraction();
    useRoundClock(room, now, { ticks: true, onOpen: () => play('open') });

    watch(
        () => phaseOf(room.value),
        () => {
            const { status } = room.value;

            if (status === 'intro') {
                play('start');
            } else if (status === 'question') {
                play('question');
            } else if (status === 'reveal') {
                play('timeUp');
                play('reveal');
            } else if (status === 'scoreboard') {
                play('scoreboard');
            } else if (status === 'finished') {
                play('winner');
            }
        },
    );

    watch(
        () => room.value.paused,
        (paused) => play(paused ? 'pause' : 'resume'),
    );

    watch(
        () => room.value.players.length,
        (count, before) => {
            if (room.value.status !== 'lobby') {
                return;
            }

            if (count > before) {
                play('join', count - 1);
            } else if (count < before) {
                play('leave');
            }
        },
    );

    watch(
        () => room.value.round?.answered.length ?? 0,
        (count, before) => {
            if (room.value.status === 'question' && count > before) {
                play('answered', count);
            }
        },
    );

    // Music while waiting and while thinking; quiet for everything else, so
    // the effects stand out.
    const music = computed(() => {
        const { status, round, paused } = room.value;

        if (paused) {
            return null;
        }

        if (status === 'lobby' || status === 'finished') {
            return 'lobby';
        }

        if (status === 'question' && round && now.value >= round.opensAt) {
            return 'thinking';
        }

        return null;
    });

    watch(
        music,
        // Let the winner's fanfare finish before the music comes back.
        (mode) => setMusic(mode, room.value.status === 'finished' ? 4 : 0),
        { immediate: true },
    );

    onScopeDispose(() => setMusic(null));
}

/**
 * A player's own sounds on their phone: their answer going in, and whether
 * they got it right. Shared moments (the countdown, the music) come from the
 * screen: a room full of phones playing them a little out of sync would be
 * a mess.
 */
export function usePlayerSounds(
    room: Ref<RoomState>,
    now: Ref<number>,
    meId: number,
    chosenSlot: Ref<number | null>,
) {
    // The player can silence their phone (the screen has a volume control).
    const sound = (name: SoundName) => phoneSoundOn.value && play(name);
    const buzz = (pattern: number | number[]) =>
        phoneSoundOn.value && vibrate(pattern);

    unlockOnInteraction();
    // A buzz when answers open, for anyone looking away.
    useRoundClock(room, now, { ticks: false, onOpen: () => buzz(40) });

    watch(chosenSlot, (slot, before) => {
        if (slot !== null && before === null) {
            sound('tap');
            buzz(15);
        }
    });

    watch(
        () => phaseOf(room.value),
        () => {
            const { status, round, standings } = room.value;

            if (status === 'reveal' && round?.reveal) {
                const mine = round.reveal.results.find(
                    (result) => result.playerId === meId,
                );

                if (mine?.correct) {
                    sound('correct');
                    buzz([30, 60, 30]);
                } else {
                    sound('wrong');
                    buzz(150);
                }
            } else if (status === 'finished') {
                const rank = standings?.find(
                    (standing) => standing.playerId === meId,
                )?.rank;

                sound(rank === 1 ? 'winner' : 'finish');
            }
        },
    );
}
