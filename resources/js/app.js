import '../css/journal-presentation.css';
import { initializeArtworkViewer } from './artwork-viewer.js';
import { initializeHomeHero } from './home-hero.js';
import { initializeJournalPresentation } from './journal-presentation.js';
import { initializeMatomoTracking } from './matomo.js';
import { initializePublicNavigation } from './public-navigation.js';
import { advancePublicFolio } from './public-folio-motion.js';
import { applyPublicLayoutFromMeta, markPublicSpaLinks } from './public-spa.js';

// Livewire swaps the public body's contents, but module scope and document
// event listeners persist. Dispose page-specific observers/listeners each visit.
let currentContent = null;
let disposers = [];

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
    markPublicSpaLinks(root);
}

// A new response has a fresh style nonce; copy its validated layout values
// into CSSOM before paint rather than relying on a newly inserted inline
// style element that the current document's CSP cannot authorize.
document.addEventListener('livewire:navigating', (event) => {
    advancePublicFolio();
    // Navigation is about to replace the old body: release modal maps, image
    // attention tracking and global observers before their elements detach.
    for (const dispose of disposers) dispose?.();
    disposers = [];
    currentContent = null;
    event.detail.onSwap?.(applyPublicLayoutFromMeta);
});
document.addEventListener('livewire:navigated', initializePublicApplication);

// Harmless initial fallback if scripts initialize in a different order.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePublicApplication, { once: true });
} else {
    initializePublicApplication();
}
