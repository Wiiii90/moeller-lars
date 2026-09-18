import '../css/journal-presentation.css';
import { initializeArtworkViewer } from './artwork-viewer.js';
import { initializeJournalPresentation } from './journal-presentation.js';
import { initializeMatomoTracking } from './matomo.js';
import { initializePublicNavigation } from './public-navigation.js';

function initializePublicApplication() {
    initializeArtworkViewer();
    initializeJournalPresentation();
    initializeMatomoTracking();
    initializePublicNavigation();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePublicApplication, { once: true });
} else {
    initializePublicApplication();
}
