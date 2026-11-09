/**
 * Client-only Home Hero preview clock. Source membership, ordering and weights
 * come from HomeHeroResolver; this does not write public presentation state.
 */
export function adminHomeHeroStage(config = {}) {
    const rows = (Array.isArray(config.rows) ? config.rows : [])
        .filter((row) => row && row.eligible !== false && Number.isInteger(Number(row.id)))
        .map((row) => ({
            id: Number(row.id),
            thumbnail_url: row.thumbnail_url || null,
            weight: Number.isFinite(Number(row.weight)) ? Number(row.weight) : null,
        }));

    const initialId = Number(config.currentId);
    const initialIndex = Math.max(0, rows.findIndex((row) => row.id === initialId));
    const intervalDays = Math.max(1, Number(config.rotationCount) || 1)
        * (config.rotationUnit === 'weeks' ? 7 : 1);
    const sampledAt = new Date(config.sampledAt);
    const nextAt = config.nextRotationAt ? new Date(config.nextRotationAt) : null;

    return {
        rows,
        strategy: config.strategy,
        activeIndex: initialIndex,
        simulatedAt: Number.isFinite(sampledAt.getTime()) ? sampledAt : new Date(),
        nextAt: nextAt && Number.isFinite(nextAt.getTime()) ? nextAt : null,
        timer: null,
        get activeId() { return this.rows[this.activeIndex]?.id ?? null; },
        get active() { return this.rows[this.activeIndex] ?? null; },
        get simulationLabel() {
            if (this.strategy === 'ordered') return 'Ordered · fixed first selection';
            if (this.strategy === 'random') return 'Random · new sample every 10 seconds';
            const at = this.simulatedAt.toISOString().slice(0, 10);
            const next = this.nextAt?.toISOString().replace('T', ' ').slice(0, 16) ?? '—';
            return 'Simulated ' + at + ' · next ' + next + ' UTC';
        },
        init() {
            if (this.rows.length === 0 || this.strategy === 'ordered') return;
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
                const total = this.rows.reduce((sum, row) => sum + Math.max(0, row.weight ?? 0), 0);
                let chance = Math.random() * (total > 0 ? total : this.rows.length);
                for (let i = 0; i < this.rows.length; i += 1) {
                    chance -= total > 0 ? Math.max(0, this.rows[i].weight ?? 0) : 1;
                    if (chance < 0 || i === this.rows.length - 1) {
                        this.activeIndex = i;
                        break;
                    }
                }
                return;
            }
            if (this.strategy !== 'sequential') return;
            this.simulatedAt = new Date(this.simulatedAt.getTime() + 86400000);
            if (!this.nextAt) return;
            const intervalMs = intervalDays * 86400000;
            while (this.simulatedAt.getTime() >= this.nextAt.getTime()) {
                this.activeIndex = (this.activeIndex + 1) % this.rows.length;
                this.nextAt = new Date(this.nextAt.getTime() + intervalMs);
            }
        },
    };
}
