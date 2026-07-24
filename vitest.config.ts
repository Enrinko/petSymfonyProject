import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

// Тесты живут в assets/tests/ (НЕ в assets/react/): require.context в app.ts
// регистрирует каждый файл дерева controllers/ как React-компонент,
// ко-локация затащила бы тесты в прод-бандл.
export default defineConfig({
    plugins: [react()],
    test: {
        environment: 'jsdom',
        include: ['assets/tests/**/*.test.{ts,tsx}'],
        setupFiles: ['assets/tests/setup.ts'],
        restoreMocks: true,
        unstubGlobals: true,
    },
});
