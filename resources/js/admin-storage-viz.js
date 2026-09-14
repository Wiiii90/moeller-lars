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
const MIN_VISIBLE_CAPACITY_SHARE = 0.18;
const MAX_VISIBLE_USED_SLICES = 5;

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
        accent: token('--admin-accent', '#b45309'),
        remaining: token('--storage-color-remaining', '#e8e8e5'),
        chart: [
            token('--storage-target-1', '#b65f19'),
            token('--storage-target-2', '#c07a28'),
            token('--storage-target-3', '#9d5a3f'),
            token('--storage-target-4', '#b9864b'),
            token('--storage-target-5', '#8d664c'),
            token('--storage-target-6', '#c58b63'),
            token('--storage-target-7', '#a06f4e'),
            token('--storage-target-8', '#7f6a58'),
        ],
    };
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, Number(value) || 0));
}

function formatBytes(value) {
    const bytes = Math.max(0, Number(value) || 0);
    if (bytes < 1000) return `${Math.round(bytes)} B`;

    const units = ['KB', 'MB', 'GB', 'TB'];
    let amount = bytes / 1000;
    let unit = units[0];
    for (let index = 1; index < units.length && amount >= 1000; index += 1) {
        amount /= 1000;
        unit = units[index];
    }

    const decimals = amount >= 100 ? 0 : amount >= 10 ? 1 : 2;
    return `${amount.toFixed(decimals).replace(/\.0+$|(?<=\.[0-9])0$/, '')} ${unit}`;
}

function displayRowLabel(row) {
    const area = row?.area || row?.key;
    if (area === 'site-identity') return 'Site icon';

    return row?.label || row?.key || 'Storage';
}

function inspectorNodes(element) {
    return {
        title: element.querySelector('[data-admin-viz-inspector-title]'),
        meta: element.querySelector('[data-admin-viz-inspector-meta]'),
    };
}

function defaultInspector(config) {
    if (! config.measurement_available) {
        return { title: '—', meta: 'No measurement' };
    }
    if (! config.configured || config.percent === null) {
        return { title: config.authoritative || '—', meta: 'No allowance configured' };
    }

    const usedPercent = clamp(config.percent, 0, 100);
    return {
        title: config.authoritative || '—',
        meta: `${usedPercent.toFixed(1)}% used · ${config.remaining || '—'} free`,
    };
}

function setInspector(element, copy) {
    const nodes = inspectorNodes(element);
    if (! nodes.title || ! nodes.meta) return;

    nodes.title.textContent = copy.title;
    nodes.meta.textContent = copy.meta;
}

function resetInspector(element, config) {
    setInspector(element, defaultInspector(config));
}

function inspectSlice(element, config, datum) {
    if (! datum || datum.key === 'remaining') {
        setInspector(element, {
            title: 'Remaining',
            meta: `${config.remaining || '—'} · ${(Number(datum?.capacityShare) || 0).toFixed(1)}% of capacity`,
        });
        return;
    }

    const files = Number(datum.files) || 0;
    const share = Number(datum.capacityShare) || 0;
    const members = Array.isArray(datum.members) && datum.members.length > 0
        ? ` · ${datum.members.join(' · ')}`
        : '';

    setInspector(element, {
        title: datum.name || 'Storage',
        meta: `${datum.displayBytes || '—'} · ${files} ${files === 1 ? 'file' : 'files'} · ${share.toFixed(share < 0.1 ? 2 : 1)}% of capacity${members}`,
    });
}

function unavailableOption(element, config, compact) {
    const colors = palette(element);

    return {
        animation: false,
        stateAnimation: { duration: 0 },
        graphic: compact ? [
            {
                type: 'text',
                silent: true,
                left: 'center',
                top: '43%',
                style: {
                    text: config.measurement_available ? (config.authoritative || '—') : '—',
                    fill: colors.text,
                    fontSize: 15,
                    fontWeight: 600,
                    fontFamily: 'inherit',
                    textAlign: 'center',
                },
            },
            {
                type: 'text',
                silent: true,
                left: 'center',
                top: '57%',
                style: {
                    text: config.measurement_available ? 'No allowance' : 'No measurement',
                    fill: colors.muted,
                    fontSize: 8,
                    fontWeight: 600,
                    fontFamily: 'inherit',
                    textAlign: 'center',
                },
            },
        ] : [],
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

function usedSlices(config, colors, compact) {
    const usedPercent = clamp(config.percent, 0, 100);
    const rows = storageRows(config, compact);
    const totalUsedBytes = rows.reduce((sum, row) => sum + (Number(row.bytes) || 0), 0);
    if (totalUsedBytes <= 0 || usedPercent <= 0) return [];

    const candidates = rows.map((row) => {
        const bytes = Number(row.bytes) || 0;
        return {
            row,
            bytes,
            capacityShare: usedPercent * (bytes / totalUsedBytes),
        };
    }).filter((entry) => entry.bytes > 0 && entry.capacityShare > 0);

    if (compact) {
        return candidates.map((entry, index) => sliceFromEntry(entry, colors.chart[index % colors.chart.length], true));
    }

    const visible = [];
    const grouped = [];
    for (const entry of candidates) {
        if (entry.capacityShare >= MIN_VISIBLE_CAPACITY_SHARE && visible.length < MAX_VISIBLE_USED_SLICES) {
            visible.push(entry);
        } else {
            grouped.push(entry);
        }
    }

    const slices = visible.map((entry, index) => sliceFromEntry(entry, colors.chart[index % colors.chart.length], false));
    if (grouped.length > 0) {
        const bytes = grouped.reduce((sum, entry) => sum + entry.bytes, 0);
        const files = grouped.reduce((sum, entry) => sum + (Number(entry.row.files) || 0), 0);
        const capacityShare = grouped.reduce((sum, entry) => sum + entry.capacityShare, 0);
        slices.push({
            key: 'other',
            name: 'Other',
            area: 'other',
            areaLabel: 'Other',
            value: capacityShare,
            displayBytes: formatBytes(bytes),
            files,
            capacityShare,
            members: grouped.slice(0, 3).map((entry) => displayRowLabel(entry.row)),
            itemStyle: {
                color: colors.chart[slices.length % colors.chart.length],
                opacity: 0.84,
            },
            emphasis: {
                focus: 'none',
                scale: false,
                itemStyle: { opacity: 1 },
            },
        });
    }

    return slices;
}

function sliceFromEntry(entry, color, compact) {
    const row = entry.row;
    const area = row.area || row.key || 'referenced';

    return {
        key: row.key,
        name: displayRowLabel(row),
        area,
        areaLabel: area === 'site-identity' ? 'Appearance' : (row.area_label || row.label || area),
        value: entry.capacityShare,
        displayBytes: row.display_bytes || formatBytes(entry.bytes),
        files: Number(row.files) || 0,
        capacityShare: entry.capacityShare,
        itemStyle: {
            color,
            opacity: compact ? 1 : 0.84,
        },
        emphasis: compact ? {
            disabled: true,
        } : {
            focus: 'none',
            scale: false,
            itemStyle: { opacity: 1 },
        },
    };
}

function storageSlices(config, colors, compact) {
    const usedPercent = clamp(config.percent, 0, 100);
    const slices = usedSlices(config, colors, compact);

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
                opacity: compact ? 1 : 0.84,
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
                color: colors.remaining,
                opacity: 1,
            },
            emphasis: {
                disabled: true,
            },
        });
    }

    return slices;
}

function compactGraphics(colors, config, usedPercent) {
    return [
        {
            type: 'text',
            silent: true,
            left: 'center',
            top: '39%',
            style: {
                text: config.authoritative || '—',
                fill: colors.text,
                fontSize: 15,
                fontWeight: 620,
                fontFamily: 'inherit',
                textAlign: 'center',
            },
        },
        {
            type: 'text',
            silent: true,
            left: 'center',
            top: '54%',
            style: {
                text: `${usedPercent.toFixed(1)}% used`,
                fill: colors.muted,
                fontSize: 8,
                fontWeight: 600,
                fontFamily: 'inherit',
                textAlign: 'center',
            },
        },
    ];
}

function storageOption(element, config) {
    const compact = element.classList.contains('is-compact');
    if (! config.configured || ! config.measurement_available || config.percent === null) {
        return unavailableOption(element, config, compact);
    }

    const colors = palette(element);
    const usedPercent = clamp(config.percent, 0, 100);
    const slices = storageSlices(config, colors, compact);

    return {
        animation: false,
        stateAnimation: { duration: 0 },
        graphic: compact ? compactGraphics(colors, config, usedPercent) : [],
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

function cancelInspectorReset(entry) {
    if (entry.inspectorFrame === null) return;
    window.cancelAnimationFrame(entry.inspectorFrame);
    entry.inspectorFrame = null;
}

function bindInspector(element, entry) {
    if (element.classList.contains('is-compact')) return;

    entry.chart.on('mouseover', (params) => {
        if (params?.seriesId !== 'storage-capacity-donut') return;
        cancelInspectorReset(entry);
        inspectSlice(element, entry.config, params.data);
    });

    entry.chart.on('mouseout', (params) => {
        if (params?.seriesId !== 'storage-capacity-donut') return;
        cancelInspectorReset(entry);
        entry.inspectorFrame = window.requestAnimationFrame(() => {
            entry.inspectorFrame = null;
            resetInspector(element, entry.config);
        });
    });
}

function disposeElement(element) {
    const entry = instances.get(element);
    if (! entry) return;

    cancelInspectorReset(entry);
    if (entry.resizeFrame !== null) window.cancelAnimationFrame(entry.resizeFrame);
    if (entry.resizeObserver) entry.resizeObserver.disconnect();
    if (! entry.chart.isDisposed()) entry.chart.dispose();
    element.classList.remove('is-ready');
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
        existing.config = config;
        if (existing.signature !== signature || existing.theme !== theme) {
            existing.chart.setOption(storageOption(element, config), {
                notMerge: true,
                lazyUpdate: false,
                silent: true,
            });
            existing.signature = signature;
            existing.theme = theme;
        }
        resetInspector(element, config);
        element.classList.add('is-ready');

        return;
    }

    if (existing) disposeElement(element);

    const chart = echarts.init(surface, null, { renderer: 'canvas' });
    const entry = {
        chart,
        surface,
        config,
        signature,
        theme,
        resizeObserver: null,
        resizeFrame: null,
        inspectorFrame: null,
        width: 0,
        height: 0,
    };

    chart.setOption(storageOption(element, config), {
        notMerge: true,
        lazyUpdate: false,
        silent: true,
    });
    resetInspector(element, config);
    bindInspector(element, entry);
    element.classList.add('is-ready');

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
