/**
 * Demo app entry (Pentatrion Vite + TypeScript): a Stimulus application with the bundle's opt-in peer — the manual's
 * figure lightbox — registered as `nowo-manual-lightbox`, the name the default chapter template uses.
 */
import { Application } from '@hotwired/stimulus';
import ManualLightboxController from '@bundle/stimulus-peers/manual_lightbox_controller';

const application = Application.start();
application.register('nowo-manual-lightbox', ManualLightboxController);

document.documentElement.dataset.demo = 'manual-kit';
