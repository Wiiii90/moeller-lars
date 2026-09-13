import * as echarts from 'echarts/core';
import { PieChart } from 'echarts/charts';
import { GraphicComponent } from 'echarts/components';
import { SVGRenderer } from 'echarts/renderers';

echarts.use([
    PieChart,
    GraphicComponent,
    SVGRenderer,
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

    return {
        text: token('--admin-text', '#181818'),
        muted: token('--admin-muted', '#707070'),
        surface: token('--admin-surface', '#ffffff'),
        accent: token('--admin-accent', '#b45309'),
        storage: {
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
        },
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

function storageSlices(config, colors) {
    const usedPercent = clamp(config.percent, 0, 100);
    const rows = (Array.isArray(config.breakdown) ? config.breakdown : [])
        .filter((row) => Number(row.bytes) > 0);
    const totalUsedBytes = rows.reduce((sum, row) => sum + (Number(row.bytes) || 0), 0);

    const slices = totalUsedBytes > 0
        ? rows.map((row) => {
            const bytes = Number(row.bytes) || 0;
            const capacityShare = usedPercent * (bytes / totalUsedBytes);

            return {
                key: row.key,
                name: row.label || row.key,
                value: capacityShare,
                displayBytes: row.display_bytes || '—',
                files: Number(row.files) || 0,
                itemStyle: {
                    color: colors.storage[row.key] || colors.accent,
                },
            };
        }).filter((slice) => slice.value > 0)
        : [];

    if (slices.length === 0 && usedPercent > 0) {
        slices.push({
            key: 'used',
            name: 'Used',
            value: usedPercent,
            displayBytes: config.authoritative || '—',
            files: 0,
            itemStyle: {
                color: colors.accent,
            },
        });
    }

    const remainingPercent = Math.max(0, 100 - usedPercent);
    if (remainingPercent > 0 || slices.length === 0) {
        slices.push({
            key: 'remaining',
            name: 'Remaining',
            value: remainingPercent > 0 ? remainingPercent : 100,
            displayBytes: config.remaining || '—',
            files: 0,
            itemStyle: {
                color: colors.storage.remaining,
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
    const slices = storageSlices(config, colors);
    const usedCopy = `${usedPercent.toFixed(usedPercent < 10 ? 1 : 0)}% of ${config.allowance || 'allowance'}`;

    return {
        animation: false,
        stateAnimation: { duration: 0 },
        graphic: [
            {
                type: 'text',
                silent: true,
                left: 'center',
                top: compact ? '39%' : '39%',
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
                    text: compact ? `${usedPercent.toFixed(1)}% used` : usedCopy,
                    fill: colors.muted,
                    fontSize: compact ? 8 : 10,
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
                radius: compact ? ['61%', '82%'] : ['59%', '82%'],
                center: ['50%', '50%'],
                startAngle: 90,
                clockwise: true,
                cursor: 'default',
                selectedMode: false,
                stillShowZeroSum: false,
                avoidLabelOverlap: true,
                label: { show: false },
                labelLine: { show: false },
                itemStyle: {
                    borderColor: colors.surface,
                    borderWidth: compact ? 1 : 2,
                    borderRadius: compact ? 1 : 3,
                },
                emphasis: compact ? {
                    disabled: true,
                } : {
                    focus: 'none',
                    scale: false,
                    itemStyle: {
                        borderColor: colors.text,
                        borderWidth: 3,
                        opacity: 1,
                    },
                },
                data: slices,
            },
        ],
    };
}

function disposeElement(element) {
    const entry = instances.get(element);
    if (! entry) return;

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

    if (
        existing
        && existing.signature === signature
        && existing.theme === theme
        && existing.surface === surface
    ) return;

    if (existing) disposeElement(element);

    const chart = echarts.init(surface, null, { renderer: 'svg' });
    const entry = {
        chart,
        surface,
        signature,
        theme,
        resizeObserver: null,
    };

    chart.setOption(storageOption(element, config), { notMerge: true });

    entry.resizeObserver = new ResizeObserver(() => chart.resize());
    entry.resizeObserver.observe(surface);
    instances.set(element, entry);
}

export function refreshStorageVisualizations() {
    for (const element of Array.from(instances.keys())) {
        if (! element.isConnected) disposeElement(element);
    }

    document.querySelectorAll('[data-admin-viz="storage-capacity"]').forEach(mountElement);
}
