import * as echarts from 'echarts/core';
import { GaugeChart, LineChart } from 'echarts/charts';
import { GraphicComponent, PolarComponent, TooltipComponent } from 'echarts/components';
import { SVGRenderer } from 'echarts/renderers';

echarts.use([
    GaugeChart,
    LineChart,
    GraphicComponent,
    PolarComponent,
    TooltipComponent,
    SVGRenderer,
]);

const instances = new Map();
let refreshFrame = null;

function readConfig(element) {
    const node = element.querySelector(':scope > script[data-admin-viz-config]');
    if (! node) return null;

    try {
        return JSON.parse(node.textContent || '{}');
    } catch (error) {
        console.error('Invalid admin visualization config.', error);
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
        faint: token('--admin-faint', '#9a9a9a'),
        line: token('--admin-line', '#dedede'),
        lineStrong: token('--admin-line-strong', '#bdbdbd'),
        surface: token('--admin-surface', '#ffffff'),
        subtle: token('--admin-subtle', '#f7f7f6'),
        accent: token('--admin-accent', '#b45309'),
        accentStrong: token('--admin-accent-strong', '#7c2d12'),
        danger: token('--admin-danger', '#b42318'),
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

function storageAxisColors(config, colors, selected) {
    const usedRatio = clamp(config.percent, 0, 100) / 100;
    const rows = Array.isArray(config.breakdown) ? config.breakdown : [];

    if (usedRatio <= 0) {
        return [[1, colors.storage.remaining]];
    }

    if (rows.length === 0) {
        return [
            [usedRatio, colors.accent],
            [1, colors.storage.remaining],
        ];
    }

    const thresholds = [];
    let cursor = 0;

    rows.forEach((row) => {
        const shareOfUsed = clamp(row.percent, 0, 100) / 100;
        const shareOfCapacity = usedRatio * shareOfUsed;
        if (shareOfCapacity <= 0) return;

        cursor = Math.min(usedRatio, cursor + shareOfCapacity);
        const base = colors.storage[row.key] || colors.accent;
        const color = selected && row.key !== selected ? withAlpha(base, 0.22) : base;
        thresholds.push([cursor, color]);
    });

    if (cursor < usedRatio - 0.00001) {
        thresholds.push([usedRatio, selected ? withAlpha(colors.accent, 0.22) : colors.accent]);
    }

    thresholds.push([1, colors.storage.remaining]);

    return thresholds;
}

function storageOption(element, config, state) {
    const colors = palette(element);
    const percent = clamp(config.percent, 0, 100);
    const rows = Array.isArray(config.breakdown) ? config.breakdown : [];
    const selectedRow = rows.find((row) => row.key === state.selected) || null;
    const mainValue = selectedRow?.display_bytes || config.allowance || '—';
    const mainLabel = selectedRow?.label || `${config.authoritative || '—'} used`;
    const trackShadow = withAlpha(colors.text, document.documentElement.classList.contains('dark') ? 0.18 : 0.07);

    if (! config.configured || ! config.measurement_available || config.percent === null) {
        return {
            animation: false,
            graphic: [
                {
                    type: 'text',
                    left: 'center',
                    top: '45%',
                    style: {
                        text: config.measurement_available ? (config.authoritative || '—') : '—',
                        fill: colors.text,
                        fontSize: 22,
                        fontWeight: 600,
                        fontFamily: 'inherit',
                        textAlign: 'center',
                    },
                },
                {
                    type: 'text',
                    left: 'center',
                    top: '56%',
                    style: {
                        text: config.measurement_available ? 'No allowance' : 'No measurement',
                        fill: colors.muted,
                        fontSize: 10,
                        fontWeight: 600,
                        fontFamily: 'inherit',
                        textAlign: 'center',
                    },
                },
            ],
            series: [],
        };
    }

    return {
        animationDuration: 620,
        animationDurationUpdate: 360,
        animationEasing: 'cubicOut',
        animationEasingUpdate: 'cubicOut',
        tooltip: { show: false },
        graphic: [
            {
                type: 'text',
                left: 'center',
                top: '41%',
                style: {
                    text: mainValue,
                    fill: colors.text,
                    fontSize: 23,
                    fontWeight: 620,
                    fontFamily: 'inherit',
                    textAlign: 'center',
                },
            },
            {
                type: 'text',
                left: 'center',
                top: '53%',
                style: {
                    text: mainLabel,
                    fill: selectedRow ? (colors.storage[selectedRow.key] || colors.accent) : colors.muted,
                    fontSize: 10,
                    fontWeight: 650,
                    fontFamily: 'inherit',
                    textAlign: 'center',
                },
            },
        ],
        series: [
            {
                id: 'storage-depth',
                type: 'gauge',
                silent: true,
                startAngle: 220,
                endAngle: -40,
                min: 0,
                max: 100,
                center: ['50%', '55%'],
                radius: '86%',
                axisLine: {
                    lineStyle: {
                        width: 25,
                        color: [[1, trackShadow]],
                        shadowBlur: 7,
                        shadowColor: trackShadow,
                        shadowOffsetY: 3,
                    },
                },
                pointer: { show: false },
                progress: { show: false },
                axisTick: { show: false },
                splitLine: { show: false },
                axisLabel: { show: false },
                anchor: { show: false },
                detail: { show: false },
                title: { show: false },
                data: [{ value: percent }],
                z: 1,
            },
            {
                id: 'storage-capacity',
                type: 'gauge',
                silent: true,
                startAngle: 220,
                endAngle: -40,
                min: 0,
                max: 100,
                splitNumber: 4,
                center: ['50%', '53%'],
                radius: '86%',
                axisLine: {
                    roundCap: true,
                    lineStyle: {
                        width: 20,
                        color: storageAxisColors(config, colors, state.selected),
                    },
                },
                pointer: { show: false },
                progress: { show: false },
                axisTick: {
                    show: true,
                    splitNumber: 4,
                    distance: -29,
                    length: 3,
                    lineStyle: {
                        color: withAlpha(colors.faint, 0.5),
                        width: 1,
                    },
                },
                splitLine: {
                    show: true,
                    distance: -32,
                    length: 7,
                    lineStyle: {
                        color: colors.lineStrong,
                        width: 1,
                    },
                },
                axisLabel: { show: false },
                anchor: { show: false },
                detail: { show: false },
                title: { show: false },
                data: [{ value: percent }],
                z: 3,
            },
        ],
    };
}

const clockPointerIcon = 'path://M2.9,0.7c1.4,0,2.6,1.2,2.6,2.6v115c0,1.4-1.2,2.6-2.6,2.6c-1.4,0-2.6-1.2-2.6-2.6V3.3C0.3,1.9,1.4,0.7,2.9,0.7z';

function activityOption(element, config) {
    const colors = palette(element);
    const buckets = Array.isArray(config.buckets) ? config.buckets : [];
    const peak = Math.max(1, ...buckets.map((bucket) => Number(bucket.count) || 0));
    const hours = buckets.map((bucket) => String(bucket.hour).padStart(2, '0'));
    const activity = buckets.map((bucket) => {
        const count = Number(bucket.count) || 0;
        const normalized = count > 0 ? Math.sqrt(count / peak) : 0;

        return {
            value: normalized,
            count,
            hour: Number(bucket.hour) || 0,
        };
    });
    const now = new Date();
    const second = now.getSeconds();
    const minute = now.getMinutes() + (second / 60);
    const hour = (now.getHours() % 12) + (minute / 60);

    const handSeries = ({ id, max, value, length, width, color, z, showAnchor = false }) => ({
        id,
        name: id,
        type: 'gauge',
        startAngle: 90,
        endAngle: -270,
        min: 0,
        max,
        center: ['50%', '50%'],
        radius: '68%',
        axisLine: { show: false },
        axisTick: { show: false },
        splitLine: { show: false },
        axisLabel: { show: false },
        progress: { show: false },
        pointer: {
            show: true,
            icon: clockPointerIcon,
            length,
            width,
            offsetCenter: [0, '7%'],
            itemStyle: { color },
        },
        anchor: {
            show: showAnchor,
            showAbove: true,
            size: showAnchor ? 9 : 0,
            itemStyle: {
                color: document.documentElement.classList.contains('dark') ? '#18181b' : '#ffffff',
                borderColor: colors.text,
                borderWidth: 2,
            },
        },
        detail: { show: false },
        title: { show: false },
        data: [{ value }],
        z,
    });

    return {
        animationDuration: 720,
        animationDurationUpdate: 280,
        animationEasing: 'cubicOut',
        animationEasingUpdate: 'cubicOut',
        tooltip: {
            trigger: 'item',
            backgroundColor: colors.text,
            borderWidth: 0,
            padding: [6, 8],
            textStyle: {
                color: document.documentElement.classList.contains('dark') ? '#181818' : '#ffffff',
                fontSize: 11,
            },
            formatter(params) {
                if (params.seriesId !== 'activity-halo') return '';
                const datum = params.data || {};
                return `${String(datum.hour).padStart(2, '0')}:00 · ${Number(datum.count || 0).toLocaleString()} changes`;
            },
        },
        polar: {
            center: ['50%', '50%'],
            radius: ['74%', '96%'],
        },
        angleAxis: {
            type: 'category',
            data: hours,
            startAngle: 90,
            clockwise: true,
            boundaryGap: false,
            show: false,
        },
        radiusAxis: {
            min: 0,
            max: 1,
            show: false,
        },
        series: [
            {
                id: 'activity-halo',
                name: 'Activity',
                type: 'line',
                coordinateSystem: 'polar',
                data: activity,
                smooth: 0.38,
                symbol: 'circle',
                symbolSize: 5,
                showSymbol: false,
                connectNulls: true,
                silent: false,
                lineStyle: {
                    color: colors.accentStrong,
                    width: 1.45,
                    opacity: 0.9,
                },
                areaStyle: {
                    color: withAlpha(colors.accent, document.documentElement.classList.contains('dark') ? 0.2 : 0.12),
                },
                itemStyle: {
                    color: colors.accentStrong,
                },
                emphasis: {
                    scale: true,
                    lineStyle: {
                        width: 2.2,
                    },
                    itemStyle: {
                        color: colors.accentStrong,
                    },
                },
                z: 1,
            },
            {
                id: 'clock-face',
                name: 'Clock face',
                type: 'gauge',
                silent: true,
                startAngle: 90,
                endAngle: -270,
                min: 0,
                max: 12,
                splitNumber: 12,
                center: ['50%', '50%'],
                radius: '68%',
                axisLine: {
                    lineStyle: {
                        width: 1,
                        color: [[1, colors.lineStrong]],
                    },
                },
                axisTick: {
                    show: true,
                    splitNumber: 4,
                    distance: -7,
                    length: 3,
                    lineStyle: {
                        color: colors.lineStrong,
                        width: 1,
                    },
                },
                splitLine: {
                    show: true,
                    distance: -9,
                    length: 8,
                    lineStyle: {
                        color: colors.text,
                        width: 1.25,
                    },
                },
                axisLabel: {
                    show: true,
                    distance: 14,
                    color: colors.text,
                    fontSize: 10,
                    fontWeight: 620,
                    formatter(value) {
                        if (value === 0) return '12';
                        if (value === 3 || value === 6 || value === 9) return String(value);
                        return '';
                    },
                },
                pointer: { show: false },
                progress: { show: false },
                anchor: { show: false },
                detail: { show: false },
                title: { show: false },
                data: [{ value: hour }],
                z: 3,
            },
            handSeries({ id: 'clock-hour', max: 12, value: hour, length: '44%', width: 5.5, color: colors.text, z: 7 }),
            handSeries({ id: 'clock-minute', max: 60, value: minute, length: '60%', width: 3.1, color: colors.text, z: 8 }),
            handSeries({ id: 'clock-second', max: 60, value: second, length: '64%', width: 1.25, color: colors.accent, z: 9, showAnchor: true }),
        ],
    };
}

function updateClock(entry) {
    const now = new Date();
    const second = now.getSeconds();
    const minute = now.getMinutes() + (second / 60);
    const hour = (now.getHours() % 12) + (minute / 60);

    entry.chart.setOption({
        series: [
            { id: 'clock-hour', data: [{ value: hour }] },
            { id: 'clock-minute', data: [{ value: minute }] },
            { id: 'clock-second', data: [{ value: second }] },
        ],
    });
}

function disposeElement(element) {
    const entry = instances.get(element);
    if (! entry) return;

    if (entry.timer) window.clearInterval(entry.timer);
    if (entry.resizeObserver) entry.resizeObserver.disconnect();
    if (entry.stateHandler) element.removeEventListener('admin-viz:state', entry.stateHandler);
    if (! entry.chart.isDisposed()) entry.chart.dispose();
    instances.delete(element);
}

function mountElement(element) {
    const config = readConfig(element);
    if (! config) return;

    const surface = chartSurface(element);
    const signature = JSON.stringify(config);
    const existing = instances.get(element);
    const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';

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
        timer: null,
        resizeObserver: null,
        stateHandler: null,
    };

    if (config.kind === 'storage-capacity') {
        chart.setOption(storageOption(element, config, state));
        entry.stateHandler = (event) => {
            state.selected = event.detail?.selected || null;
            chart.setOption(storageOption(element, config, state));
        };
        element.addEventListener('admin-viz:state', entry.stateHandler);
    } else if (config.kind === 'activity-clock') {
        chart.setOption(activityOption(element, config));
        entry.timer = window.setInterval(() => updateClock(entry), 1000);
    } else {
        chart.dispose();
        return;
    }

    entry.resizeObserver = new ResizeObserver(() => chart.resize());
    entry.resizeObserver.observe(surface);
    instances.set(element, entry);
}

function refreshVisualizations() {
    refreshFrame = null;

    for (const element of instances.keys()) {
        if (! element.isConnected) disposeElement(element);
    }

    document.querySelectorAll('[data-admin-viz]').forEach(mountElement);
}

function scheduleRefresh() {
    if (refreshFrame !== null) return;
    refreshFrame = window.requestAnimationFrame(refreshVisualizations);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleRefresh, { once: true });
} else {
    scheduleRefresh();
}

document.addEventListener('livewire:navigated', scheduleRefresh);

new MutationObserver(scheduleRefresh).observe(document.body, {
    childList: true,
    subtree: true,
});

new MutationObserver(() => {
    const elements = Array.from(instances.keys());

    elements.forEach((element) => {
        const entry = instances.get(element);
        if (! entry) return;

        entry.theme = '';
        mountElement(element);
    });
}).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
});
