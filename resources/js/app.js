import { initializeArtworkViewer } from './artwork-viewer.js';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initializeArtworkViewer(), { once: true });
} else {
    initializeArtworkViewer();
}
