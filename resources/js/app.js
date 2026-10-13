import '../css/journal-presentation.css';
import { initializeArtworkViewer } from './artwork-viewer.js';
import { initializeHomeHero } from './home-hero.js';
import { initializeJournalPresentation } from './journal-presentation.js';
import { initializeMatomoTracking } from './matomo.js';
import { initializePublicNavigation } from './public-navigation.js';
import { advancePublicFolio } from './public-folio-motion.js';
import { applyPublicLayoutFromMeta, initializePublicSpaLinks } from './public-spa.js';

// Scripts and document listeners are persistent; the page-specific DOM is not.
let currentContent = null;
let disposers = [];
let visitPending = false;

function initializePublicApplication() {
    const content = document.querySelector('#content');
    if (!content || content === currentContent) return;

    for (const dispose of disposers) dispose?.();
    disposers = [];
    currentContent = content;

    const root = document.body;
    disposers.push(initializeArtworkViewer(root));
    initializeHomeHero(root);
    disposers.push(initializeJournalPresentation());
    disposers.push(initializeMatomoTracking(root));
    disposers.push(initializePublicNavigation());
}

// This event fires for ACTUAL Livewire visits, not the initial page load.
document.addEventListener('livewire:navigate', () => {
    visitPending = true;
});

document.addEventListener('livewire:navigating', (event) => {
    for (const dispose of disposers) dispose?.();
    disposers = [];
    currentContent = null;
    // The new response has another CSP nonce. Copy its authored layout
    // metadata at the documented post-swap boundary before Alpine starts.
    event.detail.onSwap?.(applyPublicLayoutFromMeta);
});

document.addEventListener('livewire:navigated', () => {
    initializePublicApplication();
    if (!visitPending) return;
    visitPending = false;

    // Real navigation is now complete. The persisted contour exists in the
    // new document and CSS can render the interpolation on the next frame.
    advancePublicFolio();
    document.body.dataset.publicSpaVisit = 'true';
});

// Delegated, eligible content links use Livewire's official navigate API.
// No second Fetch/History router, and no late-added inert wire:navigate attrs.
initializePublicSpaLinks();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePublicApplication, { once: true });
} else {
    initializePublicApplication();
}
