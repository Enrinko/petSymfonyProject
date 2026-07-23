/**
 * Звуковые отклики интерфейса — синтез Web Audio API, ноль аудиофайлов.
 *
 * Правила:
 * - по умолчанию ВЫКЛЮЧЕНО; включается переключателем (localStorage 'ui-sound') —
 *   явный opt-in строже системного prefers-reduced-motion, поэтому включённый
 *   пользователем звук НЕ глушится настройкой анимаций ОС;
 * - не чаще раза в секунду (защита от «пулемёта»);
 * - AudioContext создаётся лениво из обработчиков событий (autoplay policy).
 */

export const SOUND_STORAGE_KEY = 'ui-sound';
const THROTTLE_MS = 1000;

interface Note {
    freq: number;
    at: number;      // смещение старта, сек
    dur: number;     // длительность, сек
    gain?: number;
}

// Пресеты — «партитуры» откликов
export const PRESETS: Record<'success' | 'error' | 'login' | 'notify', Note[]> = {
    // мажорное арпеджио C5–E5–G5
    success: [
        { freq: 523.25, at: 0, dur: 0.16 },
        { freq: 659.25, at: 0.09, dur: 0.16 },
        { freq: 783.99, at: 0.18, dur: 0.22 },
    ],
    // малая секунда — приглушённый диссонанс
    error: [
        { freq: 311.13, at: 0, dur: 0.28, gain: 0.5 },
        { freq: 329.63, at: 0, dur: 0.28, gain: 0.5 },
    ],
    // камертон «ля» 440 Гц
    login: [{ freq: 440, at: 0, dur: 0.35 }],
    // одна нота
    notify: [{ freq: 783.99, at: 0, dur: 0.14 }],
};

/** Чистая гейт-логика (тестируется без DOM). */
export function canPlay(enabled: boolean, now: number, lastPlayedAt: number): boolean {
    if (!enabled) {
        return false;
    }

    return now - lastPlayedAt >= THROTTLE_MS;
}

export function isSoundEnabled(): boolean {
    try {
        return localStorage.getItem(SOUND_STORAGE_KEY) === 'on';
    } catch {
        return false;
    }
}

export function setSoundEnabled(on: boolean): void {
    try {
        localStorage.setItem(SOUND_STORAGE_KEY, on ? 'on' : 'off');
    } catch {
        // приватный режим — состояние не переживёт перезагрузку
    }
}

let audioContext: AudioContext | null = null;
let lastPlayedAt = 0;

function scheduleNote(ctx: AudioContext, note: Note): void {
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    const start = ctx.currentTime + note.at;
    const peak = 0.12 * (note.gain ?? 1);

    osc.type = 'triangle';
    osc.frequency.value = note.freq;

    // Огибающая: быстрый подъём, плавный спад — «тёплая» нота без щелчков
    gain.gain.setValueAtTime(0, start);
    gain.gain.linearRampToValueAtTime(peak, start + 0.008);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + note.dur);

    osc.connect(gain).connect(ctx.destination);
    osc.start(start);
    osc.stop(start + note.dur + 0.02);
}

export function playSound(kind: keyof typeof PRESETS): void {
    if (!canPlay(isSoundEnabled(), Date.now(), lastPlayedAt)) {
        return;
    }

    try {
        audioContext ??= new AudioContext();

        if (audioContext.state === 'suspended') {
            void audioContext.resume();
        }

        for (const note of PRESETS[kind]) {
            scheduleNote(audioContext, note);
        }

        lastPlayedAt = Date.now();
    } catch {
        // нет Web Audio — просто молчим
    }
}
