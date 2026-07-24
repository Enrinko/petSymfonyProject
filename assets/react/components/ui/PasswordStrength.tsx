interface PasswordStrengthProps {
    value: string;
}

/**
 * Индикатор силы пароля — чистая UX-подсказка, не валидация:
 * серверная политика (длина ≥ 10 + PasswordStrength + NotCompromised)
 * остаётся источником истины. Шкала 0–4 без библиотек:
 * +1 длина ≥ 10, +1 длина ≥ 14, +1 буквы+цифры, +1 спецсимвол или регистры.
 */
const LABELS = ['слабый', 'так себе', 'нормальный', 'хороший', 'отличный'] as const;

function score(value: string): number {
    let points = 0;

    if (value.length >= 10) {
        points += 1;
    }

    if (value.length >= 14) {
        points += 1;
    }

    if (/[a-zа-яё]/i.test(value) && /\d/.test(value)) {
        points += 1;
    }

    if (/[^a-zа-яё0-9]/i.test(value) || (/[a-zа-яё]/.test(value) && /[A-ZА-ЯЁ]/.test(value))) {
        points += 1;
    }

    // Без базовой длины «бонусные» очки не считаются: короткий пароль — слабый
    return value.length >= 10 ? points : 0;
}

export default function PasswordStrength({ value }: PasswordStrengthProps) {
    if (value === '') {
        return null;
    }

    const level = score(value);

    return (
        <div className={`pwd-meter pwd-meter--l${level}`}>
            <div className="pwd-meter__track" aria-hidden="true">
                {[1, 2, 3, 4].map((segment) => (
                    <i key={segment} className={segment <= level ? 'pwd-meter__seg pwd-meter__seg--on' : 'pwd-meter__seg'} />
                ))}
            </div>
            <span className="pwd-meter__label" aria-live="polite">{LABELS[level]}</span>
        </div>
    );
}
