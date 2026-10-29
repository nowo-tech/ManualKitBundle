/**
 * Self-contained lightbox (Vite IIFE → `src/Resources/public/js/manual-lightbox.js`): Stimulus plus the bundle's
 * lightbox controller, for applications that do not register the peer in their own build. The default chapter
 * template loads it when `nowo_manual_kit.standalone_lightbox` is true.
 */
import { registerManualLightbox } from './register-lightbox';

registerManualLightbox();
