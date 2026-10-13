import '../css/app.css';
import '../css/public-content.css';
import '../css/public-presentation.css';
import '../css/custom-pages.css';
import '../css/journal-presentation.css';

import { initializeArtworkViewer } from './artwork-viewer.js';
import { initializeHomeHero } from './home-hero.js';
import { initializeJournalPresentation } from './journal-presentation.js';
import { initializeMatomoTracking } from './matomo.js';
import { initializePublicNavigation } from './public-navigation.js';
import { capturePublicFolio, advancePublicFolio, syncPublicFolioDebug } from './public-folio-motion.js';
import { applyPublicLayoutFromMeta, initializePublicSpaLinks } from './public-spa.js';

// The module and delegation are loaded once. Page-local resources aren't.
let currentContent = null;
let disposers = [];
let pendingVisit = false;

function disposePage() {
    for (const dispose of disposers) dispose?.();
    disposers = [];
    currentContent = null;
}

function initializePage() {
    const content = document.querySelector('#content');
    if (!content || content === currentContent) return;
    disposePage();
    currentContent = content;
    syncPublicFolioDebug();
    const root = document.body;
    disposers.push(initializeArtworkViewer(root));
    initializeHomeHero(root);
    disposers.push(initializeJournalPresentation());
    disposers.push(initializeMatomoTracking(root));
    disposers.push(initializePublicNavigation());
}

document.addEventListener('livewire:navigate', () => { pendingVisit = true; });
document.addEventListener('livewire:navigating', (event) => {
    capturePublicFolio();
    disposePage();
    event.detail.onSwap?.(applyPublicLayoutFromMeta);
});
document.addEventListener('livewire:navigated', () => {
    initializePage();
    if (!pendingVisit) return; // Livewire also fires this event on initial load.
    pendingVisit = false;
    advancePublicFolio();
    document.body.dataset.publicSpaVisit = 'true';
});

initializePublicSpaLinks();
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePage, { once: true });
} else {
    initializePage();
}
