/**
 * Client-only Home Hero preview clock. Source membership, ordering and weights
 * come from HomeHeroResolver; this does not write public presentation state.
 */
export function adminHomeHeroStage(config = {}) {
    const rows = (Array.isArray(config.rows) ? config.rows : [])
        .filter((row) => row && row.eligible !== false && typeof row.id === 'string' && /^(artwork|media):[1-9]\d*$/.test(row.id))
        .map((row) => ({
            id: row.id,
            thumbnail_url: row.thumbnail_url || null,
            weight: Number.isFinite(Number(row.weight)) ? Number(row.weight) : null,
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
        get simulationLabel() {
            if (this.strategy === 'ordered') return 'Top Only · first sourced candidate';
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
                this.nextAt = monthly
                    ? monthAt(++this.monthIndex + 1)
                    : new Date(this.nextAt.getTime() + intervalMs);
            }
        },
    };
}
