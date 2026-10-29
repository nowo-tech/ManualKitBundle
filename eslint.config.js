import eslint from '@eslint/js';
import tseslint from 'typescript-eslint';

export default tseslint.config(
  {
    ignores: [
      'src/Resources/assets/dist/**',
      'src/Resources/public/**',
      'vendor/**',
      'node_modules/**',
      'coverage/**',
    ],
  },
  eslint.configs.recommended,
  ...tseslint.configs.recommended,
  {
    files: ['src/Resources/assets/**/*.ts'],
    rules: {
      '@typescript-eslint/no-unused-vars': [
        'error',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
      ],
    },
  },
  {
    files: ['src/Resources/assets/**/*.ts'],
    ignores: ['**/*.test.ts'],
    rules: {
      // Captions go through textContent; no HTML string ever reaches the page.
      'no-restricted-syntax': [
        'error',
        {
          selector: 'AssignmentExpression[left.property.name=/^(innerHTML|outerHTML)$/]',
          message: 'Use textContent / DOM nodes.',
        },
        {
          selector: "CallExpression[callee.property.name='insertAdjacentHTML']",
          message: 'Build DOM nodes instead of HTML strings.',
        },
      ],
      'no-eval': 'error',
      'no-implied-eval': 'error',
    },
  },
);
