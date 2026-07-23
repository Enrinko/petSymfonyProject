import { Controller } from '@hotwired/stimulus';

/*
 * Переключатель темы «Ноктюрн».
 * Стартовое значение выставляет инлайн-скрипт в <head> (анти-flash);
 * здесь — только toggle по клику и запоминание явного выбора.
 * Stimulus (не React): кнопка нужна и на auth-страницах без React-корня.
 */
export default class extends Controller {
    toggle() {
        const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;

        try {
            localStorage.setItem('theme', next);
        } catch {
            // приватный режим без localStorage — тема живёт до перезагрузки
        }
    }
}
