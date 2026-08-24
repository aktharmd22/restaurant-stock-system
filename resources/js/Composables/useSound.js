import { reactive } from 'vue';

/**
 * The seven alert sounds.
 *
 * They are synthesised with the Web Audio API rather than loaded as mp3 files:
 * nothing to download on a bad mobile connection, nothing to 404, and no
 * chance of a missing file turning into a silent failure - which is the exact
 * problem these sounds exist to prevent.
 *
 * Browsers refuse to play any audio until the user has interacted with the
 * page, so the audio is unlocked on the first click after sign-in. Until then
 * `state.blocked` is true and the header shows it, because an admin who thinks
 * sound is on when it is not will miss requests.
 */
const state = reactive({
    unlocked: false,
    blocked: true,
    enabled: true,
    volume: 0.8,
});

let context = null;

/** Each sound: a list of [frequency, startDelay, duration, waveform, peak]. */
const SOUNDS = {
    // Two rising chimes - a branch is asking for stock.
    new_request: [
        [880, 0, 0.18, 'sine', 1],
        [1174.7, 0.16, 0.28, 'sine', 1],
    ],
    // One soft ding - approved in full.
    approved: [[1046.5, 0, 0.32, 'sine', 0.9]],
    // Ding, then a lower note - some of it was cut.
    partial: [
        [1046.5, 0, 0.18, 'sine', 0.9],
        [783.99, 0.17, 0.34, 'sine', 0.85],
    ],
    // A low thud - not approved.
    rejected: [[164.81, 0, 0.36, 'triangle', 1]],
    // Short double beep - the goods have left the store.
    sent: [
        [1318.5, 0, 0.09, 'square', 0.35],
        [1318.5, 0.13, 0.09, 'square', 0.35],
    ],
    // A soft repeating pulse - something is running out.
    low_stock: [
        [659.25, 0, 0.12, 'sine', 0.6],
        [659.25, 0.2, 0.12, 'sine', 0.6],
        [659.25, 0.4, 0.12, 'sine', 0.6],
    ],
    // A short buzz - that did not work.
    failed: [[110, 0, 0.22, 'sawtooth', 0.5]],
};

function audioContext() {
    if (context) return context;

    const Ctor = window.AudioContext || window.webkitAudioContext;
    if (!Ctor) return null;

    context = new Ctor();
    return context;
}

/**
 * Called on the first user gesture. Playing a silent buffer is what actually
 * satisfies the browser's autoplay rules.
 */
async function unlock() {
    const ctx = audioContext();
    if (!ctx) return;

    try {
        if (ctx.state === 'suspended') await ctx.resume();

        const buffer = ctx.createBuffer(1, 1, 22050);
        const source = ctx.createBufferSource();
        source.buffer = buffer;
        source.connect(ctx.destination);
        source.start(0);

        state.unlocked = true;
        state.blocked = false;
    } catch {
        state.blocked = true;
    }
}

function playTone(ctx, [frequency, delay, duration, waveform, peak]) {
    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();

    oscillator.type = waveform;
    oscillator.frequency.value = frequency;

    const startAt = ctx.currentTime + delay;
    const level = Math.max(0.0001, state.volume * peak * 0.35);

    // A short attack and an exponential fall - a flat tone sounds like an alarm.
    gain.gain.setValueAtTime(0.0001, startAt);
    gain.gain.exponentialRampToValueAtTime(level, startAt + 0.012);
    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

    oscillator.connect(gain).connect(ctx.destination);
    oscillator.start(startAt);
    oscillator.stop(startAt + duration + 0.02);
}

function play(name) {
    if (!state.enabled) return;

    const parts = SOUNDS[name];
    if (!parts) return;

    const ctx = audioContext();
    if (!ctx) return;

    if (ctx.state === 'suspended') {
        // Not unlocked yet: say so rather than failing quietly.
        state.blocked = true;
        return;
    }

    try {
        parts.forEach((part) => playTone(ctx, part));
        state.blocked = false;
    } catch {
        state.blocked = true;
    }

    // A phone in a pocket, in a kitchen, is felt before it is heard.
    if (navigator.vibrate) {
        navigator.vibrate(name === 'failed' ? 80 : 200);
    }
}

/** Wire the unlock to the first click, once per page load. */
function listenForFirstGesture() {
    if (typeof window === 'undefined' || state.unlocked) return;

    const handler = () => {
        unlock();
        window.removeEventListener('pointerdown', handler);
        window.removeEventListener('keydown', handler);
    };

    window.addEventListener('pointerdown', handler, { once: true });
    window.addEventListener('keydown', handler, { once: true });
}

export function useSound() {
    return {
        state,
        play,
        unlock,
        listenForFirstGesture,
        configure({ enabled, volume }) {
            if (enabled !== undefined) state.enabled = enabled;
            if (volume !== undefined) state.volume = Math.min(1, Math.max(0, volume / 100));
        },
        /** Play a sample so the volume slider means something. */
        preview: () => play('approved'),
    };
}
