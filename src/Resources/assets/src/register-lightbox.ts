import { Application } from '@hotwired/stimulus';
import ManualLightboxController from '../stimulus-peers/manual_lightbox_controller';

/** Stimulus identifier the default chapter template uses (`data-controller="nowo-manual-lightbox"`). */
export const LIGHTBOX_IDENTIFIER = 'nowo-manual-lightbox';

/**
 * Register the manual lightbox controller on a Stimulus application.
 *
 * @param application Application to register on; a new one is started on the document when omitted.
 * @returns The application the controller is registered on.
 */
export function registerManualLightbox(application?: Application): Application {
  const app = application ?? Application.start();
  app.register(LIGHTBOX_IDENTIFIER, ManualLightboxController);

  return app;
}
