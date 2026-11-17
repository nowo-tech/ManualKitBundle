import { defineConfig } from 'vite';

/**
 * 1.11: builds the anchor redirects script to a single IIFE in `src/Resources/public/js/manual-anchors.js` (served as
 * `asset('js/manual-anchors.js', 'nowo_manual_kit')`). A second config because an IIFE build has one entry.
 */
export default defineConfig({
  build: {
    outDir: 'src/Resources/public/js',
    emptyOutDir: false,
    rollupOptions: {
      input: 'src/Resources/assets/src/manual-anchors.ts',
      output: {
        format: 'iife',
        entryFileNames: 'manual-anchors.js',
      },
    },
    minify: true,
    sourcemap: false,
  },
});
