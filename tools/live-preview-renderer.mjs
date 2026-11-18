import { createServer } from 'node:http';
import { timingSafeEqual } from 'node:crypto';
import { chromium, firefox, webkit, devices } from 'playwright';

const secret = process.env.LIVE_PREVIEW_RENDERER_TOKEN ?? '';
const origin = new URL(process.env.LIVE_PREVIEW_RENDERER_ORIGIN ?? 'http://preview:8080');
const port = Number(process.env.LIVE_PREVIEW_RENDERER_PORT ?? 3100);

if (secret.length < 16 || !['http:', 'https:'].includes(origin.protocol)) {
    throw new Error('A private renderer origin and a token of at least 16 characters are required.');
}

const engines = { chromium, webkit, firefox };
const instances = new Map();
let inFlight = 0;

function respond(res, status, message) {
    res.writeHead(status, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
    res.end(JSON.stringify({ message }));
}

function authorized(header) {
    if (typeof header !== 'string' || !header.startsWith('Bearer ')) return false;
    const actual = Buffer.from(header.slice(7));
    const expected = Buffer.from(secret);
    return actual.length === expected.length && timingSafeEqual(actual, expected);
}

async function readPayload(req) {
    let body = '';
    for await (const chunk of req) {
        body += chunk.toString();
        if (body.length > 8192) throw new Error('Request exceeds limit.');
    }
    return JSON.parse(body);
}

function viewportFor(input) {
    const device = input.device;
    if (!device || typeof device !== 'object') throw new Error('Device missing.');
    const { width, height, scale, touch, platform } = device;
    if (![width, height].every(Number.isInteger) || width < 300 || width > 2560
        || height < 300 || height > 2560
        || typeof scale !== 'number' || scale < 1 || scale > 4
        || typeof touch !== 'boolean'
        || !['ios', 'android', 'tablet', 'desktop'].includes(platform)
        || !['default', 'rotated'].includes(input.orientation)) {
        throw new Error('Invalid device parameters.');
    }
    return {
        width: input.orientation === 'rotated' ? height : width,
        height: input.orientation === 'rotated' ? width : height,
        scale, touch, platform,
    };
}

async function browserFor(engine) {
    if (!instances.has(engine)) {
        const launch = engines[engine].launch({ headless: true })
            .catch((error) => { instances.delete(engine); throw error; });
        instances.set(engine, launch);
    }
    return instances.get(engine);
}

function addSessionCookies(context, raw) {
    if (!raw) return Promise.resolve();
    const cookies = raw.split(';').map((part) => {
        const index = part.indexOf('=');
        if (index < 1) return null;
        const name = part.slice(0, index).trim();
        const value = part.slice(index + 1).trim();
        if (!/^[!#$%&'*+\-.^_\`|~0-9A-Za-z]+$/.test(name)) return null;
        return { name, value, url: origin.origin };
    }).filter(Boolean);
    return cookies.length ? context.addCookies(cookies) : Promise.resolve();
}

async function render(payload, cookieHeader) {
    if (!Object.hasOwn(engines, payload.engine)) throw new Error('Unknown engine.');
    if (typeof payload.path !== 'string'
        || !/^\/preview(?:\/|$)/.test(payload.path)
        || payload.path.includes('..') || payload.path.includes('//')
        || /[%?#\\]/.test(payload.path)) throw new Error('Invalid protected preview path.');

    const viewport = viewportFor(payload);
    const browser = await browserFor(payload.engine);
    const phone = viewport.platform === 'ios' || viewport.platform === 'android';
    const mobile = viewport.touch && payload.engine !== 'firefox';
    const deviceReference = viewport.platform === 'ios'
        ? devices['iPhone 13']
        : viewport.platform === 'android' ? devices['Pixel 7'] : null;
    const userAgent = deviceReference && (
        (payload.engine === 'webkit' && viewport.platform === 'ios')
        || (payload.engine === 'chromium' && viewport.platform === 'android')
    ) ? deviceReference.userAgent : undefined;

    const context = await browser.newContext({
        viewport: { width: viewport.width, height: viewport.height },
        screen: { width: viewport.width, height: viewport.height },
        deviceScaleFactor: viewport.scale,
        hasTouch: viewport.touch,
        isMobile: mobile,
        ...(userAgent ? { userAgent } : {}),
        serviceWorkers: 'block',
    });

    try {
        await addSessionCookies(context, cookieHeader);
        // This worker may visit the application preview only. It must not
        // become a browser-accessible server-side request proxy.
        await context.route('**/*', async (route) => {
            const requestUrl = new URL(route.request().url());
            if (requestUrl.origin !== origin.origin) {
                await route.abort('blockedbyclient');
                return;
            }
            await route.continue();
        });
        const page = await context.newPage();
        const target = new URL(payload.path, origin);
        const navigation = await page.goto(target.href, { waitUntil: 'domcontentloaded', timeout: 12000 });
        if (!navigation || navigation.status() >= 400
            || !new URL(page.url()).pathname.startsWith('/preview')) {
            throw new Error('The protected preview was not accessible.');
        }
        await page.waitForFunction(() => {
            const menu = document.querySelector('[data-site-navigation]');
            return !menu || menu.hasAttribute('data-navigation-ready');
        }, null, { timeout: 3500 }).catch(() => {});
        await page.evaluate(() => Promise.race([
            document.fonts?.ready ?? Promise.resolve(),
            new Promise((resolve) => setTimeout(resolve, 2500)),
        ]));
        await page.waitForFunction(() => [...document.images].every((img) => img.complete), null, {
            timeout: 3500,
        }).catch(() => {});

        // Phone browsers normally overlay their scrollbars. Only the renderer
        // presentation changes, never the public application's layout rules.
        if (phone) {
            await page.addStyleTag({ content: 'html, body { scrollbar-width: none; } html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }' }).catch(() => {});
        }

        const overflow = await page.evaluate(() =>
            Math.max(document.documentElement.scrollWidth, document.body.scrollWidth)
                > document.documentElement.clientWidth + 1,
        );
        const png = await page.screenshot({
            type: 'png', scale: 'css', animations: 'disabled',
            timeout: 10000,
        });
        return { png, overflow };
    } finally {
        await context.close();
    }
}

createServer(async (req, res) => {
    if (req.method !== 'POST' || req.url !== '/render') {
        respond(res, 404, 'Not found.');
        return;
    }
    if (!authorized(req.headers.authorization)) {
        respond(res, 401, 'Unauthorized.');
        return;
    }
    if (inFlight >= 2) {
        respond(res, 429, 'Renderer is busy.');
        return;
    }
    inFlight++;
    try {
        const payload = await readPayload(req);
        const result = await render(payload, req.headers.cookie ?? '');
        res.writeHead(200, {
            'Content-Type': 'image/png',
            'Cache-Control': 'private, no-store',
            'X-Preview-Overflow-X': result.overflow ? '1' : '0',
            'X-Content-Type-Options': 'nosniff',
        });
        res.end(result.png);
    } catch (error) {
        // Never expose browser cookies, internal URLs or stack details.
        console.error('Preview render failed:', error?.name ?? 'UnknownError');
        respond(res, 422, 'Unable to render protected preview.');
    } finally {
        inFlight--;
    }
}).listen(port, '0.0.0.0', () => {
    console.log('Private preview renderer is listening.');
});

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, async () => {
        await Promise.allSettled([...instances.values()].map(async (browser) => (await browser).close()));
        process.exit(0);
    });
}
