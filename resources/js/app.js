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
import { capturePublicFolio, advancePublicFolio } from './public-folio-motion.js';
import { applyPublicLayoutFromMeta, initializePublicSpaLinks } from './public-spa.js';

// The module and delegation are loaded once. Page-local resources aren't.
let currentContent = null;
let disposers = [];

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
    const root = document.body;
    disposers.push(initializeArtworkViewer(root));
    initializeHomeHero(root);
    disposers.push(initializeJournalPresentation());
    disposers.push(initializeMatomoTracking(root));
    disposers.push(initializePublicNavigation());
    advancePublicFolio();
}

document.addEventListener('livewire:navigating', (event) => {
    capturePublicFolio();
    disposePage();
    event.detail.onSwap?.(applyPublicLayoutFromMeta);
});
document.addEventListener('livewire:navigated', initializePage);

initializePublicSpaLinks();
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePage, { once: true });
} else {
    initializePage();
}
