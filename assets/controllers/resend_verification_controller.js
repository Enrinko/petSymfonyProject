import { Controller } from '@hotwired/stimulus';

/**
 * Кнопка «Отправить ещё раз» в плашке неподтверждённого email.
 * После отправки блокируется и показывает результат прямо в кнопке.
 */
export default class extends Controller {
    static targets = ['button'];

    async resend() {
        const button = this.buttonTarget;
        button.disabled = true;
        button.textContent = 'Отправляем…';

        try {
            const response = await fetch('/api/verify-email/resend', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();

            button.textContent = response.ok ? 'Отправлено ✓' : (data.message ?? 'Не получилось');

            if (!response.ok) {
                button.disabled = false;
            }
        } catch {
            button.textContent = 'Ошибка сети';
            button.disabled = false;
        }
    }
}
