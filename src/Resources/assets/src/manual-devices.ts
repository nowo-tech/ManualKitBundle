/**
 * 1.12: self-contained desktop / phone views (Vite IIFE → `src/Resources/public/js/manual-devices.js`). The default
 * chapter template loads it (deferred, external: CSP-safe) only on chapters with device content when
 * `device_views.enabled` is true.
 */
import { registerDeviceViews } from './device-views';
import { createBundleLogger } from './logger';

declare const __MANUAL_KIT_BUILD_TIME__: string;

createBundleLogger('manual-kit/devices', {
  buildTime: typeof __MANUAL_KIT_BUILD_TIME__ !== 'undefined' ? __MANUAL_KIT_BUILD_TIME__ : undefined,
}).scriptLoaded();

registerDeviceViews();
