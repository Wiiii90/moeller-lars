import '../css/app.css';
import '../css/public-content.css';
import '../css/public-presentation.css';
import '../css/custom-pages.css';
import '../css/journal-presentation.css';

import { initializeArtworkViewer } from './artwork-viewer.js';
import { initializeGalleryImageLoading } from './public-gallery-loading.js';
import { initializeHomeHero } from './home-hero.js';
import { initializeJournalPresentation } from './journal-presentation.js';
import { initializeMatomoTracking } from './matomo.js';
import { initializePublicNavigation } from './public-navigation.js';
import { initializePublicHeader } from './public-header.js';
import { capturePublicFolio, advancePublicFolio, animatePublicHeroChange } from './public-folio-motion.js';
import { applyPublicLayoutFromMeta, initializePublicSpaLinks } from './public-spa.js';
import { revealLinkedLegalSection } from './public-legal.js';

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
    disposers.push(initializeGalleryImageLoading(root));
    initializeHomeHero(root, (change) => animatePublicHeroChange(change, root));
    disposers.push(initializeJournalPresentation());
    disposers.push(initializeMatomoTracking(root));
    disposers.push(initializePublicHeader());
    disposers.push(initializePublicNavigation());
    advancePublicFolio();
    revealLinkedLegalSection();
}

document.addEventListener('livewire:navigating', (event) => {
    capturePublicFolio();
    disposePage();
    event.detail.onSwap?.(applyPublicLayoutFromMeta);
});
document.addEventListener('livewire:navigated', initializePage);
window.addEventListener('hashchange', () => revealLinkedLegalSection());

initializePublicSpaLinks();
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePage, { once: true });
} else {
    initializePage();
}
