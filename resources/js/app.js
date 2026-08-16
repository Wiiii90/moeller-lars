import { initializeArtworkViewer } from './artwork-viewer.js';
import { initializeMatomoTracking } from './matomo.js';

function initializePublicApplication() {
    initializeArtworkViewer();
    initializeMatomoTracking();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePublicApplication, { once: true });
} else {
    initializePublicApplication();
}
