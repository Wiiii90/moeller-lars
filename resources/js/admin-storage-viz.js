import * as echarts from 'echarts/core';
import { PieChart } from 'echarts/charts';
import { GraphicComponent, TooltipComponent } from 'echarts/components';
import { SVGRenderer } from 'echarts/renderers';

echarts.use([
    PieChart,
    GraphicComponent,
    TooltipComponent,
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

function withAlpha(color, alpha) {
    const match = color.match(/rgba?\(([^)]+)\)/i);
    if (! match) return color;

    const channels = match[1].split(',').slice(0, 3).map((value) => value.trim());
    return `rgba(${channels.join(', ')}, ${alpha})`;
}

function palette(element) {
    const token = (name, fallback) => resolveCssColor(element, name, fallback);

    return {
        text: token('--admin-text', '#181818'),
        muted: token('--admin-muted', '#707070'),
        line: token('--admin-line', '#dedede'),
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

function storageSlices(config, colors, selected) {
    const usedPercent = clamp(config.percent, 0, 100);
    const rows = Array.isArray(config.breakdown) ? config.breakdown : [];
    const slices = [];
    let allocated = 0;

    rows.forEach((row) => {
        const shareOfUsed = clamp(row.percent, 0, 100) / 100;
        const shareOfCapacity = usedPercent * shareOfUsed;
        if (shareOfCapacity <= 0) return;

        allocated += shareOfCapacity;
        const base = colors.storage[row.key] || colors.accent;
        const faded = selected && row.key !== selected;

        slices.push({
            key: row.key,
            name: row.label,
            value: shareOfCapacity,
            displayBytes: row.display_bytes || '—',
            files: Number(row.files) || 0,
            capacityShare: shareOfCapacity,
            selected: row.key === selected,
            itemStyle: {
                color: faded ? withAlpha(base, 0.28) : base,
                borderColor: colors.surface,
                borderWidth: 2,
                borderRadius: 4,
            },
        });
    });

    const unresolvedUsed = Math.max(0, usedPercent - allocated);
    if (unresolvedUsed > 0.01) {
        slices.push({
            key: 'referenced',
            name: 'Used',
            value: unresolvedUsed,
            displayBytes: config.authoritative || '—',
            files: 0,
            capacityShare: unresolvedUsed,
            itemStyle: {
                color: selected ? withAlpha(colors.accent, 0.28) : colors.accent,
                borderColor: colors.surface,
                borderWidth: 2,
                borderRadius: 4,
            },
        });
    }

    slices.push({
        key: 'remaining',
        name: 'Remaining',
        value: Math.max(0, 100 - usedPercent),
        displayBytes: config.remaining || '—',
        files: 0,
        capacityShare: Math.max(0, 100 - usedPercent),
        itemStyle: {
            color: colors.storage.remaining,
            borderColor: colors.surface,
            borderWidth: 2,
            borderRadius: 4,
        },
        tooltip: { show: false },
    });

    return slices;
}

function storageOption(element, config, state) {
    const colors = palette(element);
    const compact = element.classList.contains('is-compact');
    const selectedRow = (Array.isArray(config.breakdown) ? config.breakdown : [])
        .find((row) => row.key === state.selected) || null;

    if (! config.configured || ! config.measurement_available || config.percent === null) {
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

    const mainValue = selectedRow?.display_bytes || config.allowance || '—';
    const mainLabel = selectedRow?.label || `${config.authoritative || '—'} used`;
    const usedPercent = clamp(config.percent, 0, 100);
    const slices = storageSlices(config, colors, state.selected);

    return {
        animationDuration: compact ? 0 : 520,
        animationDurationUpdate: compact ? 0 : 320,
        animationEasing: 'cubicOut',
        animationEasingUpdate: 'cubicOut',
        tooltip: compact ? { show: false } : {
            trigger: 'item',
            backgroundColor: colors.text,
            borderWidth: 0,
            padding: [6, 8],
            textStyle: {
                color: document.documentElement.classList.contains('dark') ? '#181818' : '#ffffff',
                fontSize: 11,
            },
            formatter(params) {
                const datum = params.data || {};
                if (! datum.key || datum.key === 'remaining') return '';

                const fileCopy = datum.files > 0
                    ? ` · ${datum.files.toLocaleString()} ${datum.files === 1 ? 'original' : 'originals'}`
                    : '';
                return `${datum.name} · ${datum.displayBytes}${fileCopy}<br>${Number(datum.capacityShare || 0).toFixed(1)}% of allowance`;
            },
        },
        graphic: [
            {
                type: 'text',
                left: 'center',
                top: compact ? '39%' : '39%',
                style: {
                    text: mainValue,
                    fill: colors.text,
                    fontSize: compact ? 15 : 23,
                    fontWeight: 620,
                    fontFamily: 'inherit',
                    textAlign: 'center',
                },
            },
            {
                type: 'text',
                left: 'center',
                top: compact ? '53%' : '52%',
                style: {
                    text: mainLabel,
                    fill: selectedRow ? (colors.storage[selectedRow.key] || colors.accent) : colors.muted,
                    fontSize: compact ? 8 : 10,
                    fontWeight: 650,
                    fontFamily: 'inherit',
                    textAlign: 'center',
                },
            },
        ],
        series: [
            {
                id: 'storage-capacity-context',
                type: 'pie',
                silent: true,
                radius: compact ? ['88%', '93%'] : ['88%', '93%'],
                center: ['50%', '50%'],
                startAngle: 90,
                clockwise: true,
                avoidLabelOverlap: true,
                label: { show: false },
                labelLine: { show: false },
                data: [
                    {
                        value: usedPercent,
                        itemStyle: { color: colors.accent },
                    },
                    {
                        value: Math.max(0, 100 - usedPercent),
                        itemStyle: { color: colors.storage.remaining },
                    },
                ],
                z: 1,
            },
            {
                id: 'storage-capacity-composition',
                type: 'pie',
                selectedMode: compact ? false : 'single',
                selectedOffset: compact ? 0 : 8,
                radius: compact ? ['56%', '82%'] : ['55%', '82%'],
                center: ['50%', '50%'],
                startAngle: 90,
                clockwise: true,
                avoidLabelOverlap: true,
                minAngle: 0.4,
                label: { show: false },
                labelLine: { show: false },
                emphasis: compact ? { disabled: true } : {
                    scale: true,
                    scaleSize: 5,
                },
                data: slices,
                z: 3,
            },
        ],
    };
}

function disposeElement(element) {
    const entry = instances.get(element);
    if (! entry) return;

    if (entry.resizeObserver) entry.resizeObserver.disconnect();
    if (entry.stateHandler) element.removeEventListener('admin-viz:state', entry.stateHandler);
    if (entry.clickHandler) entry.chart.off('click', entry.clickHandler);
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
    const state = { selected: config.selected || null };
    const entry = {
        chart,
        surface,
        config,
        signature,
        theme,
        resizeObserver: null,
        stateHandler: null,
        clickHandler: null,
    };

    chart.setOption(storageOption(element, config, state));

    entry.stateHandler = (event) => {
        state.selected = event.detail?.selected || null;
        chart.setOption(storageOption(element, config, state), { notMerge: true });
    };
    element.addEventListener('admin-viz:state', entry.stateHandler);

    if (element.dataset.adminVizLinked === 'true') {
        entry.clickHandler = (params) => {
            const key = params.data?.key || null;
            if (! key || key === 'remaining') return;

            element.dispatchEvent(new CustomEvent('admin-viz:select', {
                bubbles: true,
                detail: { key },
            }));
        };
        chart.on('click', entry.clickHandler);
    }

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
