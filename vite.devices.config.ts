import { defineConfig } from 'vite';

/**
 * 1.12: builds the desktop / phone views script to a single IIFE in `src/Resources/public/js/manual-devices.js` (served as
 * `asset('js/manual-devices.js', 'nowo_manual_kit')`). A second config because an IIFE build has one entry.
 */
export default defineConfig({
  build: {
    outDir: 'src/Resources/public/js',
    emptyOutDir: false,
    rollupOptions: {
      input: 'src/Resources/assets/src/manual-devices.ts',
      output: {
        format: 'iife',
        entryFileNames: 'manual-devices.js',
      },
    },
    minify: true,
    sourcemap: false,
  },
});
