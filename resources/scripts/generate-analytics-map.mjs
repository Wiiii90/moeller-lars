import { createHash } from 'node:crypto';
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname } from 'node:path';

const sourceCommit = 'ca96624a56bd078437bca8184e78163e5039ad19';
const sourceBlob = '1e6ab74c7042f97013be69ceec798be8e1aff27d';
const sourceUrl = `https://raw.githubusercontent.com/nvkelso/natural-earth-vector/${sourceCommit}/geojson/ne_110m_admin_0_countries.geojson`;
const output = 'resources/views/filament/generated/analytics-world-map.blade.php';

const response = await fetch(sourceUrl, {
    headers: { 'User-Agent': 'moeller-lars-map-builder/1.0' },
});

if (!response.ok) {
    throw new Error(`Natural Earth download failed with HTTP ${response.status}.`);
}

const bytes = Buffer.from(await response.arrayBuffer());
const gitBlobHash = createHash('sha1')
    .update(`blob ${bytes.length}\0`)
    .update(bytes)
    .digest('hex');

if (gitBlobHash !== sourceBlob) {
    throw new Error(`Natural Earth source integrity mismatch: expected ${sourceBlob}, got ${gitBlobHash}.`);
}

const collection = JSON.parse(bytes.toString('utf8'));
if (collection?.type !== 'FeatureCollection' || !Array.isArray(collection.features)) {
    throw new Error('Natural Earth source is not a GeoJSON FeatureCollection.');
}

const width = 1200;
const height = 600;
const project = ([longitude, latitude]) => [
    ((Number(longitude) + 180) / 360) * width,
    ((90 - Number(latitude)) / 180) * height,
];
const number = (value) => Number(value.toFixed(2));
const escapeAttribute = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('"', '&quot;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');

function ringPath(ring) {
    if (!Array.isArray(ring) || ring.length < 3) {
        return '';
    }

    return ring.map((coordinate, index) => {
        const [x, y] = project(coordinate);
        return `${index === 0 ? 'M' : 'L'}${number(x)} ${number(y)}`;
    }).join(' ') + ' Z';
}

function geometryPath(geometry) {
    if (!geometry || !Array.isArray(geometry.coordinates)) {
        return '';
    }

    if (geometry.type === 'Polygon') {
        return geometry.coordinates.map(ringPath).filter(Boolean).join(' ');
    }

    if (geometry.type === 'MultiPolygon') {
        return geometry.coordinates
            .flatMap((polygon) => polygon.map(ringPath))
            .filter(Boolean)
            .join(' ');
    }

    return '';
}

const paths = collection.features.map((feature) => {
    const path = geometryPath(feature.geometry);
    if (!path) {
        return null;
    }

    const properties = feature.properties ?? {};
    const iso = typeof properties.ISO_A2 === 'string' && /^[A-Z]{2}$/.test(properties.ISO_A2)
        ? properties.ISO_A2.toLowerCase()
        : `ne-${properties.NE_ID ?? 'unknown'}`;
    const name = properties.NAME_LONG ?? properties.ADMIN ?? properties.NAME ?? iso;

    return `    <path id="map-${escapeAttribute(iso)}" class="analytics-world__country" data-country="${escapeAttribute(name)}" d="${path}" />`;
}).filter(Boolean);

const blade = `{{-- Generated at build time from Natural Earth public-domain data.\n     Source commit: ${sourceCommit}\n     Source Git blob: ${sourceBlob}\n     Do not edit this generated file manually. --}}\n<svg class="analytics-world__svg" viewBox="0 0 ${width} ${height}" aria-hidden="true" focusable="false" preserveAspectRatio="xMidYMid meet">\n${paths.join('\n')}\n</svg>\n`;

await mkdir(dirname(output), { recursive: true });
await writeFile(output, blade, 'utf8');
console.log(`Generated ${paths.length} Natural Earth map features at ${output}.`);
