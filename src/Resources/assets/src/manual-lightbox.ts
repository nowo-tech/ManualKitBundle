/**
 * Self-contained lightbox (Vite IIFE → `src/Resources/public/js/manual-lightbox.js`): Stimulus plus the bundle's
 * lightbox controller, for applications that do not register the peer in their own build. The default chapter
 * template loads it when `nowo_manual_kit.standalone_lightbox` is true.
 */
import { registerManualLightbox } from './register-lightbox';
import { createBundleLogger } from './logger';

declare const __MANUAL_KIT_BUILD_TIME__: string;

createBundleLogger('manual-kit/lightbox', {
  buildTime: typeof __MANUAL_KIT_BUILD_TIME__ !== 'undefined' ? __MANUAL_KIT_BUILD_TIME__ : undefined,
}).scriptLoaded();

registerManualLightbox();
