const express = require('express');
const router = express.Router();
const PDFController = require('../controllers/pdfController');
const axios = require('axios');
const cheerio = require('cheerio');
const fs = require('fs');
const crypto = require('crypto');
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const ProxyAgent = require('proxy-agent');

puppeteer.use(StealthPlugin());

const DATAFORTHAI_USER_AGENT =
  "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36";

const COMPANY_LOOKUP_LOGS =
  (process.env.COMPANY_LOOKUP_LOGS || 'true').toLowerCase() !== 'false';
const COMPANY_FETCH_RETRIES = Number.parseInt(
  process.env.COMPANY_FETCH_RETRIES || '1',
  10
);
const COMPANY_FETCH_BACKOFF_BASE_MS = Number.parseInt(
  process.env.COMPANY_FETCH_BACKOFF_BASE_MS || '400',
  10
);
const COMPANY_FETCH_BACKOFF_MAX_MS = Number.parseInt(
  process.env.COMPANY_FETCH_BACKOFF_MAX_MS || '3000',
  10
);
const COMPANY_BROWSER_LAUNCH_TIMEOUT_MS = Number.parseInt(
  process.env.COMPANY_BROWSER_LAUNCH_TIMEOUT_MS || '20000',
  10
);
const COMPANY_BROWSER_NAV_TIMEOUT_MS = Number.parseInt(
  process.env.COMPANY_BROWSER_NAV_TIMEOUT_MS || '20000',
  10
);
const COMPANY_BROWSER_TOTAL_TIMEOUT_MS = Number.parseInt(
  process.env.COMPANY_BROWSER_TOTAL_TIMEOUT_MS || '35000',
  10
);
const COMPANY_BROWSER_RESPONSE_TIMEOUT_MS = Number.parseInt(
  process.env.COMPANY_BROWSER_RESPONSE_TIMEOUT_MS || '8000',
  10
);
const COMPANY_BROWSER_HEADLESS = process.env.COMPANY_BROWSER_HEADLESS || 'new';
const COMPANY_BROWSER_SINGLE_PROCESS =
  (process.env.COMPANY_BROWSER_SINGLE_PROCESS || 'false').toLowerCase() === 'true';

const COMPANY_CACHE_TTL_MS = Number.parseInt(
  process.env.COMPANY_CACHE_TTL_MS || '21600000',
  10
);
const COMPANY_CACHE_MAX_ITEMS = Number.parseInt(
  process.env.COMPANY_CACHE_MAX_ITEMS || '1000',
  10
);
const COMPANY_CACHE_FILE = process.env.COMPANY_CACHE_FILE || '';
const companyCache = new Map();
let cacheLoaded = false;
let cacheFlushTimer = null;

let proxyIndex = 0;
const PROXY_FAIL_LIMIT = Number.parseInt(
  process.env.PROXY_FAIL_LIMIT || '2',
  10
);
const PROXY_COOLDOWN_MS = Number.parseInt(
  process.env.PROXY_COOLDOWN_MS || '300000',
  10
);
const proxyHealth = new Map();

function createRequestId() {
  if (crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

function formatLogValue(value) {
  if (value === null || value === undefined) {
    return String(value);
  }
  if (typeof value === 'string') {
    return value.includes(' ') ? JSON.stringify(value) : value;
  }
  if (typeof value === 'number' || typeof value === 'boolean') {
    return String(value);
  }
  return JSON.stringify(value);
}

function createCompanyLogger(requestId) {
  return (level, message, meta = {}) => {
    if (!COMPANY_LOOKUP_LOGS) {
      return;
    }
    const metaText = Object.entries(meta)
      .map(([key, value]) => `${key}=${formatLogValue(value)}`)
      .join(' ');
    const line = `[company-lookup][${requestId}] ${message}${
      metaText ? ` ${metaText}` : ''
    }`;
    if (level === 'error') {
      console.error(line);
    } else if (level === 'warn') {
      console.warn(line);
    } else {
      console.log(line);
    }
  };
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function withTimeout(promise, ms, label = 'operation') {
  let timer = null;
  const timeout = new Promise((_, reject) => {
    timer = setTimeout(() => {
      const error = new Error(`${label} timed out after ${ms}ms`);
      error.code = 'ETIMEDOUT';
      reject(error);
    }, ms);
  });

  return Promise.race([promise, timeout]).finally(() => {
    if (timer) {
      clearTimeout(timer);
    }
  });
}

function computeBackoffMs(attempt) {
  const base = COMPANY_FETCH_BACKOFF_BASE_MS * Math.pow(2, attempt - 1);
  const jitter = base * 0.2 * Math.random();
  return Math.min(base + jitter, COMPANY_FETCH_BACKOFF_MAX_MS);
}

function resolveHeadlessMode() {
  const raw = COMPANY_BROWSER_HEADLESS;
  if (raw === 'true') {
    return true;
  }
  if (raw === 'false') {
    return false;
  }
  return raw || 'new';
}

function getProxyPool() {
  const raw = process.env.PROXY_URLS || process.env.PROXY_URL || '';
  return raw
    .split(',')
    .map((entry) => entry.trim())
    .filter(Boolean);
}

function hasProxyPool() {
  return getProxyPool().length > 0;
}

function isProxyCooling(proxyUrl) {
  const entry = proxyHealth.get(proxyUrl);
  if (!entry || !entry.cooldownUntil) {
    return false;
  }
  return entry.cooldownUntil > Date.now();
}

function markProxySuccess(proxyUrl) {
  if (!proxyUrl) {
    return;
  }
  proxyHealth.set(proxyUrl, {
    failCount: 0,
    cooldownUntil: 0,
    lastError: null,
    lastErrorAt: null,
  });
}

function markProxyFailure(proxyUrl, reason) {
  if (!proxyUrl) {
    return;
  }
  const entry = proxyHealth.get(proxyUrl) || {
    failCount: 0,
    cooldownUntil: 0,
    lastError: null,
    lastErrorAt: null,
  };
  entry.failCount += 1;
  entry.lastError = reason || null;
  entry.lastErrorAt = Date.now();
  if (entry.failCount >= PROXY_FAIL_LIMIT) {
    entry.cooldownUntil = Date.now() + PROXY_COOLDOWN_MS;
    entry.failCount = 0;
  }
  proxyHealth.set(proxyUrl, entry);
}

function getNextProxyUrl(log) {
  const pool = getProxyPool();
  if (!pool.length) {
    return null;
  }

  const strategy = (process.env.PROXY_STRATEGY || 'round_robin').toLowerCase();
  if (strategy === 'random') {
    const available = pool.filter((proxy) => !isProxyCooling(proxy));
    if (!available.length) {
      if (log) {
        log('warn', 'all proxies are cooling down');
      }
      return null;
    }
    return available[Math.floor(Math.random() * available.length)];
  }

  for (let i = 0; i < pool.length; i += 1) {
    const proxyUrl = pool[proxyIndex % pool.length];
    proxyIndex = (proxyIndex + 1) % pool.length;
    if (!isProxyCooling(proxyUrl)) {
      return proxyUrl;
    }
  }

  if (log) {
    log('warn', 'all proxies are cooling down');
  }
  return null;
}

function maskProxyUrl(proxyUrl) {
  if (!proxyUrl) {
    return null;
  }
  try {
    const parsed = new URL(proxyUrl);
    if (parsed.username || parsed.password) {
      parsed.username = '***';
      parsed.password = '***';
    }
    return parsed.toString();
  } catch {
    return '***';
  }
}

function getCachedCompany(taxId) {
  if (!cacheLoaded) {
    loadCompanyCache();
  }
  if (!COMPANY_CACHE_TTL_MS) {
    return null;
  }
  const cached = companyCache.get(taxId);
  if (!cached) {
    return null;
  }
  if (Date.now() - cached.timestamp > COMPANY_CACHE_TTL_MS) {
    companyCache.delete(taxId);
    return null;
  }
  return cached.value;
}

function setCachedCompany(taxId, value) {
  if (!cacheLoaded) {
    loadCompanyCache();
  }
  if (!COMPANY_CACHE_TTL_MS || !value) {
    return;
  }
  companyCache.set(taxId, { value, timestamp: Date.now() });
  scheduleCompanyCacheFlush();
}

function loadCompanyCache() {
  cacheLoaded = true;
  if (!COMPANY_CACHE_FILE) {
    return;
  }
  try {
    if (!fs.existsSync(COMPANY_CACHE_FILE)) {
      return;
    }
    const raw = fs.readFileSync(COMPANY_CACHE_FILE, 'utf8');
    if (!raw) {
      return;
    }
    const entries = JSON.parse(raw);
    if (!Array.isArray(entries)) {
      return;
    }
    entries.forEach((entry) => {
      if (entry && entry.taxId && entry.value && entry.timestamp) {
        companyCache.set(entry.taxId, {
          value: entry.value,
          timestamp: entry.timestamp,
        });
      }
    });
  } catch (error) {
    console.warn('⚠️ Failed to load company cache file:', error.message);
  }
}

function flushCompanyCache() {
  if (!COMPANY_CACHE_FILE) {
    return;
  }
  try {
    const entries = Array.from(companyCache.entries()).map(
      ([taxId, entry]) => ({
        taxId,
        value: entry.value,
        timestamp: entry.timestamp,
      })
    );
    entries.sort((a, b) => b.timestamp - a.timestamp);
    const trimmed = entries.slice(0, COMPANY_CACHE_MAX_ITEMS);
    fs.writeFile(COMPANY_CACHE_FILE, JSON.stringify(trimmed), (error) => {
      if (error) {
        console.warn('⚠️ Failed to write company cache file:', error.message);
      }
    });
  } catch (error) {
    console.warn('⚠️ Failed to write company cache file:', error.message);
  }
}

function scheduleCompanyCacheFlush() {
  if (!COMPANY_CACHE_FILE) {
    return;
  }
  if (cacheFlushTimer) {
    return;
  }
  cacheFlushTimer = setTimeout(() => {
    cacheFlushTimer = null;
    flushCompanyCache();
  }, 1000);
  if (typeof cacheFlushTimer.unref === 'function') {
    cacheFlushTimer.unref();
  }
}

function resolveChromePath() {
  if (process.env.CHROME_BIN && fs.existsSync(process.env.CHROME_BIN)) {
    return process.env.CHROME_BIN;
  }

  const candidates = [];

  if (process.platform === 'darwin') {
    candidates.push(
      '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
      '/Applications/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing'
    );
  } else if (process.platform === 'win32') {
    candidates.push(
      'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
      'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe'
    );
  } else {
    candidates.push(
      '/usr/bin/google-chrome',
      '/usr/bin/chromium-browser',
      '/usr/bin/chromium'
    );
  }

  return candidates.find((candidate) => fs.existsSync(candidate)) || null;
}

function parseCompanyInfo(html) {
  if (!html) {
    return null;
  }

  const $ = cheerio.load(html);

  // ชื่อไทย (h2 ภายใน #maindata)
  const companyNameTh = $("#maindata h2").first().text().trim();

  // ชื่ออังกฤษ (h3.noselect ภายใน #maindata)
  const companyNameEn = $("#maindata h3.noselect").first().text().trim();

  // Tax ID
  const taxRow = $("td")
    .filter((i, el) => $(el).text().trim() === "เลขทะเบียน")
    .closest("tr");
  const taxNumber = taxRow.find("td").eq(1).text().trim();

  // Business type
  const businessRow = $("td")
    .filter((i, el) => $(el).text().trim() === "ประเภทธุรกิจ")
    .closest("tr");
  const businessType = businessRow.find("td").eq(1).text().trim();

  // Address (Google Maps link ที่ class="noselect")
  const address = $("a.noselect").first().text().trim();

  return { companyNameTh, companyNameEn, taxNumber, businessType, address };
}

function detectBlockPage(html) {
  if (!html) {
    return null;
  }
  const lower = html.toLowerCase();
  if (
    lower.includes('cloudflare') ||
    lower.includes('captcha') ||
    lower.includes('access denied') ||
    lower.includes('attention required') ||
    lower.includes('ddos')
  ) {
    return 'block';
  }
  return null;
}

function isRetryableStatus(status) {
  return [408, 425, 429, 500, 502, 503, 504].includes(status);
}

function isRetryableNetworkError(error) {
  if (!error || error.response) {
    return false;
  }
  return [
    'ECONNRESET',
    'ETIMEDOUT',
    'EAI_AGAIN',
    'ENOTFOUND',
    'ECONNREFUSED',
    'EPIPE',
  ].includes(error.code);
}

function shouldCooldownProxy(status, error) {
  if (status) {
    return status === 403 || status === 429 || status >= 500;
  }
  return Boolean(error && error.code);
}

async function attemptFetchHtml({ label, fetcher, proxyUrl, log }) {
  let lastError = null;
  let lastStatus = null;
  for (let attempt = 1; attempt <= COMPANY_FETCH_RETRIES + 1; attempt += 1) {
    const startedAt = Date.now();
    try {
      log('info', `${label} start`, {
        attempt,
        proxy: maskProxyUrl(proxyUrl),
      });
      const totalTimeoutMs =
        label && label.startsWith('browser')
          ? COMPANY_BROWSER_TOTAL_TIMEOUT_MS
          : COMPANY_FETCH_BACKOFF_MAX_MS + 10000;
      const response = await withTimeout(
        fetcher(),
        totalTimeoutMs,
        `${label} total`
      );
      const durationMs = Date.now() - startedAt;
      const bytes = response?.data
        ? Buffer.byteLength(response.data, 'utf8')
        : 0;
      log('info', `${label} success`, {
        attempt,
        status: response?.status ?? null,
        durationMs,
        bytes,
        proxy: maskProxyUrl(proxyUrl),
      });
      return {
        html: response?.data || null,
        status: response?.status ?? null,
        error: null,
      };
    } catch (error) {
      const durationMs = Date.now() - startedAt;
      const status = error.response?.status ?? error.status ?? null;
      const retryable = isRetryableStatus(status) || isRetryableNetworkError(error);
      log('warn', `${label} error`, {
        attempt,
        status,
        durationMs,
        retryable,
        message: error.message,
        proxy: maskProxyUrl(proxyUrl),
      });
      lastError = error;
      lastStatus = status;
      if (attempt <= COMPANY_FETCH_RETRIES && retryable) {
        const delayMs = computeBackoffMs(attempt);
        log('info', `${label} retrying`, { attempt, delayMs });
        await sleep(delayMs);
        continue;
      }
      break;
    }
  }
  return { html: null, status: lastStatus, error: lastError };
}

async function fetchCompanyHtmlWithAxios(url, proxyUrl = null) {
  const agent = proxyUrl ? new ProxyAgent(proxyUrl) : undefined;
  const response = await axios.get(url, {
    headers: {
      "User-Agent": DATAFORTHAI_USER_AGENT,
      "Accept":
        "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
      "Accept-Language": "th-TH,th;q=0.9,en-US;q=0.8,en;q=0.7",
      "Cache-Control": "no-cache",
      Pragma: "no-cache",
    },
    timeout: 10000, // 10 second timeout
    httpAgent: agent,
    httpsAgent: agent,
    proxy: false,
  });

  return {
    data: response.data,
    status: response.status,
  };
}

async function fetchCompanyHtmlWithBrowser(url, proxyUrl = null, log = null) {
  let browser;
  try {
    const executablePath = resolveChromePath();
    if (log) {
      log('info', 'browser launch start', {
        proxy: maskProxyUrl(proxyUrl),
        executablePath: executablePath || 'bundled',
        headless: resolveHeadlessMode(),
      });
    }
    const args = [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-accelerated-2d-canvas',
      '--no-first-run',
      '--no-zygote',
      '--disable-gpu',
      '--disable-web-security',
      '--disable-features=VizDisplayCompositor',
    ];
    if (COMPANY_BROWSER_SINGLE_PROCESS) {
      args.push('--single-process');
    }

    let proxyAuth = null;
    if (proxyUrl) {
      const parsed = new URL(proxyUrl);
      const proxyServer = `${parsed.protocol}//${parsed.hostname}${
        parsed.port ? `:${parsed.port}` : ''
      }`;
      args.push(`--proxy-server=${proxyServer}`);

      if (parsed.username || parsed.password) {
        proxyAuth = {
          username: decodeURIComponent(parsed.username || ''),
          password: decodeURIComponent(parsed.password || ''),
        };
      }
    }

    browser = await withTimeout(
      puppeteer.launch({
        headless: resolveHeadlessMode(),
        args,
        executablePath: executablePath || undefined,
      }),
      COMPANY_BROWSER_LAUNCH_TIMEOUT_MS,
      'browser launch'
    );
    if (log) {
      log('info', 'browser launch success', {
        proxy: maskProxyUrl(proxyUrl),
      });
    }
    browser.on('disconnected', () => {
      if (log) {
        log('warn', 'browser disconnected');
      }
    });

    const page = await browser.newPage();
    page.setDefaultTimeout(COMPANY_BROWSER_NAV_TIMEOUT_MS);
    page.setDefaultNavigationTimeout(COMPANY_BROWSER_NAV_TIMEOUT_MS);
    page.on('close', () => {
      if (log) {
        log('warn', 'browser page closed');
      }
    });
    page.on('error', (error) => {
      if (log) {
        log('warn', 'browser page error', { message: error.message });
      }
    });
    page.on('pageerror', (error) => {
      if (log) {
        log('warn', 'browser pageerror', { message: error.message });
      }
    });
    await page.setUserAgent(DATAFORTHAI_USER_AGENT);
    await page.setExtraHTTPHeaders({
      "Accept-Language": "th-TH,th;q=0.9,en-US;q=0.8,en;q=0.7",
    });
    if (proxyAuth) {
      await page.authenticate(proxyAuth);
    }

    if (log) {
      log('info', 'browser goto start', { url });
    }
    const response = await withTimeout(
      page.goto(url, {
        waitUntil: 'domcontentloaded',
        timeout: COMPANY_BROWSER_NAV_TIMEOUT_MS,
      }),
      COMPANY_BROWSER_NAV_TIMEOUT_MS + 2000,
      'browser navigation'
    );
    if (log) {
      log('info', 'browser goto success', {
        status: response ? response.status() : null,
      });
    }

    let responseHtml = null;
    if (response) {
      try {
        if (log) {
          log('info', 'browser response text start');
        }
        responseHtml = await withTimeout(
          response.text(),
          COMPANY_BROWSER_RESPONSE_TIMEOUT_MS,
          'browser response text'
        );
        if (log) {
          log('info', 'browser response text success', {
            bytes: responseHtml ? responseHtml.length : 0,
          });
        }
      } catch (error) {
        if (log) {
          log('warn', 'browser response text failed', { message: error.message });
        }
      }
    }

    if (responseHtml) {
      return {
        data: responseHtml,
        status: response ? response.status() : null,
      };
    }

    if (log) {
      log('info', 'browser waitForSelector start', { selector: '#maindata' });
    }
    await withTimeout(
      page.waitForSelector('#maindata', { timeout: 10000 }).catch(() => null),
      12000,
      'browser waitForSelector'
    );
    if (log) {
      log('info', 'browser waitForSelector done');
    }

    let html = null;
    try {
      if (log) {
        log('info', 'browser extract start');
      }
      if (page.isClosed()) {
        throw new Error('page closed before extract');
      }
      html = await withTimeout(
        page.evaluate(() => {
          const main = document.querySelector('#maindata');
          return main ? main.outerHTML : document.documentElement.outerHTML;
        }),
        5000,
        'browser extract'
      );
      if (log) {
        log('info', 'browser extract success', {
          bytes: html ? html.length : 0,
        });
      }
    } catch (error) {
      if (log) {
        log('warn', 'browser extract failed', { message: error.message });
      }
    }

    if (!html) {
      if (log) {
        log('info', 'browser content fallback start');
      }
      if (page.isClosed()) {
        throw new Error('page closed before content fallback');
      }
      html = await withTimeout(
        page.content(),
        8000,
        'browser content'
      );
      if (log) {
        log('info', 'browser content fallback success', {
          bytes: html ? html.length : 0,
        });
      }
    }

    return {
      data: html,
      status: response ? response.status() : null,
    };
  } finally {
    if (browser) {
      await browser.close();
    }
  }
}

// Function: ดึงข้อมูลบริษัทจาก dataforthai.com
async function getCompanyInfo(taxId, context = {}) {
  const log = context.log || (() => {});
  const cached = getCachedCompany(taxId);
  if (cached) {
    log('info', 'cache hit', { taxId });
    return cached;
  }

  log('info', 'cache miss', { taxId });
  const url = `https://www.dataforthai.com/company/${taxId}/`;

  const directAxios = await attemptFetchHtml({
    label: 'axios direct',
    fetcher: () => fetchCompanyHtmlWithAxios(url),
    proxyUrl: null,
    log,
  });

  let info = parseCompanyInfo(directAxios.html);
  if (info && info.companyNameTh) {
    log('info', 'parse success', { source: 'axios direct' });
    setCachedCompany(taxId, info);
    return info;
  }

  const directBlock = detectBlockPage(directAxios.html);
  log('warn', 'parse miss', {
    source: 'axios direct',
    block: directBlock,
    status: directAxios.status ?? null,
  });

  if (hasProxyPool()) {
    const proxyUrl = getNextProxyUrl(log);
    if (proxyUrl) {
      const proxyAxios = await attemptFetchHtml({
        label: 'axios proxy',
        fetcher: () => fetchCompanyHtmlWithAxios(url, proxyUrl),
        proxyUrl,
        log,
      });

      if (proxyAxios.error && shouldCooldownProxy(proxyAxios.status, proxyAxios.error)) {
        markProxyFailure(
          proxyUrl,
          proxyAxios.status || proxyAxios.error.code || 'error'
        );
        log('warn', 'proxy marked failed', {
          proxy: maskProxyUrl(proxyUrl),
          status: proxyAxios.status ?? null,
        });
      }

      info = parseCompanyInfo(proxyAxios.html);
      if (info && info.companyNameTh) {
        log('info', 'parse success', { source: 'axios proxy' });
        markProxySuccess(proxyUrl);
        setCachedCompany(taxId, info);
        return info;
      }

      const proxyBlock = detectBlockPage(proxyAxios.html);
      if (proxyBlock) {
        markProxyFailure(proxyUrl, proxyBlock);
      }
      log('warn', 'parse miss', {
        source: 'axios proxy',
        block: proxyBlock,
        status: proxyAxios.status ?? null,
        proxy: maskProxyUrl(proxyUrl),
      });
    }
  }

  const browserDirect = await attemptFetchHtml({
    label: 'browser direct',
    fetcher: () => fetchCompanyHtmlWithBrowser(url, null, log),
    proxyUrl: null,
    log,
  });
  info = parseCompanyInfo(browserDirect.html);
  if (info && info.companyNameTh) {
    log('info', 'parse success', { source: 'browser direct' });
    setCachedCompany(taxId, info);
    return info;
  }

  const browserBlock = detectBlockPage(browserDirect.html);
  log('warn', 'parse miss', {
    source: 'browser direct',
    block: browserBlock,
    status: browserDirect.status ?? null,
  });

  if (hasProxyPool()) {
    const proxyUrl = getNextProxyUrl(log);
    if (proxyUrl) {
      const browserProxy = await attemptFetchHtml({
        label: 'browser proxy',
        fetcher: () => fetchCompanyHtmlWithBrowser(url, proxyUrl, log),
        proxyUrl,
        log,
      });

      if (browserProxy.error && shouldCooldownProxy(browserProxy.status, browserProxy.error)) {
        markProxyFailure(
          proxyUrl,
          browserProxy.status || browserProxy.error.code || 'error'
        );
        log('warn', 'proxy marked failed', {
          proxy: maskProxyUrl(proxyUrl),
          status: browserProxy.status ?? null,
        });
      }

      info = parseCompanyInfo(browserProxy.html);
      if (info && info.companyNameTh) {
        log('info', 'parse success', { source: 'browser proxy' });
        markProxySuccess(proxyUrl);
        setCachedCompany(taxId, info);
        return info;
      }

      const browserProxyBlock = detectBlockPage(browserProxy.html);
      if (browserProxyBlock) {
        markProxyFailure(proxyUrl, browserProxyBlock);
      } else if (shouldCooldownProxy(browserProxy.status, null)) {
        markProxyFailure(proxyUrl, browserProxy.status);
      }
      log('warn', 'parse miss', {
        source: 'browser proxy',
        block: browserProxyBlock,
        status: browserProxy.status ?? null,
        proxy: maskProxyUrl(proxyUrl),
      });
    }
  }

  return null;
}

// Health check endpoint (must come before catch-all)
router.get('/health', PDFController.healthCheck);

// Company lookup endpoint
router.get('/company/:taxId', async (req, res) => {
  const requestId = createRequestId();
  const log = createCompanyLogger(requestId);
  const requestStart = Date.now();
  log('info', 'request start', {
    taxId: req.params.taxId,
    ip: req.ip,
    userAgent: req.headers['user-agent'] || '',
  });

  try {
    const { taxId } = req.params;
    
    // Validate tax ID format (should be 13 digits)
    if (!/^\d{13}$/.test(taxId)) {
      log('warn', 'invalid tax id', { taxId });
      return res.status(400).json({ 
        error: "Invalid tax ID format. Tax ID must be 13 digits." 
      });
    }
    
    log('info', 'lookup start', { taxId });
    const info = await getCompanyInfo(taxId, { log });

    if (!info || !info.companyNameTh) {
      log('warn', 'company not found', { taxId });
      return res.status(404).json({ 
        error: "Company not found",
        taxId: taxId
      });
    }

    log('info', 'company found', {
      companyNameTh: info.companyNameTh,
      taxId,
    });
    res.json(info);
  } catch (error) {
    log('error', 'lookup error', { message: error.message });
    res.status(500).json({ 
      error: "Internal server error while fetching company data",
      details: error.message
    });
  } finally {
    log('info', 'request end', { durationMs: Date.now() - requestStart });
  }
});

// System diagnostics endpoint
router.get('/diagnostics', PDFController.diagnostics);

// Database test endpoint
router.get('/test-db', PDFController.testDatabase);

// List sample invoice numbers from database
router.get('/list-invoices', async (req, res) => {
    try {
        const DatabaseService = require('../services/databaseService');
        const mysql = require('mysql2/promise');
        
        // Use dynamic database configuration
        const dbConfig = DatabaseService.getDbConfig(req);
        
        const connection = await mysql.createConnection(dbConfig);
        
        const [rows] = await connection.execute(
            'SELECT id, invoice_no, type, status, payment_status, created_at FROM transactions ORDER BY created_at DESC LIMIT 20'
        );
        
        await connection.end();
        
        res.json({
            total: rows.length,
            sample_invoices: rows
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Test endpoint to return HTML for debugging
router.get('/test-html/:id', PDFController.testHTML);

// Debug endpoint to see generated HTML
router.post('/debug-html/:id', PDFController.debugHTML);

// Main PDF generation endpoints - supporting both GET and POST
router.get('/public/quotations/:id/pdf-print-nodejs', PDFController.generateQuotationPDF);
router.post('/generate-quotation-pdf/:id', PDFController.generateQuotationPDF);

router.get('/tax-invoice/:id/pdf-print-nodejs', PDFController.generateTaxInvoicePDF);
router.post('/generate-tax-invoice-pdf/:id', PDFController.generateTaxInvoicePDF);

router.get('/billing-receipt/:id/pdf-print-nodejs', PDFController.generateBillingReceiptPDF);
router.post('/generate-billing-receipt-pdf/:id', PDFController.generateBillingReceiptPDF);

// Catch-all route to handle invoice numbers directly (must come last)
router.get('/:invoiceNumber', PDFController.generatePDFByInvoiceNumber);

module.exports = router;
