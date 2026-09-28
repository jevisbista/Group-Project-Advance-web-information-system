/* Additional real-browser, JavaScript-disabled member/committee walkthrough. */
const { chromium } = require('../storage/browser-tools/node_modules/playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
const base = process.env.CLUBCONNECT_TEST_URL || 'http://127.0.0.1:8001';
const preview = process.env.CLUBCONNECT_PREVIEW_URL || 'http://127.0.0.1:8000';
let checks = 0;
function check(value, text) { assert.ok(value, text); checks++; console.log('PASS: ' + text); }
async function capture(page, label) {
    for (const width of [375, 768, 1200]) {
        await page.setViewportSize({ width, height: 900 });
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), label + ' ' + width + 'px no overflow');
        await page.screenshot({ path: path.join(__dirname, '../storage/browser-results', label + '-' + width + '.png'), fullPage: true });
    }
}
async function clickNavigate(page, locator) {
    assert.equal(await locator.count(), 1, 'Expected one navigation control');
    await Promise.all([page.waitForNavigation(), locator.click()]);
}
async function login(page, email) {
    await page.goto(base + '/dashboard.php');
    await page.getByLabel('Email address', { exact: true }).fill(email);
    await page.getByLabel('Password', { exact: true }).fill('ClubConnectDemo!2026');
    await clickNavigate(page, page.getByRole('button', { name: 'Log in', exact: true }));
    check(await page.getByRole('heading', { name: 'Keep your details up to date' }).isVisible(), email + ' signs in with JavaScript disabled');
}
(async () => {
    for (const [name, executablePath] of [['chrome', 'C:/Program Files/Google/Chrome/Application/chrome.exe'], ['edge', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe']]) {
        const browser = await chromium.launch({ executablePath, headless: true });
        console.log('VERSION: ' + name + ' ' + browser.version());
        const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 375, height: 900 } });
        const page = await context.newPage();
        await login(page, 'alex.member@example.test');
        await capture(page, name + '-member-dashboard');
        await page.getByLabel('Phone (optional)').fill('+61 400 000 001');
        await clickNavigate(page, page.getByRole('button', { name: 'Save my details' }));
        check(await page.getByText('Your profile has been updated.').isVisible(), name + ' no-JS profile save succeeds');
        await page.goto(base + '/events.php?q=Creative%20skills');
        await clickNavigate(page, page.getByRole('link', { name: 'RSVP to this event' }));
        await capture(page, name + '-rsvp');
        await clickNavigate(page, page.getByRole('button', { name: /Confirm my RSVP|Rejoin this event/ }));
        check(await page.getByText('Already registered', { exact: true }).isVisible(), name + ' no-JS RSVP succeeds');
        await page.goto(base + '/dashboard.php');
        const registration = page.locator('.registration-item').filter({ hasText: 'Demo: Creative skills workshop' });
        await clickNavigate(page, registration.getByRole('link', { name: 'Cancel registration' }));
        await clickNavigate(page, page.getByRole('button', { name: 'Yes, cancel my place' }));
        check(await page.getByText('Your registration has been cancelled.', { exact: false }).isVisible(), name + ' no-JS confirmed cancellation succeeds');
        await clickNavigate(page, page.getByRole('button', { name: 'Log out', exact: true }));
        await login(page, 'sam.committee@example.test');
        await page.goto(base + '/events.php?add=1');
        await capture(page, name + '-committee-editor');
        await page.getByLabel('Event name', { exact: true }).fill('No-JS workshop ' + name + '-' + Date.now());
        await page.getByLabel('Description', { exact: true }).fill('Fictional browser acceptance workshop.');
        await page.getByLabel('Location', { exact: true }).fill('Acceptance room');
        await page.getByLabel('Capacity', { exact: true }).fill('3');
        await clickNavigate(page, page.getByRole('button', { name: 'Create event', exact: true }));
        check(await page.getByText('Your event has been created.').isVisible(), name + ' no-JS committee event creation succeeds');
        await page.goto(base + '/events.php?view=past&q=Welcome%20and%20ideas');
        await clickNavigate(page, page.getByRole('link', { name: 'Manage participants' }));
        await capture(page, name + '-participants');
        check(await page.locator('#participants table').count() === 1, name + ' committee participant table is available');
        await browser.close();
    }
    const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
    const page = await browser.newPage();
    for (const width of [375, 768, 1200]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(preview + '/index.php');
        // Load all lazy images before an exported full-page screenshot.
        await page.locator('img').evaluateAll(images => images.forEach(img => { img.loading = 'eager'; }));
        await page.waitForFunction(() => [...document.images].every(img => img.complete && img.naturalWidth > 0));
        await page.screenshot({ path: path.join(__dirname, '../storage/browser-results', 'chrome-home-' + width + '.png'), fullPage: true });
    }
    await browser.close();
    console.log('Completed ' + checks + ' protected browser checks.');
})().catch(error => { console.error(error); process.exit(1); });
