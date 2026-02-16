import { performance } from 'node:perf_hooks';
import http from 'node:http';
import https from 'node:https';

const BASE_URL = process.env.LOADTEST_BASE_URL || 'http://127.0.0.1:8000';
const ADMIN_EMAIL = process.env.LOADTEST_ADMIN_EMAIL || 'loadtest-admin@example.com';
const ADMIN_PASSWORD = process.env.LOADTEST_ADMIN_PASSWORD || 'Passw0rd!';

function getSetCookies(headers) {
    const setCookie = headers['set-cookie'];
    if (Array.isArray(setCookie)) {
        return setCookie;
    }
    return typeof setCookie === 'string' ? [setCookie] : [];
}

function parseCookies(setCookieHeaders) {
    const jar = new Map();
    for (const raw of setCookieHeaders) {
        const [pair] = raw.split(';');
        const idx = pair.indexOf('=');
        if (idx <= 0) continue;
        const key = pair.slice(0, idx).trim();
        const value = pair.slice(idx + 1).trim();
        jar.set(key, value);
    }
    return jar;
}

function mergeCookieMaps(target, source) {
    for (const [k, v] of source.entries()) {
        target.set(k, v);
    }
}

function cookieHeader(cookies) {
    return [...cookies.entries()].map(([k, v]) => `${k}=${v}`).join('; ');
}

function decodeXsrfToken(value) {
    try {
        return decodeURIComponent(value);
    } catch {
        return value;
    }
}

function findSessionCookie(cookies) {
    if (cookies.get('laravel_session')) {
        return 'laravel_session';
    }

    for (const key of cookies.keys()) {
        if (key.toLowerCase().includes('session')) {
            return key;
        }
    }

    return null;
}

function quantile(sorted, q) {
    if (sorted.length === 0) return 0;
    const pos = (sorted.length - 1) * q;
    const base = Math.floor(pos);
    const rest = pos - base;
    if (sorted[base + 1] !== undefined) {
        return sorted[base] + rest * (sorted[base + 1] - sorted[base]);
    }
    return sorted[base];
}

async function loginAndGetCookie() {
    const getRes = await httpRequest({
        method: 'GET',
        url: `${BASE_URL}/login`,
    });
    if (getRes.status < 200 || getRes.status >= 400) {
        throw new Error(`GET /login failed with status ${getRes.status}`);
    }

    const cookies = parseCookies(getSetCookies(getRes.responseHeaders));
    const xsrf = cookies.get('XSRF-TOKEN');
    if (!xsrf) {
        throw new Error('No XSRF-TOKEN cookie from /login');
    }

    const body = new URLSearchParams({
        email: ADMIN_EMAIL,
        password: ADMIN_PASSWORD,
    });

    const postRes = await httpRequest({
        method: 'POST',
        url: `${BASE_URL}/login`,
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-XSRF-TOKEN': decodeXsrfToken(xsrf),
            'Cookie': cookieHeader(cookies),
        },
        body: body.toString(),
    });

    if (![302, 303].includes(postRes.status)) {
        throw new Error(`POST /login failed with status ${postRes.status}`);
    }

    mergeCookieMaps(cookies, parseCookies(getSetCookies(postRes.responseHeaders)));
    const sessionCookieName = findSessionCookie(cookies);
    if (!sessionCookieName) {
        throw new Error(`No session cookie after login. Cookies: ${JSON.stringify(Object.fromEntries(cookies))}`);
    }

    return cookieHeader(cookies);
}

async function runScenario({ name, path, concurrency, requests, cookie, cookies }) {
    const latencies = [];
    const statuses = new Map();
    let ok = 0;
    let fail = 0;
    let pointer = 0;

    async function worker() {
        while (true) {
            const i = pointer++;
            if (i >= requests) return;

            const started = performance.now();
            try {
                const selectedCookie = Array.isArray(cookies) && cookies.length > 0
                    ? cookies[i % cookies.length]
                    : cookie;

                const res = await httpRequest({
                    method: 'GET',
                    url: `${BASE_URL}${path}`,
                    headers: selectedCookie ? { Cookie: selectedCookie } : {},
                });
                const elapsed = performance.now() - started;
                latencies.push(elapsed);
                statuses.set(res.status, (statuses.get(res.status) || 0) + 1);
                if (res.status >= 200 && res.status < 400) {
                    ok += 1;
                } else {
                    fail += 1;
                }
            } catch {
                const elapsed = performance.now() - started;
                latencies.push(elapsed);
                statuses.set('ERR', (statuses.get('ERR') || 0) + 1);
                fail += 1;
            }
        }
    }

    const startedAll = performance.now();
    await Promise.all(Array.from({ length: concurrency }, () => worker()));
    const totalMs = performance.now() - startedAll;

    latencies.sort((a, b) => a - b);
    const avg = latencies.reduce((acc, n) => acc + n, 0) / (latencies.length || 1);
    const rps = (requests / (totalMs / 1000));

    return {
        name,
        path,
        concurrency,
        requests,
        ok,
        fail,
        statusCounts: Object.fromEntries(statuses),
        totalMs,
        rps,
        min: latencies[0] || 0,
        p50: quantile(latencies, 0.5),
        p95: quantile(latencies, 0.95),
        p99: quantile(latencies, 0.99),
        max: latencies[latencies.length - 1] || 0,
        avg,
    };
}

function httpRequest({ method, url, headers = {}, body = '' }) {
    const parsed = new URL(url);
    const lib = parsed.protocol === 'https:' ? https : http;

    return new Promise((resolve, reject) => {
        const req = lib.request({
            protocol: parsed.protocol,
            hostname: parsed.hostname,
            port: parsed.port || (parsed.protocol === 'https:' ? 443 : 80),
            path: `${parsed.pathname}${parsed.search}`,
            method,
            headers: {
                ...headers,
                ...(body ? { 'Content-Length': Buffer.byteLength(body) } : {}),
            },
        }, (res) => {
            const chunks = [];
            res.on('data', (chunk) => chunks.push(chunk));
            res.on('end', () => {
                resolve({
                    status: res.statusCode || 0,
                    body: Buffer.concat(chunks).toString('utf8'),
                    responseHeaders: res.headers,
                });
            });
        });

        req.on('error', reject);
        if (body) {
            req.write(body);
        }
        req.end();
    });
}

function printResult(result) {
    const fmt = (n) => Number.isFinite(n) ? n.toFixed(2) : String(n);
    console.log(`\n=== ${result.name} ===`);
    console.log(`Path: ${result.path}`);
    console.log(`Requests: ${result.requests}, Concurrency: ${result.concurrency}`);
    console.log(`Success: ${result.ok}, Fail: ${result.fail}, Statuses: ${JSON.stringify(result.statusCounts)}`);
    console.log(`Total Time: ${fmt(result.totalMs)} ms, Throughput: ${fmt(result.rps)} req/s`);
    console.log(`Latency(ms): min ${fmt(result.min)} | p50 ${fmt(result.p50)} | p95 ${fmt(result.p95)} | p99 ${fmt(result.p99)} | max ${fmt(result.max)} | avg ${fmt(result.avg)}`);
}

async function main() {
    console.log(`Base URL: ${BASE_URL}`);

    const controlCookies = await Promise.all(Array.from({ length: 6 }, () => loginAndGetCookie()));
    console.log('Authenticated sessions established for /control tests.');

    const timetable = await runScenario({
        name: 'Timetable Burst',
        path: '/timetable',
        concurrency: 100,
        requests: 100,
        cookie: null,
    });
    printResult(timetable);

    const control = await runScenario({
        name: 'Control Burst',
        path: '/control',
        concurrency: 6,
        requests: 60,
        cookies: controlCookies,
    });
    printResult(control);
}

main().catch((error) => {
    console.error(error?.stack || String(error));
    process.exit(1);
});
