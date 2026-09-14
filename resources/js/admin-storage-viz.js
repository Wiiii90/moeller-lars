import * as echarts from 'echarts/core';
import { PieChart } from 'echarts/charts';
import { GraphicComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([
    PieChart,
    GraphicComponent,
    CanvasRenderer,
]);

const instances = new Map();

function readConfig(element) {
    const node = element.querySelector(':scope > script[data-admin-viz-config]');
    if (! node) return null;

    try {
        return JSON.parse(node.textContent || '{}');
    } catch (error) {
        console.error('Invalid Storage visualization config.', error);
        return null;
    }
}

function chartSurface(element) {
    return element.querySelector(':scope > [data-admin-viz-surface]') || element;
}

function resolveCssColor(element, variable, fallback) {
    const probe = document.createElement('span');
    probe.style.cssText = `position:absolute;pointer-events:none;opacity:0;color:var(${variable}, ${fallback})`;
    element.appendChild(probe);
    const color = getComputedStyle(probe).color || fallback;
    probe.remove();

    return color;
}

function palette(element) {
    const token = (name, fallback) => resolveCssColor(element, name, fallback);
    const storage = {
        galleries: token('--storage-color-galleries', '#b45309'),
        journal: token('--storage-color-journal', '#3f6f92'),
        cv: token('--storage-color-cv', '#76558f'),
        home: token('--storage-color-home', '#4f7d5b'),
        'custom-pages': token('--storage-color-custom-pages', '#c47a2c'),
        'site-identity': token('--storage-color-site-identity', '#2f7a78'),
        referenced: token('--storage-color-referenced', '#687386'),
        shared: token('--storage-color-shared', '#a15578'),
        unassigned: token('--storage-color-unassigned', '#8b6a38'),
        uncatalogued: token('--storage-color-uncatalogued', '#b42318'),
        remaining: token('--storage-color-remaining', '#e8e8e5'),
    };

    return {
        text: token('--admin-text', '#181818'),
        muted: token('--admin-muted', '#707070'),
        accent: token('--admin-accent', '#b45309'),
        storage,
        chart: [
            storage.galleries,
            storage.journal,
            storage.cv,
            storage['site-identity'],
            storage.home,
            storage.shared,
            storage.referenced,
            storage['custom-pages'],
            storage.unassigned,
        ],
    };
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, Number(value) || 0));
}

function unavailableOption(element, config, compact) {
    const colors = palette(element);

    return {
        animation: false,
        stateAnimation: { duration: 0 },
        graphic: [
            {
                type: 'text',
                silent: true,
                left: 'center',
                top: compact ? '43%' : '45%',
                style: {
                    text: config.measurement_available ? (config.authoritative || '—') : '—',
                    fill: colors.text,
                    fontSize: compact ? 15 : 22,
                    fontWeight: 600,
                    fontFamily: 'inherit',
                    textAlign: 'center',
                },
            },
            {
                type: 'text',
                silent: true,
                left: 'center',
                top: compact ? '57%' : '56%',
                style: {
                    text: config.measurement_available ? 'No allowance' : 'No measurement',
                    fill: colors.muted,
                    fontSize: compact ? 8 : 10,
                    fontWeight: 600,
                    fontFamily: 'inherit',
                    textAlign: 'center',
                },
            },
        ],
        series: [],
    };
}

function storageRows(config, compact) {
    if (! compact && Array.isArray(config.segments) && config.segments.length > 0) {
        return config.segments.filter((row) => Number(row.bytes) > 0);
    }

    return (Array.isArray(config.breakdown) ? config.breakdown : [])
        .filter((row) => Number(row.bytes) > 0)
        .map((row) => ({
            ...row,
            area: row.key,
            area_label: row.label,
        }));
}

function storageSlices(config, colors, compact) {
    const usedPercent = clamp(config.percent, 0, 100);
    const rows = storageRows(config, compact);
    const totalUsedBytes = rows.reduce((sum, row) => sum + (Number(row.bytes) || 0), 0);

    const slices = totalUsedBytes > 0
        ? rows.map((row, index) => {
            const bytes = Number(row.bytes) || 0;
            const capacityShare = usedPercent * (bytes / totalUsedBytes);
            const area = row.area || row.key || 'referenced';
            const color = compact
                ? (colors.storage[area] || colors.storage[row.key] || colors.accent)
                : colors.chart[index % colors.chart.length];

            return {
                key: row.key,
                name: row.label || row.key,
                area,
                areaLabel: row.area_label || row.label || area,
                value: capacityShare,
                displayBytes: row.display_bytes || '—',
                files: Number(row.files) || 0,
                capacityShare,
                itemStyle: {
                    color,
                    opacity: compact ? 1 : 0.88,
                },
                emphasis: compact ? {
                    disabled: true,
                } : {
                    focus: 'none',
                    scale: false,
                    itemStyle: {
                        opacity: 1,
                    },
                },
            };
        }).filter((slice) => slice.value > 0)
        : [];

    if (slices.length === 0 && usedPercent > 0) {
        slices.push({
            key: 'used',
            name: 'Used',
            area: 'referenced',
            areaLabel: 'Used storage',
            value: usedPercent,
            displayBytes: config.authoritative || '—',
            files: 0,
            capacityShare: usedPercent,
            itemStyle: {
                color: colors.accent,
                opacity: compact ? 1 : 0.88,
            },
            emphasis: compact ? {
                disabled: true,
            } : {
                focus: 'none',
                scale: false,
                itemStyle: { opacity: 1 },
            },
        });
    }

    const remainingPercent = Math.max(0, 100 - usedPercent);
    if (remainingPercent > 0 || slices.length === 0) {
        slices.push({
            key: 'remaining',
            name: 'Remaining',
            area: 'remaining',
            areaLabel: 'Remaining',
            value: remainingPercent > 0 ? remainingPercent : 100,
            displayBytes: config.remaining || '—',
            files: 0,
            capacityShare: remainingPercent,
            itemStyle: {
                color: colors.storage.remaining,
                opacity: 1,
            },
            emphasis: {
                disabled: true,
            },
        });
    }

    return slices;
}

function storageOption(element, config) {
    const compact = element.classList.contains('is-compact');
    if (! config.configured || ! config.measurement_available || config.percent === null) {
        return unavailableOption(element, config, compact);
    }

    const colors = palette(element);
    const usedPercent = clamp(config.percent, 0, 100);
    const slices = storageSlices(config, colors, compact);
    const usedCopy = compact
        ? `${usedPercent.toFixed(1)}% used`
        : `${usedPercent.toFixed(usedPercent < 10 ? 1 : 0)}% used · ${config.remaining || '—'} free`;

    return {
        animation: false,
        stateAnimation: { duration: 0 },
        graphic: [
            {
                type: 'text',
                silent: true,
                left: 'center',
                top: compact ? '39%' : '38%',
                style: {
                    text: config.authoritative || '—',
                    fill: colors.text,
                    fontSize: compact ? 15 : 23,
                    fontWeight: 620,
                    fontFamily: 'inherit',
                    textAlign: 'center',
                },
            },
            {
                type: 'text',
                silent: true,
                left: 'center',
                top: compact ? '54%' : '53%',
                style: {
                    text: usedCopy,
                    fill: colors.muted,
                    fontSize: compact ? 8 : 9,
                    fontWeight: 600,
                    fontFamily: 'inherit',
                    textAlign: 'center',
                },
            },
        ],
        series: [
            {
                id: 'storage-capacity-donut',
                type: 'pie',
                silent: compact,
                radius: compact ? ['61%', '82%'] : ['54%', '84%'],
                center: ['50%', '50%'],
                startAngle: 90,
                clockwise: true,
                cursor: 'default',
                selectedMode: false,
                stillShowZeroSum: false,
                avoidLabelOverlap: true,
                padAngle: 0,
                label: { show: false },
                labelLine: { show: false },
                emphasis: compact ? {
                    disabled: true,
                } : {
                    focus: 'none',
                    scale: false,
                },
                data: slices,
            },
        ],
    };
}

function scheduleResize(entry, width, height) {
    const nextWidth = Math.round(width || 0);
    const nextHeight = Math.round(height || 0);
    if (nextWidth <= 0 || nextHeight <= 0) return;
    if (nextWidth === entry.width && nextHeight === entry.height) return;

    entry.width = nextWidth;
    entry.height = nextHeight;
    if (entry.resizeFrame !== null) return;

    entry.resizeFrame = window.requestAnimationFrame(() => {
        entry.resizeFrame = null;
        if (! entry.chart.isDisposed()) {
            entry.chart.resize({
                width: entry.width,
                height: entry.height,
                animation: { duration: 0 },
                silent: true,
            });
        }
    });
}

function disposeElement(element) {
    const entry = instances.get(element);
    if (! entry) return;

    if (entry.resizeFrame !== null) window.cancelAnimationFrame(entry.resizeFrame);
    if (entry.resizeObserver) entry.resizeObserver.disconnect();
    if (! entry.chart.isDisposed()) entry.chart.dispose();
    instances.delete(element);
}

function mountElement(element) {
    const config = readConfig(element);
    if (! config || config.kind !== 'storage-capacity') return;

    const surface = chartSurface(element);
    const signature = JSON.stringify(config);
    const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
    const existing = instances.get(element);

    if (existing && existing.surface === surface) {
        if (existing.signature !== signature || existing.theme !== theme) {
            existing.chart.setOption(storageOption(element, config), {
                notMerge: true,
                lazyUpdate: false,
                silent: true,
            });
            existing.signature = signature;
            existing.theme = theme;
        }

        return;
    }

    if (existing) disposeElement(element);

    const chart = echarts.init(surface, null, { renderer: 'canvas' });
    const entry = {
        chart,
        surface,
        signature,
        theme,
        resizeObserver: null,
        resizeFrame: null,
        width: 0,
        height: 0,
    };

    chart.setOption(storageOption(element, config), {
        notMerge: true,
        lazyUpdate: false,
        silent: true,
    });

    entry.resizeObserver = new ResizeObserver((entries) => {
        const rect = entries[0]?.contentRect;
        if (! rect) return;
        scheduleResize(entry, rect.width, rect.height);
    });
    entry.resizeObserver.observe(surface);
    instances.set(element, entry);
}

export function refreshStorageVisualizations() {
    for (const element of Array.from(instances.keys())) {
        if (! element.isConnected) disposeElement(element);
    }

    document.querySelectorAll('[data-admin-viz="storage-capacity"]').forEach(mountElement);
}
