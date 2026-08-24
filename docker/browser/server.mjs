import http from 'node:http';
import { chromium } from 'playwright';

const PORT = Number.parseInt(process.env.PORT || '3000', 10);

let browser = null;

async function getBrowser() {
    if (!browser || !browser.isConnected()) {
        browser = await chromium.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-blink-features=AutomationControlled',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--no-first-run',
                '--no-zygote',
            ],
        });
    }
    return browser;
}

async function scrapeUrl(url, timeoutMs = 25000) {
    const currentBrowser = await getBrowser();
    const context = await currentBrowser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        locale: 'fr-FR',
        timezoneId: 'Europe/Paris',
        viewport: { width: 1280, height: 800 },
        deviceScaleFactor: 1,
    });

    // Remove navigator.webdriver stealth bypass
    await context.addInitScript(() => {
        Object.defineProperty(navigator, 'webdriver', {
            get: () => undefined,
        });
    });

    const page = await context.newPage();

    try {
        const response = await page.goto(url, {
            waitUntil: 'domcontentloaded',
            timeout: timeoutMs,
        });

        // Wait a small moment for dynamic content or challenge resolution
        await page.waitForTimeout(1500);

        const html = await page.content();
        const statusCode = response ? response.status() : 200;
        const finalUrl = page.url();

        return {
            status: statusCode,
            finalUrl,
            html,
        };
    } finally {
        await context.close().catch(() => {});
    }
}

const server = http.createServer(async (req, res) => {
    if (req.method === 'GET' && req.url === '/health') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ status: 'ok' }));
        return;
    }

    if (req.method === 'POST' && req.url === '/scrape') {
        let body = '';
        req.on('data', (chunk) => {
            body += chunk;
        });

        req.on('end', async () => {
            try {
                const payload = JSON.parse(body || '{}');
                const url = payload.url;

                if (!url || typeof url !== 'string') {
                    res.writeHead(400, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ error: 'Field "url" is required and must be a string' }));
                    return;
                }

                const timeout = typeof payload.timeout === 'number' ? payload.timeout : 25000;
                const result = await scrapeUrl(url, timeout);

                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify(result));
            } catch (error) {
                const message = error instanceof Error ? error.message : String(error);
                res.writeHead(500, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ error: message }));
            }
        });
        return;
    }

    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: 'Not found' }));
});

server.listen(PORT, '0.0.0.0', () => {
    console.log(`Job Matcher Browser Service listening on port ${PORT}`);
});

process.on('SIGTERM', async () => {
    console.log('Stopping browser service...');
    if (browser) {
        await browser.close().catch(() => {});
    }
    server.close(() => {
        process.exit(0);
    });
});
