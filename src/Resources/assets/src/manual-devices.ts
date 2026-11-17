/**
 * 1.12: self-contained desktop / phone views (Vite IIFE → `src/Resources/public/js/manual-devices.js`). The default
 * chapter template loads it (deferred, external: CSP-safe) only on chapters with device content when
 * `device_views.enabled` is true.
 */
import { registerDeviceViews } from './device-views';

registerDeviceViews();
