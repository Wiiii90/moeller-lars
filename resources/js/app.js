import { initializeArtworkViewer } from './artwork-viewer.js';
import { initializeMatomoTracking } from './matomo.js';
import { initializePublicNavigation } from './public-navigation.js';

function initializePublicApplication() {
    initializeArtworkViewer();
    initializeMatomoTracking();
    initializePublicNavigation();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePublicApplication, { once: true });
} else {
    initializePublicApplication();
}
