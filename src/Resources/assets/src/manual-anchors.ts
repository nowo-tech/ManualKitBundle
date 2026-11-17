/**
 * 1.11: self-contained anchor redirects (Vite IIFE → `src/Resources/public/js/manual-anchors.js`). The default chapter
 * template loads it (deferred, external: CSP-safe) only on pages whose `anchor_redirects` map is not empty.
 */
import { registerAnchorRedirects } from './anchor-redirects';

registerAnchorRedirects();
