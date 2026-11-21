/**
 * Client-only Gallery Hero preview clock. Source membership, ordering and weights
 * come from GalleryHeroResolver; this does not write public presentation state.
 */
export function adminGalleryHeroStage(config = {}) {
    const rows = (Array.isArray(config.rows) ? config.rows : [])
        .filter((row) => row && row.eligible !== false && typeof row.id === 'string' && /^(artwork|media):[1-9]\d*$/.test(row.id))
        .map((row) => ({
            id: row.id,
            visual_key: String(row.visual_key),
            thumbnail_url: row.thumbnail_url || null,
            weight: Number.isFinite(Number(row.weight)) ? Number(row.weight) : null,
            is_manual: row.is_manual === true,
            is_automatic: row.is_automatic === true,
        }));

    const initialId = String(config.currentId ?? '');
    const initialIndex = Math.max(0, rows.findIndex((row) => row.id === initialId));
    const intervalCount = Math.max(1, Math.floor(Number(config.rotationCount) || 1));
    const intervalDays = intervalCount * (config.rotationUnit === 'weeks' ? 7 : 1);
    const anchor = config.rotationStartedAt ? new Date(config.rotationStartedAt) : null;
    const monthly = config.rotationUnit === 'months' && anchor && Number.isFinite(anchor.getTime());
    const monthAt = (index) => {
        const first = new Date(Date.UTC(
            anchor.getUTCFullYear(), anchor.getUTCMonth() + index * intervalCount, 1,
            anchor.getUTCHours(), anchor.getUTCMinutes(), anchor.getUTCSeconds(), anchor.getUTCMilliseconds(),
        ));
        const lastDay = new Date(Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + 1, 0)).getUTCDate();
        first.setUTCDate(Math.min(anchor.getUTCDate(), lastDay));
        return first;
    };
    const sampledAt = new Date(config.sampledAt);
    let monthIndex = monthly && sampledAt < anchor ? -1 : 0;
    if (monthly && Number.isFinite(sampledAt.getTime()) && sampledAt >= anchor) {
        const wholeMonths = (sampledAt.getUTCFullYear() - anchor.getUTCFullYear()) * 12
            + sampledAt.getUTCMonth() - anchor.getUTCMonth();
        monthIndex = Math.max(0, Math.floor(wholeMonths / intervalCount));
        if (monthAt(monthIndex) > sampledAt) monthIndex -= 1;
    }
    const nextAt = config.nextRotationAt ? new Date(config.nextRotationAt) : null;

    return {
        rows,
        strategy: config.strategy,
        activeIndex: initialIndex,
        simulatedAt: Number.isFinite(sampledAt.getTime()) ? sampledAt : new Date(),
        nextAt: nextAt && Number.isFinite(nextAt.getTime()) ? nextAt : null,
        timer: null,
        monthIndex,
        get activeId() { return this.rows[this.activeIndex]?.id ?? null; },
        get active() { return this.rows[this.activeIndex] ?? null; },
        get randomAvailable() {
            return new Set(this.rows
                .filter((row) => (row.weight ?? 0) > 0)
                .map((row) => row.visual_key)).size > 1;
        },
        // The readonly next chance follows the currently simulated image.
        // The artist edits the original row.weight, never this distribution.
        nextChance(id) {
            const excluded = this.randomAvailable ? this.active?.visual_key : null;
            const distribution = this.rows.map((row) => ({
                id: row.id,
                weight: row.visual_key === excluded ? 0 : Math.max(0, row.weight ?? 0),
            }));
            const total = distribution.reduce((sum, row) => sum + row.weight, 0);
            const candidate = distribution.find((row) => row.id === id);
            return (total > 0 ? (candidate?.weight ?? 0) * 100 / total : 0).toFixed(2) + '%';
        },
        syncPreview() {
            if (!this.activeId) return;
            const frame = this.$el.querySelector('.admin-visual-stage__preview-pane iframe.admin-live-preview__frame');
            frame?.contentWindow?.postMessage({
                type: 'gallery-hero-preview-select',
                candidateKey: this.activeId,
            }, window.location.origin);
        },
        selectCandidate(id) {
            const index = this.rows.findIndex((row) => row.id === id);
            if (index < 0) return;
            if (index === this.activeIndex) this.syncPreview();
            else this.activeIndex = index;
        },
        init() {
            this.$watch('activeIndex', () => this.syncPreview());
            if (this.rows.length === 0 || this.strategy === 'ordered'
                || (this.strategy === 'random' && !this.randomAvailable)) return;
            this.timer = window.setInterval(
                () => this.tick(),
                this.strategy === 'random' ? 10000 : 3000,
            );
        },
        destroy() {
            if (this.timer !== null) window.clearInterval(this.timer);
            this.timer = null;
        },
        tick() {
            if (this.rows.length === 0) return;
            if (this.strategy === 'random') {
                if (!this.randomAvailable) return;
                const lastVisual = this.active?.visual_key;
                const choices = this.rows
                    .map((row, index) => ({
                        index,
                        weight: row.visual_key === lastVisual ? 0 : Math.max(0, row.weight ?? 0),
                    }))
                    .filter((row) => row.weight > 0);
                const total = choices.reduce((sum, row) => sum + row.weight, 0);
                if (total === 0) return;
                let draw = Math.random() * total;
                for (const choice of choices) {
                    if (draw < choice.weight) {
                        this.activeIndex = choice.index;
                        return;
                    }
                    draw -= choice.weight;
                }
                this.activeIndex = choices[choices.length - 1].index;
                return;
            }
            if (this.strategy !== 'sequential') return;
            this.simulatedAt = new Date(this.simulatedAt.getTime() + 86400000);
            if (!this.nextAt) return;
            const intervalMs = intervalDays * 86400000;
            while (this.simulatedAt.getTime() >= this.nextAt.getTime()) {
                this.activeIndex = (this.activeIndex + 1) % this.rows.length;
                this.nextAt = monthly
                    ? monthAt(++this.monthIndex + 1)
                    : new Date(this.nextAt.getTime() + intervalMs);
            }
        },
    };
}
