import { defineConfig } from 'vite';

/**
 * Builds the self-contained lightbox (Stimulus + the bundle's lightbox controller) to a single IIFE in
 * `src/Resources/public/js` for Symfony `assets:install` (served as `asset('js/manual-lightbox.js', 'nowo_manual_kit')`).
 * Applications with their own Stimulus build register `src/Resources/assets/stimulus-peers/manual_lightbox_controller.ts`
 * instead.
 */
export default defineConfig({
  build: {
    outDir: 'src/Resources/public/js',
    emptyOutDir: false,
    rollupOptions: {
      input: 'src/Resources/assets/src/manual-lightbox.ts',
      output: {
        format: 'iife',
        entryFileNames: 'manual-lightbox.js',
      },
    },
    minify: true,
    sourcemap: false,
  },
});
