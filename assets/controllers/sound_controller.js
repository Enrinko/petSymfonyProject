import { Controller } from '@hotwired/stimulus';

/*
 * Переключатель «Звуки интерфейса» (🔔/🔕). Состояние — localStorage 'ui-sound',
 * по умолчанию выключено. Дублирует ключ из assets/react/utils/sound.ts —
 * React-формы читают его при каждом воспроизведении.
 */
export default class extends Controller {
    connect() {
        this.refresh();
    }

    toggle() {
        const on = this.isOn();

        try {
            localStorage.setItem('ui-sound', on ? 'off' : 'on');
        } catch {
            /* приватный режим */
        }

        this.refresh();
    }

    isOn() {
        try {
            return localStorage.getItem('ui-sound') === 'on';
        } catch {
            return false;
        }
    }

    refresh() {
        this.element.dataset.soundOn = this.isOn() ? '1' : '0';
    }
}
