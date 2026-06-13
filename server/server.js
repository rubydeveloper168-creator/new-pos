const express = require('express');
const cors = require('cors');
const http = require('http');
const https = require('https');
const fs = require('fs');
const path = require('path');
const cron = require('node-cron');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3000;

// Environment-based configuration
const nodeEnv = (process.env.NODE_ENV || 'development').toLowerCase();
const isProduction = nodeEnv === 'production';
const API_BASE_URL = process.env.API_BASE_URL || 'https://api-shop.rubyshop.co.th';

// Database configuration for production
const DB_CONFIG = {
    host: process.env.DB_HOST || 'api-shop.rubyshop.co.th',
    port: process.env.DB_PORT || 3306,
    user: process.env.DB_USER ,
    password: process.env.DB_PASSWORD ,
    database: process.env.DB_NAME ,
};

// Middleware
app.use(cors({
    origin: isProduction ? [
        'https://api-shop.rubyshop.co.th',
        'https://shop.rubyshop.co.th',
         'https://sale.rubyshop.co.th'
    ] : '*',
    methods: '*',
    allowedHeaders: '*'
}));

app.use(express.json());

// Make configuration available to routes
app.locals.apiBaseUrl = API_BASE_URL;
app.locals.dbConfig = DB_CONFIG;
app.locals.isProduction = isProduction;

// Routes
const pdfRoutes = require('./routes/pdfRoutes');
app.use('/', pdfRoutes);

// ─── Cron: Full Sync every minute (24/7) ───────────────────────────────────────
const PROD_NEW_POS_PUBLIC_BASE_INPUT = '/var/www/shop.rubyshop.co.th/public/';
const PROD_OLD_POS_PUBLIC_BASE_INPUT = '/var/www/sale.rubyshop.co.th/public';
const LEGACY_PROD_NEW_POS_PATH = '/var/www/shop.rubyshop.co.th/public';
const LEGACY_PROD_OLD_POS_PATH = '/var/www/sale.rubyshop.co.th/public';

function normalizeBaseUrl(url) {
    return (url || '').replace(/\/+$/, '');
}

function resolveUrlInput(url) {
    const normalized = normalizeBaseUrl(url);
    if (!normalized) {
        return normalized;
    }

    if (/^https?:\/\//i.test(normalized)) {
        return normalized;
    }

    if (normalized.startsWith(LEGACY_PROD_NEW_POS_PATH)) {
        return normalizeBaseUrl(normalized.replace(LEGACY_PROD_NEW_POS_PATH, 'https://shop.rubyshop.co.th'));
    }

    if (normalized.startsWith(LEGACY_PROD_OLD_POS_PATH)) {
        return normalizeBaseUrl(normalized.replace(LEGACY_PROD_OLD_POS_PATH, 'https://sale.rubyshop.co.th'));
    }

    return normalized;
}

function buildPublicPathFallback(baseUrl) {
    if (!baseUrl || !/^https?:\/\//i.test(baseUrl)) {
        return null;
    }

    const normalized = normalizeBaseUrl(baseUrl);

    if (normalized.endsWith('/public')) {
        const withoutPublic = normalizeBaseUrl(normalized.slice(0, -'/public'.length));
        return withoutPublic && withoutPublic !== normalized ? withoutPublic : null;
    }

    return normalizeBaseUrl(`${normalized}/public`);
}

const DEFAULT_LARAVEL_BASE_INPUT = PROD_NEW_POS_PUBLIC_BASE_INPUT;
const LARAVEL_BASE_URL = resolveUrlInput(process.env.LARAVEL_BASE_URL || DEFAULT_LARAVEL_BASE_INPUT);
const FALLBACK_LARAVEL_BASE_URL = buildPublicPathFallback(LARAVEL_BASE_URL);
const DEFAULT_OLD_POS_EXPORT_INPUT = PROD_OLD_POS_PUBLIC_BASE_INPUT;
let OLD_POS_EXPORT_DATA_URL = resolveUrlInput(process.env.OLD_POS_EXPORT_DATA_URL || DEFAULT_OLD_POS_EXPORT_INPUT);
if (OLD_POS_EXPORT_DATA_URL && !OLD_POS_EXPORT_DATA_URL.includes('/admin/welcome/export_data')) {
    OLD_POS_EXPORT_DATA_URL = normalizeBaseUrl(`${OLD_POS_EXPORT_DATA_URL}/admin/welcome/export_data`);
}
app.locals.laravelBaseUrl = LARAVEL_BASE_URL;
app.locals.oldPosExportDataUrl = OLD_POS_EXPORT_DATA_URL;
const SYNC_CRON_TOKEN = process.env.SYNC_CRON_TOKEN || '';
const SYNC_NEW_PATH = '/migrate-update-data/cron-sync-run';
const SYNC_PRODUCT_PATH = '/migrate-update-data/cron-sync-products';
const SYNC_PAY_PATH = '/migrate-update-data/cron-sync-payment-updates';
const parsedSyncTimeoutMs = Number(process.env.SYNC_TIMEOUT_MS || 180000);
const SYNC_TIMEOUT_MS = Number.isFinite(parsedSyncTimeoutMs) && parsedSyncTimeoutMs >= 10000
    ? parsedSyncTimeoutMs
    : 180000;
const RUN_EXTRA_SYNC_ENDPOINTS = String(process.env.RUN_EXTRA_SYNC_ENDPOINTS || 'false').toLowerCase() === 'true';
let isSyncCycleRunning = false;
let syncCycleCounter = 0;

const PROD_IMAGE_SYNC_JOBS = [
    {
        source: '/var/www/sale.rubyshop.co.th/assets/uploads',
        destination: '/var/www/shop.rubyshop.co.th/public/uploads/img',
    },
    {
        source: '/var/www/sale.rubyshop.co.th/files/',
        destination: '/var/www/shop.rubyshop.co.th/public/uploads/documents/',
    },
];

function resolveImageSyncJobs() {
    const jobs = [
        {
            source: process.env.IMAGE_SYNC_SOURCE || PROD_IMAGE_SYNC_JOBS[0].source,
            destination: process.env.IMAGE_SYNC_DESTINATION || PROD_IMAGE_SYNC_JOBS[0].destination,
        },
        {
            source:
                process.env.IMAGE_SYNC_SOURCE_2
                || process.env.SOURCES_2
                || PROD_IMAGE_SYNC_JOBS[1].source,
            destination:
                process.env.IMAGE_SYNC_DESTINATION_2
                || process.env.DESTINATION_2
                || PROD_IMAGE_SYNC_JOBS[1].destination,
        },
    ];

    return jobs
        .map((job) => ({
            source: String(job.source || '').trim(),
            destination: String(job.destination || '').trim(),
        }))
        .filter((job) => job.source && job.destination);
}

const IMAGE_SYNC_JOBS = resolveImageSyncJobs();

let isImageSyncRunning = false;
let imageSyncCycleCounter = 0;

function logCron(level, event, payload = {}) {
    const logMethod = typeof console[level] === 'function' ? console[level] : console.log;
    logMethod(
        JSON.stringify({
            scope: 'cron-sync',
            event,
            ts: new Date().toISOString(),
            ...payload,
        })
    );
}

function logImageSync(level, event, payload = {}) {
    const logMethod = typeof console[level] === 'function' ? console[level] : console.log;
    logMethod(
        JSON.stringify({
            scope: 'cron-image-sync',
            event,
            ts: new Date().toISOString(),
            ...payload,
        })
    );
}

async function copyChangedFilesRecursively(sourceDir, destinationDir, summary) {
    await fs.promises.mkdir(destinationDir, { recursive: true });
    const entries = await fs.promises.readdir(sourceDir, { withFileTypes: true });

    for (const entry of entries) {
        const sourcePath = path.join(sourceDir, entry.name);
        const destinationPath = path.join(destinationDir, entry.name);

        if (entry.isDirectory()) {
            await copyChangedFilesRecursively(sourcePath, destinationPath, summary);
            continue;
        }

        if (!entry.isFile()) {
            summary.skippedSpecial += 1;
            continue;
        }

        summary.scanned += 1;
        const sourceStat = await fs.promises.stat(sourcePath);

        let shouldCopy = true;
        try {
            const destinationStat = await fs.promises.stat(destinationPath);
            if (
                destinationStat.isFile()
                && destinationStat.size === sourceStat.size
                && Math.floor(destinationStat.mtimeMs) === Math.floor(sourceStat.mtimeMs)
            ) {
                shouldCopy = false;
            }
        } catch (error) {
            if (error.code !== 'ENOENT') {
                throw error;
            }
        }

        if (!shouldCopy) {
            summary.unchanged += 1;
            continue;
        }

        await fs.promises.copyFile(sourcePath, destinationPath);
        // Preserve modified timestamp so future cycles can skip unchanged files cheaply.
        await fs.promises.utimes(destinationPath, sourceStat.atime, sourceStat.mtime);
        summary.copied += 1;
    }
}

async function runImageSyncCycle(cycleId) {
    const summary = {
        scanned: 0,
        copied: 0,
        unchanged: 0,
        skippedSpecial: 0,
        jobs: [],
    };

    logImageSync('log', 'cycle.paths', {
        cycleId,
        jobs: IMAGE_SYNC_JOBS,
        environment: nodeEnv,
    });

    for (const job of IMAGE_SYNC_JOBS) {
        const sourceDir = job.source;
        const destinationDir = job.destination;
        const sourceStat = await fs.promises.stat(sourceDir).catch((error) => {
            if (error.code === 'ENOENT') {
                return null;
            }
            throw error;
        });

        if (!sourceStat || !sourceStat.isDirectory()) {
            throw new Error(`Image sync source directory not found: ${sourceDir}`);
        }

        const sourceSummary = {
            source: sourceDir,
            destination: destinationDir,
            scanned: 0,
            copied: 0,
            unchanged: 0,
            skippedSpecial: 0,
        };

        await copyChangedFilesRecursively(sourceDir, destinationDir, sourceSummary);

        summary.scanned += sourceSummary.scanned;
        summary.copied += sourceSummary.copied;
        summary.unchanged += sourceSummary.unchanged;
        summary.skippedSpecial += sourceSummary.skippedSpecial;
        summary.jobs.push(sourceSummary);
    }

    return summary;
}

function buildSyncUrl(baseUrl, path) {
    const urlObj = new URL(`${baseUrl}${path}`);
    if (SYNC_CRON_TOKEN) {
        urlObj.searchParams.set('token', SYNC_CRON_TOKEN);
    }
    return urlObj.toString();
}

function triggerSyncEndpoint(
    label,
    url,
    timeoutMs = 55000,
    fallbackUrl = null,
    context = {}
) {
    const client = url.startsWith('https') ? https : http;
    const startedAt = Date.now();
    const cycleId = context.cycleId || 'n/a';
    const fallbackReason = context.fallbackReason || null;

    return new Promise((resolve) => {
        logCron('log', 'endpoint.start', {
            cycleId,
            label,
            url,
            hasFallback: !!fallbackUrl,
            fallbackReason,
        });

        let settled = false;
        let timeoutHandle = null;
        let sseBuffer = '';
        const finalize = (result) => {
            if (settled) {
                return;
            }
            settled = true;
            if (timeoutHandle) {
                clearTimeout(timeoutHandle);
            }
            resolve({
                label,
                url,
                durationMs: Date.now() - startedAt,
                ...result,
            });
        };

        const attemptFallback = (reason, detail = null) => {
            if (!fallbackUrl) {
                return false;
            }

            logCron('warn', 'endpoint.fallback', {
                cycleId,
                label,
                reason,
                fromUrl: url,
                toUrl: fallbackUrl,
                detail,
            });

            triggerSyncEndpoint(label, fallbackUrl, timeoutMs, null, {
                cycleId,
                fallbackReason: reason,
            }).then(finalize);

            return true;
        };

        const parseSseLine = (line) => {
            if (!line) {
                return;
            }
            const trimmed = line.trim();
            if (!trimmed || trimmed.startsWith(':')) {
                return;
            }

            if (!trimmed.startsWith('data:')) {
                return;
            }

            const data = trimmed.replace(/^data:\s*/, '');
            if (!data || data === '[DONE]') {
                return;
            }

            try {
                const payload = JSON.parse(data);
                if (payload && payload.message) {
                    logCron('log', 'endpoint.sse', {
                        cycleId,
                        label,
                        level: payload.type || 'info',
                        message: payload.message,
                    });
                }
            } catch (_) {
                // Keep compatible with SSE responses that emit plain text chunks.
                logCron('log', 'endpoint.sse.raw', {
                    cycleId,
                    label,
                    message: data,
                });
            }
        };

        const req = client.get(url, (res) => {
            logCron('log', 'endpoint.response', {
                cycleId,
                label,
                url,
                statusCode: res.statusCode,
            });

            const statusCode = res.statusCode || 0;
            if (statusCode >= 400) {
                res.resume();
                if (attemptFallback(`http_${statusCode}`)) {
                    return;
                }
                finalize({
                    ok: false,
                    statusCode,
                    error: `HTTP ${statusCode} from sync endpoint`,
                });
                return;
            }

            res.setEncoding('utf8');
            res.on('data', (chunk) => {
                sseBuffer += chunk;
                let lineBreakIndex = sseBuffer.indexOf('\n');
                while (lineBreakIndex !== -1) {
                    const line = sseBuffer.slice(0, lineBreakIndex).replace(/\r$/, '');
                    sseBuffer = sseBuffer.slice(lineBreakIndex + 1);
                    parseSseLine(line);
                    lineBreakIndex = sseBuffer.indexOf('\n');
                }
            });

            res.on('end', () => {
                if (sseBuffer.trim()) {
                    parseSseLine(sseBuffer.replace(/\r$/, ''));
                }
                finalize({
                    ok: statusCode >= 200 && statusCode < 400,
                    statusCode,
                });
            });
        });

        req.on('error', (err) => {
            if (attemptFallback('request_error', err.message)) {
                return;
            }
            finalize({
                ok: false,
                statusCode: 0,
                error: err.message,
            });
        });

        timeoutHandle = setTimeout(() => {
            req.destroy(new Error(`timeout after ${timeoutMs}ms`));
            logCron('warn', 'endpoint.timeout', {
                cycleId,
                label,
                url,
                timeoutMs,
            });
        }, timeoutMs);
    });
}

// Run every minute (24/7)
cron.schedule('* * * * *', async () => {
    if (isSyncCycleRunning) {
        logCron('warn', 'cycle.skip_locked', {
            reason: 'previous cycle still running',
        });
        return;
    }

    isSyncCycleRunning = true;
    syncCycleCounter += 1;
    const cycleId = `cycle-${syncCycleCounter}`;
    const cycleStartedAt = Date.now();

    logCron('log', 'cycle.start', { cycleId });

    try {
        const results = [];

        const syncNewResult = await triggerSyncEndpoint(
            'SyncNew',
            buildSyncUrl(LARAVEL_BASE_URL, SYNC_NEW_PATH),
            SYNC_TIMEOUT_MS,
            FALLBACK_LARAVEL_BASE_URL ? buildSyncUrl(FALLBACK_LARAVEL_BASE_URL, SYNC_NEW_PATH) : null,
            { cycleId }
        );
        results.push(syncNewResult);

        if (RUN_EXTRA_SYNC_ENDPOINTS) {
            const syncProductResult = await triggerSyncEndpoint(
                'SyncProduct',
                buildSyncUrl(LARAVEL_BASE_URL, SYNC_PRODUCT_PATH),
                SYNC_TIMEOUT_MS,
                FALLBACK_LARAVEL_BASE_URL ? buildSyncUrl(FALLBACK_LARAVEL_BASE_URL, SYNC_PRODUCT_PATH) : null,
                { cycleId }
            );
            results.push(syncProductResult);

            const syncPayResult = await triggerSyncEndpoint(
                'SyncPay',
                buildSyncUrl(LARAVEL_BASE_URL, SYNC_PAY_PATH),
                SYNC_TIMEOUT_MS,
                FALLBACK_LARAVEL_BASE_URL ? buildSyncUrl(FALLBACK_LARAVEL_BASE_URL, SYNC_PAY_PATH) : null,
                { cycleId }
            );
            results.push(syncPayResult);
        }

        logCron('log', 'cycle.end', {
            cycleId,
            durationMs: Date.now() - cycleStartedAt,
            results,
        });
    } catch (err) {
        logCron('error', 'cycle.error', {
            cycleId,
            durationMs: Date.now() - cycleStartedAt,
            error: err.message,
        });
    } finally {
        isSyncCycleRunning = false;
    }
});

console.log('[Cron] Full sync scheduled — runs every minute (24/7)');
console.log(`[Cron] Primary Laravel sync base URL: ${LARAVEL_BASE_URL}`);
console.log(`[Config] Old POS export-data URL: ${OLD_POS_EXPORT_DATA_URL}`);
console.log(`[Cron] Sync endpoint timeout: ${SYNC_TIMEOUT_MS}ms`);
console.log(`[Cron] Extra product/payment endpoints: ${RUN_EXTRA_SYNC_ENDPOINTS ? 'enabled' : 'disabled'}`);
if (!SYNC_CRON_TOKEN) {
    console.warn('[Cron] SYNC_CRON_TOKEN is empty. Cron sync endpoints will be rejected by Laravel.');
}
if (FALLBACK_LARAVEL_BASE_URL) {
    console.log(`[Cron] Fallback Laravel sync base URL: ${FALLBACK_LARAVEL_BASE_URL}`);
}
if (!/^https?:\/\//i.test(LARAVEL_BASE_URL)) {
    console.warn('[Cron] LARAVEL_BASE_URL is not an HTTP URL. Please set LARAVEL_BASE_URL in server/.env.');
}
if (!/^https?:\/\//i.test(OLD_POS_EXPORT_DATA_URL)) {
    console.warn('[Config] OLD_POS_EXPORT_DATA_URL is not an HTTP URL. Please set OLD_POS_EXPORT_DATA_URL in server/.env.');
}

// Run image sync every minute
cron.schedule('* * * * *', async () => {
    if (isImageSyncRunning) {
        logImageSync('warn', 'cycle.skip_locked', {
            reason: 'previous image sync cycle still running',
        });
        return;
    }

    isImageSyncRunning = true;
    imageSyncCycleCounter += 1;
    const cycleId = `image-cycle-${imageSyncCycleCounter}`;
    const cycleStartedAt = Date.now();

    logImageSync('log', 'cycle.start', { cycleId });

    try {
        const summary = await runImageSyncCycle(cycleId);
        logImageSync('log', 'cycle.end', {
            cycleId,
            durationMs: Date.now() - cycleStartedAt,
            ...summary,
        });
    } catch (error) {
        logImageSync('error', 'cycle.error', {
            cycleId,
            durationMs: Date.now() - cycleStartedAt,
            error: error.message,
        });
    } finally {
        isImageSyncRunning = false;
    }
});

console.log('[Cron] Image sync scheduled — runs every minute (24/7)');
IMAGE_SYNC_JOBS.forEach((job, index) => {
    console.log(`[Cron] Image sync #${index + 1}: ${job.source} -> ${job.destination}`);
});

// Start server
app.listen(PORT, () => {
    console.log(`PDF Generator Server running on port ${PORT}`);
    console.log(`Environment: ${process.env.NODE_ENV || 'development'}`);
    console.log(`API Base URL: ${API_BASE_URL}`);
    console.log(`Database: ${DB_CONFIG.host}:${DB_CONFIG.port}/${DB_CONFIG.database}`);
    console.log('Health check: GET /health');
    console.log('Generate Quotation PDF: POST /generate-quotation-pdf/:id');
    console.log(
        'Generate Tax Invoice PDF: POST /generate-tax-invoice-pdf/:id'
    );
    console.log(
        'Generate Billing Receipt PDF: POST /generate-billing-receipt-pdf/:id'
    );
});

module.exports = app;
