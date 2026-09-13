import * as echarts from 'echarts/core';
import { SankeyChart } from 'echarts/charts';
import { GraphicComponent } from 'echarts/components';
import { SVGRenderer } from 'echarts/renderers';

echarts.use([
    SankeyChart,
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
        lineStrong: token('--admin-line-strong', '#bdbdbd'),
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
        graphic: [
            {
                type: 'text',
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

function storageFlow(config, colors, compact) {
    const usedPercent = clamp(config.percent, 0, 100);
    const freePercent = Math.max(0, 100 - usedPercent);
    const rows = (Array.isArray(config.breakdown) ? config.breakdown : [])
        .filter((row) => Number(row.bytes) > 0);
    const totalUsedBytes = rows.reduce((sum, row) => sum + (Number(row.bytes) || 0), 0);

    const nodes = [
        {
            name: 'capacity',
            depth: 0,
            displayLabel: `${config.allowance || '—'} capacity`,
            itemStyle: { color: colors.lineStrong },
        },
    ];
    const links = [];

    if (usedPercent > 0) {
        nodes.push({
            name: 'used',
            depth: 1,
            displayLabel: `${config.authoritative || '—'} used`,
            itemStyle: { color: colors.accent },
        });
        links.push({
            source: 'capacity',
            target: 'used',
            value: usedPercent,
        });
    }

    if (freePercent > 0) {
        nodes.push({
            name: 'free',
            depth: 1,
            displayLabel: `${config.remaining || '—'} free`,
            itemStyle: { color: colors.storage.remaining },
        });
        links.push({
            source: 'capacity',
            target: 'free',
            value: freePercent,
        });
    }

    if (! compact && usedPercent > 0 && totalUsedBytes > 0) {
        rows.forEach((row) => {
            const bytes = Number(row.bytes) || 0;
            const shareOfUsed = bytes / totalUsedBytes;
            const capacityShare = usedPercent * shareOfUsed;
            if (capacityShare <= 0) return;

            const nodeName = `area:${row.key}`;
            nodes.push({
                name: nodeName,
                depth: 2,
                displayLabel: row.label || row.key,
                itemStyle: {
                    color: colors.storage[row.key] || colors.accent,
                },
            });
            links.push({
                source: 'used',
                target: nodeName,
                value: capacityShare,
            });
        });
    }

    return { nodes, links };
}

function storageOption(element, config) {
    const compact = element.classList.contains('is-compact');
    if (! config.configured || ! config.measurement_available || config.percent === null) {
        return unavailableOption(element, config, compact);
    }

    const colors = palette(element);
    const flow = storageFlow(config, colors, compact);

    return {
        animation: false,
        tooltip: { show: false },
        series: [
            {
                id: 'storage-capacity-flow',
                type: 'sankey',
                data: flow.nodes,
                links: flow.links,
                left: compact ? '7%' : '4%',
                right: compact ? '7%' : '5%',
                top: compact ? '10%' : '7%',
                bottom: compact ? '10%' : '7%',
                orient: 'horizontal',
                nodeAlign: 'justify',
                nodeWidth: compact ? 5 : 8,
                nodeGap: compact ? 4 : 9,
                layoutIterations: 48,
                draggable: false,
                silent: true,
                emphasis: { disabled: true },
                label: {
                    show: ! compact,
                    color: colors.text,
                    fontSize: 9,
                    fontWeight: 600,
                    distance: 5,
                    formatter(params) {
                        return params.data?.displayLabel || '';
                    },
                },
                lineStyle: {
                    color: 'gradient',
                    opacity: 0.34,
                    curveness: 0.52,
                },
                levels: [
                    {
                        depth: 0,
                        itemStyle: { borderWidth: 0 },
                        lineStyle: { opacity: 0.26 },
                    },
                    {
                        depth: 1,
                        itemStyle: { borderWidth: 0 },
                        lineStyle: { opacity: 0.3 },
                    },
                    {
                        depth: 2,
                        itemStyle: { borderWidth: 0 },
                        lineStyle: { opacity: 0.38 },
                    },
                ],
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
