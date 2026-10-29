/**
 * Vitest configuration for `src/Resources/assets/` — the Stimulus peer and the standalone lightbox entry (100% coverage gate).
 */
import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'jsdom',
    include: ['src/Resources/assets/**/*.test.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'text-summary', 'html'],
      reportsDirectory: 'coverage-ts',
      include: ['src/Resources/assets/**/*.ts'],
      exclude: ['src/Resources/assets/**/*.test.ts'],
      thresholds: {
        lines: 100,
        branches: 100,
        functions: 100,
        statements: 100,
      },
    },
  },
});
