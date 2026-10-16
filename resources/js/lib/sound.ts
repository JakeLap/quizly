import { ref, watch } from 'vue';

/**
 * The game's sounds, synthesized with the Web Audio API: no files to load,
 * and every effect is a few oscillators and envelopes.
 *
 * Browsers only let a page make sound after someone has interacted with it,
 * so the audio starts on the first tap or key press.
 */

export type SoundName =
    | 'join'
    | 'leave'
    | 'start'
    | 'question'
    | 'open'
    | 'tick'
    | 'answered'
    | 'tap'
    | 'timeUp'
    | 'reveal'
    | 'correct'
    | 'wrong'
    | 'scoreboard'
    | 'winner'
    | 'finish'
    | 'pause'
    | 'resume';

export type MusicMode = 'lobby' | 'thinking';

const STORAGE_KEY = 'quizly:phone-sound';
const SFX_VOLUME = 0.55;
const MUSIC_VOLUME = 0.22;

function loadPhoneSound(): boolean {
    try {
        return localStorage.getItem(STORAGE_KEY) !== 'off';
    } catch {
        return true;
    }
}

/**
 * Whether this phone plays its sounds, remembered on the device. The screen
 * always plays: it has its own volume control.
 */
export const phoneSoundOn = ref(loadPhoneSound());

watch(phoneSoundOn, (on) => {
    try {
        localStorage.setItem(STORAGE_KEY, on ? 'on' : 'off');
    } catch {
        // Storage blocked (e.g. private mode): the choice lasts for this visit.
    }
});

/** Whether the browser lets the page play sound yet. */
export const audioUnlocked = ref(false);

let ctx: AudioContext | null = null;
let sfxBus: GainNode | null = null;
let musicBus: GainNode | null = null;
let noiseBuffer: AudioBuffer | null = null;

function context(): AudioContext | null {
    if (ctx) {
        return ctx;
    }

    const Context =
        window.AudioContext ??
        (window as unknown as { webkitAudioContext?: typeof AudioContext })
            .webkitAudioContext;

    if (!Context) {
        return null;
    }

    ctx = new Context();

    // Several sounds at once shouldn't clip.
    const limiter = ctx.createDynamicsCompressor();
    limiter.threshold.value = -10;
    limiter.connect(ctx.destination);

    sfxBus = ctx.createGain();
    sfxBus.gain.value = SFX_VOLUME;
    sfxBus.connect(limiter);

    musicBus = ctx.createGain();
    musicBus.gain.value = MUSIC_VOLUME;
    musicBus.connect(limiter);

    const update = () => (audioUnlocked.value = ctx?.state === 'running');
    ctx.addEventListener('statechange', update);
    update();

    return ctx;
}

/** Start the audio if the browser allows it now (i.e. during a tap). */
export function unlockAudio() {
    const audio = context();

    if (audio && audio.state !== 'running') {
        void audio.resume().catch(() => {});
    }
}

let listening = false;

/** Start the audio on the page's first tap or key press. */
export function unlockOnInteraction() {
    // Some browsers allow sound straight away, e.g. after clicking a link to this page.
    unlockAudio();

    if (listening) {
        return;
    }

    listening = true;

    for (const event of ['pointerdown', 'keydown', 'touchend']) {
        document.addEventListener(event, unlockAudio, {
            capture: true,
            passive: true,
        });
    }
}

// --- Building blocks ---------------------------------------------------------

function midi(note: number) {
    return 440 * 2 ** ((note - 69) / 12);
}

type ToneOptions = {
    note: number;
    at?: number;
    duration: number;
    type?: OscillatorType;
    volume?: number;
    attack?: number;
    /** Slide to this note over the tone's length. */
    glideTo?: number;
    /** Soften bright waveforms. */
    lowpass?: number;
    detune?: number;
    out?: AudioNode;
};

function tone(audio: AudioContext, options: ToneOptions) {
    const start = audio.currentTime + (options.at ?? 0);
    const end = start + options.duration;
    const osc = audio.createOscillator();
    const gain = audio.createGain();

    osc.type = options.type ?? 'sine';
    osc.frequency.setValueAtTime(midi(options.note), start);
    osc.detune.value = options.detune ?? 0;

    if (options.glideTo !== undefined) {
        osc.frequency.exponentialRampToValueAtTime(midi(options.glideTo), end);
    }

    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(
        options.volume ?? 0.3,
        start + (options.attack ?? 0.005),
    );
    gain.gain.exponentialRampToValueAtTime(0.0001, end);

    let last: AudioNode = osc;

    if (options.lowpass) {
        const filter = audio.createBiquadFilter();
        filter.type = 'lowpass';
        filter.frequency.value = options.lowpass;
        last.connect(filter);
        last = filter;
    }

    last.connect(gain).connect(options.out ?? sfxBus!);
    osc.start(start);
    osc.stop(end + 0.05);
}

/** A bell: a tone with the slightly out-of-tune overtones that make it ring. */
function bell(
    audio: AudioContext,
    note: number,
    at = 0,
    volume = 0.25,
    duration = 1.2,
) {
    tone(audio, { note, at, duration, volume });
    tone(audio, {
        note: note + 12,
        at,
        duration: duration * 0.6,
        volume: volume * 0.4,
    });
    tone(audio, {
        note: note + 19.02,
        at,
        duration: duration * 0.35,
        volume: volume * 0.2,
    });
}

type NoiseOptions = {
    at?: number;
    duration: number;
    volume?: number;
    filter?: BiquadFilterType;
    from: number;
    to?: number;
    q?: number;
    attack?: number;
    out?: AudioNode;
};

function noise(audio: AudioContext, options: NoiseOptions) {
    if (!noiseBuffer) {
        noiseBuffer = audio.createBuffer(1, audio.sampleRate, audio.sampleRate);
        const data = noiseBuffer.getChannelData(0);

        for (let i = 0; i < data.length; i++) {
            data[i] = Math.random() * 2 - 1;
        }
    }

    const start = audio.currentTime + (options.at ?? 0);
    const end = start + options.duration;
    const source = audio.createBufferSource();
    const filter = audio.createBiquadFilter();
    const gain = audio.createGain();

    source.buffer = noiseBuffer;
    source.loop = true;
    filter.type = options.filter ?? 'bandpass';
    filter.Q.value = options.q ?? 1;
    filter.frequency.setValueAtTime(options.from, start);

    if (options.to !== undefined) {
        filter.frequency.exponentialRampToValueAtTime(options.to, end);
    }

    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(
        options.volume ?? 0.2,
        start + (options.attack ?? 0.01),
    );
    gain.gain.exponentialRampToValueAtTime(0.0001, end);

    source
        .connect(filter)
        .connect(gain)
        .connect(options.out ?? sfxBus!);
    source.start(start, Math.random() * 0.5);
    source.stop(end + 0.05);
}

// --- Effects -----------------------------------------------------------------

const C5 = 72;

const effects: Record<
    SoundName,
    (audio: AudioContext, variant: number) => void
> = {
    // A bubbly pop, a little higher for each player who joins.
    join(audio, variant) {
        const base = C5 + [0, 2, 4, 7, 9, 12, 14, 16][variant % 8];
        tone(audio, {
            note: base,
            duration: 0.12,
            type: 'triangle',
            volume: 0.3,
        });
        tone(audio, {
            note: base + 7,
            at: 0.07,
            duration: 0.25,
            type: 'triangle',
            volume: 0.3,
        });
        noise(audio, { duration: 0.04, from: 3000, volume: 0.05 });
    },

    leave(audio) {
        tone(audio, {
            note: C5 + 7,
            duration: 0.12,
            type: 'triangle',
            volume: 0.2,
        });
        tone(audio, {
            note: C5,
            at: 0.08,
            duration: 0.25,
            type: 'triangle',
            volume: 0.2,
        });
    },

    // A rising arpeggio into a bright chord.
    start(audio) {
        noise(audio, {
            duration: 0.8,
            from: 300,
            to: 6000,
            volume: 0.12,
            attack: 0.6,
        });
        [0, 4, 7, 12].forEach((step, i) =>
            tone(audio, {
                note: 60 + step,
                at: 0.6 + i * 0.09,
                duration: 0.2,
                type: 'square',
                lowpass: 2500,
                volume: 0.12,
            }),
        );
        [72, 76, 79, 84].forEach((note) =>
            tone(audio, {
                note,
                at: 0.98,
                duration: 1.1,
                type: 'sawtooth',
                lowpass: 1800,
                volume: 0.07,
                attack: 0.02,
            }),
        );
        bell(audio, 96, 1.0, 0.08);
    },

    // The question appears: a whoosh and a soft thump.
    question(audio) {
        noise(audio, {
            duration: 0.45,
            from: 600,
            to: 4000,
            volume: 0.12,
            attack: 0.3,
        });
        tone(audio, {
            note: 40,
            at: 0.3,
            duration: 0.35,
            glideTo: 33,
            volume: 0.45,
        });
    },

    // Answers open.
    open(audio) {
        bell(audio, 84, 0, 0.22, 0.9);
        bell(audio, 91, 0.08, 0.16, 0.9);
    },

    // The last seconds of a question, higher as time runs out.
    tick(audio, secondsLeft) {
        const urgent = secondsLeft <= 3;
        tone(audio, {
            note: urgent ? 88 : 81,
            duration: 0.07,
            volume: urgent ? 0.32 : 0.22,
        });
        noise(audio, {
            duration: 0.03,
            filter: 'highpass',
            from: 5000,
            volume: 0.06,
        });
    },

    // Someone answered (on the screen): a blip that climbs with each answer.
    answered(audio, count) {
        tone(audio, {
            note: 76 + Math.min(count, 12) * 2,
            duration: 0.09,
            type: 'triangle',
            volume: 0.18,
        });
    },

    // My answer is in (on the phone).
    tap(audio) {
        tone(audio, {
            note: 79,
            duration: 0.06,
            type: 'triangle',
            volume: 0.3,
            glideTo: 84,
        });
        noise(audio, { duration: 0.025, from: 2500, volume: 0.08 });
    },

    timeUp(audio) {
        tone(audio, {
            note: 50,
            duration: 0.45,
            type: 'square',
            lowpass: 900,
            volume: 0.15,
        });
        tone(audio, {
            note: 49,
            duration: 0.45,
            type: 'square',
            lowpass: 900,
            volume: 0.15,
            detune: 12,
        });
    },

    // The right answer lights up on the screen.
    reveal(audio) {
        [72, 76, 79].forEach((note, i) =>
            bell(audio, note, 0.35 + i * 0.07, 0.16, 1.4),
        );
    },

    correct(audio) {
        tone(audio, {
            note: 79,
            duration: 0.12,
            type: 'triangle',
            volume: 0.3,
        });
        tone(audio, {
            note: 84,
            at: 0.1,
            duration: 0.4,
            type: 'triangle',
            volume: 0.3,
        });
        bell(audio, 96, 0.1, 0.08, 0.6);
    },

    wrong(audio) {
        tone(audio, {
            note: 55,
            duration: 0.5,
            type: 'sawtooth',
            lowpass: 700,
            glideTo: 45,
            volume: 0.2,
        });
    },

    scoreboard(audio) {
        noise(audio, { duration: 0.5, from: 5000, to: 500, volume: 0.1 });
        [60, 64, 67].forEach((note) =>
            tone(audio, {
                note,
                at: 0.15,
                duration: 0.6,
                type: 'triangle',
                volume: 0.12,
                attack: 0.03,
            }),
        );
    },

    // A fanfare and applause for the winner.
    winner(audio) {
        const fanfare: [number, number, number][] = [
            [67, 0, 0.14],
            [67, 0.15, 0.14],
            [67, 0.3, 0.14],
            [72, 0.45, 0.9],
        ];
        fanfare.forEach(([note, at, duration]) => {
            tone(audio, {
                note,
                at,
                duration,
                type: 'sawtooth',
                lowpass: 2200,
                volume: 0.13,
                attack: 0.02,
            });
            tone(audio, {
                note: note + 4,
                at,
                duration,
                type: 'sawtooth',
                lowpass: 2200,
                volume: 0.08,
                attack: 0.02,
            });
        });
        [60, 64, 67, 72, 76].forEach((note) =>
            tone(audio, {
                note,
                at: 1.3,
                duration: 1.6,
                type: 'triangle',
                volume: 0.1,
                attack: 0.03,
            }),
        );
        bell(audio, 96, 1.3, 0.1, 1.5);

        // Clapping: lots of short, random bursts.
        for (let i = 0; i < 70; i++) {
            noise(audio, {
                at: 1.2 + Math.random() * 2.4,
                duration: 0.05,
                from: 1200 + Math.random() * 1500,
                q: 1.5,
                volume: 0.05 + Math.random() * 0.08,
                attack: 0.002,
            });
        }
    },

    // The end, for everyone but the winner.
    finish(audio) {
        [60, 64, 67, 72].forEach((note, i) =>
            tone(audio, {
                note,
                at: i * 0.12,
                duration: 0.9 - i * 0.1,
                type: 'triangle',
                volume: 0.15,
            }),
        );
    },

    pause(audio) {
        tone(audio, {
            note: 67,
            duration: 0.6,
            type: 'triangle',
            glideTo: 48,
            volume: 0.25,
        });
    },

    resume(audio) {
        tone(audio, {
            note: 48,
            duration: 0.4,
            type: 'triangle',
            glideTo: 67,
            volume: 0.25,
        });
    },
};

/**
 * Play an effect, if the page may make sound. Effects that can't play now
 * are dropped rather than played late.
 *
 * @param variant Effect-specific: e.g. the player number for `join`.
 */
export function play(name: SoundName, variant = 0) {
    const audio = context();

    if (!audio || audio.state !== 'running') {
        return;
    }

    effects[name](audio, variant);
    window.dispatchEvent(new CustomEvent('quizly:sound', { detail: name }));
}

/** A short buzz on phones that can (not iPhones). */
export function vibrate(pattern: number | number[]) {
    if ('vibrate' in navigator) {
        try {
            navigator.vibrate(pattern);
        } catch {
            // Not allowed here; it's only a nicety.
        }
    }
}

// --- Music -------------------------------------------------------------------

type Song = {
    bpm: number;
    /** One chord per bar, as notes; the first is the bass. */
    chords: number[][];
    /** Plays one eighth-note step of the given bar. */
    step: (
        audio: AudioContext,
        out: GainNode,
        at: number,
        chord: number[],
        step: number,
    ) => void;
};

const songs: Record<MusicMode, Song> = {
    // Easy-going, while people join.
    lobby: {
        bpm: 104,
        chords: [
            [48, 64, 67, 72],
            [45, 64, 69, 72],
            [41, 65, 69, 72],
            [43, 62, 67, 71],
        ],
        step(audio, out, at, chord, step) {
            const beat = 60 / 104;
            const [bass, ...upper] = chord;

            if (step === 0) {
                upper.forEach((note) =>
                    tone(audio, {
                        note,
                        at,
                        duration: beat * 4,
                        type: 'triangle',
                        volume: 0.05,
                        attack: 0.25,
                        out,
                    }),
                );
            }

            if (step % 4 === 0 || step === 7) {
                tone(audio, {
                    note: bass,
                    at,
                    duration: beat * 0.9,
                    volume: 0.35,
                    out,
                });
            }

            const arpeggio = [0, 1, 2, 1, 2, 0, 1, 2];
            tone(audio, {
                note: upper[arpeggio[step]] + 12,
                at,
                duration: beat * 0.45,
                type: 'square',
                lowpass: 1600,
                volume: step % 2 ? 0.035 : 0.05,
                out,
            });

            if (step % 2 === 1) {
                noise(audio, {
                    at,
                    duration: 0.04,
                    filter: 'highpass',
                    from: 7000,
                    volume: 0.04,
                    out,
                });
            }
        },
    },

    // A ticking, curious pulse while everyone thinks.
    thinking: {
        bpm: 120,
        chords: [
            [45, 69, 72, 76],
            [45, 69, 72, 76],
            [41, 69, 72, 77],
            [43, 67, 71, 74],
        ],
        step(audio, out, at, chord, step) {
            const beat = 60 / 120;
            const [bass, ...upper] = chord;

            tone(audio, {
                note: step % 2 ? bass + 12 : bass,
                at,
                duration: beat * 0.4,
                volume: 0.25,
                out,
            });

            if (step % 4 === 2) {
                tone(audio, {
                    note: upper[(step / 2) % upper.length] + 12,
                    at,
                    duration: beat * 0.3,
                    type: 'square',
                    lowpass: 2000,
                    volume: 0.035,
                    out,
                });
            }

            noise(audio, {
                at,
                duration: 0.03,
                filter: 'highpass',
                from: 8000,
                volume: step % 2 ? 0.03 : 0.015,
                out,
            });
        },
    },
};

type Playing = { mode: MusicMode; out: GainNode; timer: number };

let playing: Playing | null = null;
let musicStart: number | undefined;

/**
 * Loop a song, or stop the music (null). Notes are scheduled a little ahead
 * on the audio clock, so they keep time even if the page is busy.
 */
export function setMusic(mode: MusicMode | null, delay = 0) {
    window.clearTimeout(musicStart);

    if (playing?.mode === mode) {
        return;
    }

    stopMusic();

    if (mode !== null) {
        musicStart = window.setTimeout(() => startMusic(mode), delay * 1000);
    }
}

function stopMusic() {
    if (!playing || !ctx) {
        return;
    }

    const { out, timer } = playing;
    window.clearInterval(timer);
    out.gain.setTargetAtTime(0, ctx.currentTime, 0.15);
    window.setTimeout(() => out.disconnect(), 1500);
    playing = null;
}

function startMusic(mode: MusicMode) {
    const audio = context();

    if (!audio || !musicBus) {
        return;
    }

    const song = songs[mode];
    const stepLength = 60 / song.bpm / 2;
    const out = audio.createGain();
    out.gain.value = 1;
    out.connect(musicBus);

    // Hidden tabs run timers about once a second, so schedule well ahead.
    const lookahead = 1.5;
    let step = 0;
    let nextAt = audio.currentTime + 0.1;

    function schedule() {
        // Waiting for the first tap: start in time once it comes.
        if (audio!.state !== 'running') {
            nextAt = audio!.currentTime + 0.1;

            return;
        }

        while (nextAt < audio!.currentTime + lookahead) {
            const bar = Math.floor(step / 8) % song.chords.length;
            song.step(
                audio!,
                out,
                nextAt - audio!.currentTime,
                song.chords[bar],
                step % 8,
            );
            nextAt += stepLength;
            step++;
        }
    }

    schedule();
    playing = { mode, out, timer: window.setInterval(schedule, 200) };
}
