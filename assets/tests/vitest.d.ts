/**
 * Типы кастомных матчеров vitest-axe (expect.extend в setup.ts).
 */
import 'vitest';
import type { AxeMatchers } from 'vitest-axe/matchers';

declare module 'vitest' {
    // Пустые интерфейсы — канонический способ подмешать матчеры в типы vitest
    /* eslint-disable @typescript-eslint/no-empty-object-type */
    interface Assertion extends AxeMatchers {}
    interface AsymmetricMatchersContaining extends AxeMatchers {}
    /* eslint-enable @typescript-eslint/no-empty-object-type */
}
