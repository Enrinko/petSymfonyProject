const js = require('@eslint/js');
const tseslint = require('typescript-eslint');
const reactHooks = require('eslint-plugin-react-hooks');

module.exports = tseslint.config(
    {
        ignores: [
            'public/**',
            'node_modules/**',
            'vendor/**',
            'var/**',
            'projects/**',
            'assets/**/*.js',
            '*.js',
        ],
    },
    {
        files: ['assets/**/*.{ts,tsx}'],
        extends: [js.configs.recommended, ...tseslint.configs.recommended],
        plugins: { 'react-hooks': reactHooks },
        rules: {
            ...reactHooks.configs.recommended.rules,
            // Классический fetch-on-mount (setLoading в начале эффекта) — осознанный
            // паттерн проекта до появления слоя данных; правило v7 даёт ложные срабатывания.
            'react-hooks/set-state-in-effect': 'off',
            '@typescript-eslint/no-unused-vars': [
                'error',
                { argsIgnorePattern: '^_', varsIgnorePattern: '^_', ignoreRestSiblings: true },
            ],
        },
    },
);
