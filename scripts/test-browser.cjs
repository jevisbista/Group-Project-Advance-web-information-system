/* Browser checks use local Chrome/Edge and the optional Playwright test dependency.
   npm.cmd install --prefix storage/browser-tools --no-audit --no-fund playwright
   Run against an isolated test server; see docs/testing.md. */
const { chromium, request } = require('../storage/browser-tools/node_modules/playwright');
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const assert = require('node:assert/strict');
const base = process.env.CLUBCONNECT_TEST_URL || 'http://127.0.0.1:8001';
const preview = process.env.CLUBCONNECT_PREVIEW_URL || 'http://127.0.0.1:8000';
const out = path.resolve(__dirname, '../storage/browser-results');
fs.mkdirSync(out, { recursive: true });
let count = 0;
function check(condition, message) { assert.ok(condition, message); count++; console.log('PASS: ' + message); }
function token(html) {
    const match = html.match(/name="csrf_token" value="([a-f0-9]+)"/);
    assert.ok(match, 'CSRF token present'); return match[1];
}
async function form(client, endpoint, values, csrf = true) {
    const html = await (await client.get(base + '/' + endpoint)).text();
    return client.post(base + '/' + endpoint, { form: { ...(csrf ? { csrf_token: token(html) } : {}), ...values } });
}
async function login(client, email) {
    const response = await form(client, 'dashboard.php', { action: 'login', email, password: 'ClubConnectDemo!2026' });
    check((await response.text()).includes('Keep your details up to date'), 'login succeeds for ' + email);
}

async function eventChecks() {
    const guest = await request.newContext();
    let html = await (await guest.get(base + '/events.php?q=Creative%20skills')).text();
    const eventId = html.match(/id="event-(\d+)"/)[1];
    let response = await guest.get(base + '/event_register.php?event_id=' + eventId);
    check(response.url().includes('dashboard.php?event_id=' + eventId), 'visitor selected event is preserved through login redirect');
    response = await form(guest, 'dashboard.php?event_id=' + eventId, { action: 'login', email: 'alex.member@example.test', password: 'ClubConnectDemo!2026', event_id: eventId, return_url: 'https://example.org' });
    check(response.url().includes('event_register.php?event_id=' + eventId), 'login returns only to validated local event, ignoring external redirect');
    html = await response.text();
    const memberToken = token(html);
    check(html.includes('Confirm my RSVP') && !html.includes('Already registered'), 'opening event page alone does not register');
    response = await guest.post(base + '/event_register.php?event_id=' + eventId, { form: { action: 'register', csrf_token: memberToken, member_id: '1' } });
    check((await response.text()).includes('Already registered'), 'member confirms RSVP via POST');
    response = await guest.post(base + '/event_register.php?event_id=' + eventId, { form: { action: 'register', csrf_token: memberToken } });
    check((await response.text()).includes('Already registered'), 'repeat RSVP request is harmless');
    response = await guest.get(base + '/dashboard.php?cancel=' + eventId);
    check((await response.text()).includes('Cancel your place?'), 'cancellation has a server-rendered confirmation step');
    response = await guest.get(base + '/event_register.php?event_id=' + eventId);
    check((await response.text()).includes('Already registered'), 'opening cancellation confirmation does not cancel');
    response = await form(guest, 'dashboard.php', { action: 'cancel', cancel_event_id: eventId });
    check((await response.text()).includes('Please confirm the event cancellation'), 'cancellation requires explicit confirmation');
    await form(guest, 'dashboard.php', { action: 'cancel', cancel_event_id: eventId, confirm_cancel: '1', member_id: '1' });
    html = await (await guest.get(base + '/event_register.php?event_id=' + eventId)).text();
    check(html.includes('Previous registration cancelled'), 'confirmed cancellation persists');
    response = await form(guest, 'event_register.php?event_id=' + eventId, { action: 'register' });
    check((await response.text()).includes('Already registered'), 'member can rejoin a future event');
    // Leave the demo account ready for the same acceptance walkthrough on another run.
    await form(guest, 'dashboard.php', { action: 'cancel', cancel_event_id: eventId, confirm_cancel: '1' });
    html = await (await guest.get(base + '/events.php?participants=' + eventId)).text();
    check(!html.includes('sam.committee@example.test'), 'member cannot obtain participant email list via URL');
    response = await guest.post(base + '/events.php', { form: { action: 'save_event', csrf_token: memberToken, committee_id: '1' } });
    check(response.status() === 403, 'member cannot forge committee event action');
    html = await (await guest.get(base + '/events.php?q=NO_MATCH_' + Date.now())).text();
    check(html.includes('No matching events'), 'server-side search has a useful empty state');
    html = await (await guest.get(base + '/events.php?view=past')).text();
    check(html.includes('Demo: Welcome and ideas evening') && !html.includes('Demo: Creative skills workshop'), 'past events are explicitly separated');
    const expired = await request.newContext();
    await login(expired, 'taylor.expired@example.test');
    response = await form(expired, 'dashboard.php', { action: 'profile', first_name: 'Taylor', last_name: 'Chen', student_id: 'CCDEMO003', email: 'taylor.expired@example.test', phone: '' });
    const expiredToken = token(await response.text());
    response = await expired.post(base + '/events.php', { form: { action: 'save_event', csrf_token: expiredToken } });
    check(response.status() === 403, 'expired committee cannot create events via HTTP');
    const committee = await request.newContext();
    await login(committee, 'sam.committee@example.test');
    const eventData = { action: 'save_event', event_id: '', event_name: 'Browser workshop ' + Date.now(), description: '<script>alert("test")</script> Fictional acceptance event.', event_date: new Date(Date.now() + 15 * 86400000).toISOString().slice(0, 10), start_time: '13:00', end_time: '14:30', location: 'Browser test room', capacity: '5', created_by: '2147483647' };
    const csrf = token(await (await committee.get(base + '/events.php?add=1')).text());
    response = await committee.post(base + '/events.php', { form: { ...eventData, csrf_token: csrf }, maxRedirects: 0 });
    check(response.status() === 303, 'committee event creation uses Post/Redirect/Get');
    const createdId = response.headers().location.match(/event-(\d+)/)[1];
    html = await (await committee.get(base + '/events.php?q=' + encodeURIComponent(eventData.event_name))).text();
    check(html.includes('&lt;script&gt;') && !html.includes('<script>alert'), 'event descriptions are encoded instead of executed');
    response = await committee.post(base + '/events.php', { form: { ...eventData, event_id: createdId, capacity: '6', csrf_token: csrf } });
    check((await response.text()).includes('Event details updated'), 'current committee can edit event');
    html = await (await committee.get(base + '/events.php?participants=' + eventId)).text();
    check(html.includes('sam.committee@example.test') && !html.includes('CCDEMO002'), 'committee receives limited participant data without student IDs');
    response = await committee.post(base + '/events.php', { form: { ...eventData, csrf_token: 'wrong' } });
    check(response.status() === 403, 'committee event action rejects invalid CSRF');
    for (const ctx of [guest, committee, expired]) { await ctx.dispose(); }
}

async function layoutChecks() {
    const browsers = [
        ['Chrome', 'C:/Program Files/Google/Chrome/Application/chrome.exe'],
        ['Edge', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'],
    ];
    const pages = ['index.php', 'about.php', 'committee.php', 'register.php', 'events.php', 'dashboard.php', 'contact.php', 'event_register.php'];
    for (const [name, executablePath] of browsers) {
        if (!fs.existsSync(executablePath)) { console.log('UNAVAILABLE: ' + name); continue; }
        const browser = await chromium.launch({ executablePath, headless: true });
        const context = await browser.newContext();
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        for (const width of [375, 768, 1200]) {
            await page.setViewportSize({ width, height: 900 });
            for (const endpoint of pages) {
                const response = await page.goto(preview + '/' + endpoint);
                check(response.status() < 400, `${name} ${width}px ${endpoint} loads`);
                await page.locator('body').waitFor();
                check(await page.locator('h1').count() === 1, `${name} ${width}px ${endpoint} has one main heading`);
                check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${name} ${width}px ${endpoint} has no horizontal overflow`);
                const broken = await page.locator('img').evaluateAll(imgs => imgs.filter(i => i.complete && i.naturalWidth === 0).map(i => i.src));
                check(!broken.length, `${name} ${width}px ${endpoint} local images load`);
                const icon = await page.locator('link[rel="icon"]').first().getAttribute('href');
                check((await context.request.get(new URL(icon, preview).href)).status() === 200, `${name} ${width}px ${endpoint} favicon loads`);
            }
            await page.goto(preview + '/index.php');
            await page.screenshot({ path: path.join(out, `${name.toLowerCase()}-home-${width}.png`), fullPage: true });
        }
        await page.goto(preview + '/register.php');
        await page.keyboard.press('Tab');
        check(await page.evaluate(() => document.activeElement.textContent.toLowerCase().includes('skip')), `${name} keyboard reaches skip link first`);
        check(errors.length === 0, `${name} has no uncaught JavaScript errors`);
        const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 375, height: 900 } });
        const plain = await noJs.newPage();
        await plain.goto(preview + '/register.php');
        const joinLink = plain.locator('nav a[href$="register.php"]');
        const menu = plain.locator('summary');
        if (await menu.count() && await menu.first().isVisible()) { await menu.first().click(); }
        check(await joinLink.first().isVisible(), `${name} no-JavaScript mobile navigation remains usable`);
        check(await plain.locator('form[method="post"] button[type="submit"]').count() > 0, `${name} no-JavaScript form submits natively`);
        await browser.close();
    }
}

async function httpChecks() {
    const visitor = await request.newContext();
    for (const privatePath of ['config/config.local.php', 'database/schema.sql', 'scripts/seed.php', 'storage/login-throttle.json', 'app/bootstrap.php', '.git/config']) {
        check((await visitor.get(base + '/' + privatePath)).status() >= 400, 'private path cannot be downloaded: ' + privatePath);
    }
    let response = await visitor.post(base + '/dashboard.php', { form: { action: 'login', email: 'alex.member@example.test', password: 'ClubConnectDemo!2026' } });
    check(response.status() === 403, 'login without CSRF rejected');
    response = await form(visitor, 'dashboard.php', { action: 'login', email: "' OR 1=1 --@example.test", password: 'Not the password' });
    check((await response.text()).includes('email address or password is incorrect'), 'SQL-like login input cannot authenticate');
    const profile = { first_name: 'Jordan', last_name: 'Demo', student_id: 'B' + Date.now(), email: `browser.${Date.now()}@example.test`, phone: '', password: 'BrowserTestPass!2026', confirm_password: 'BrowserTestPass!2026' };
    response = await form(visitor, 'register.php', profile);
    check((await response.text()).includes('Please read and acknowledge'), 'privacy acknowledgement required');
    response = await form(visitor, 'register.php', { ...profile, password: 'short', confirm_password: 'short', privacy: '1' });
    check((await response.text()).includes('Use a passphrase'), 'short password rejected server-side');
    response = await form(visitor, 'register.php', { ...profile, password: 'é'.repeat(37), confirm_password: 'é'.repeat(37), privacy: '1' });
    check((await response.text()).includes('Use a passphrase'), 'bcrypt UTF-8 byte limit enforced');
    response = await form(visitor, 'register.php', { ...profile, password: 'DifferentPass!2026', privacy: '1' });
    check((await response.text()).includes('passwords must match'), 'password mismatch rejected');
    response = await form(visitor, 'register.php', { ...profile, privacy: '1', membership_type: 'life', status: 'inactive', committee_id: '1' });
    check((await response.text()).includes('Your account is ready'), 'valid member registration persists and redirects to login');
    response = await form(visitor, 'register.php', { ...profile, privacy: '1' });
    let html = await response.text();
    check(html.includes('email address is already in use') && html.includes('student ID is already in use'), 'duplicate email and student ID rejected');
    check(!html.includes('value="BrowserTestPass!2026"'), 'password is never redisplayed');
    response = await form(visitor, 'dashboard.php', { action: 'login', email: profile.email, password: profile.password });
    html = await response.text();
    check(html.includes('Hello, Jordan.') && html.includes('Standard membership'), 'registered account logs in with standard membership');
    check(!html.includes('You are a current committee member'), 'public registration cannot grant committee access');
    response = await visitor.get(base + '/dashboard.php?member_id=2');
    check(!(await response.text()).includes('sam.committee@example.test'), 'changing member_id cannot expose another profile');
    response = await form(visitor, 'dashboard.php', { action: 'profile', ...profile, first_name: '<script>alert(1)</script>', member_id: '2', membership_type: 'life' });
    html = await response.text();
    check(html.includes('&lt;script&gt;alert(1)&lt;/script&gt;') && !html.includes('<script>alert(1)</script>'), 'profile stores text safely and encodes HTML output');
    check(html.includes('Standard membership'), 'profile cannot change membership category');
    response = await form(visitor, 'dashboard.php', { action: 'logout' });
    check((await response.text()).includes('Welcome back'), 'CSRF-protected logout returns login state');
    response = await form(visitor, 'dashboard.php', { action: 'login', email: profile.email, password: profile.password });
    check((await response.text()).includes('&lt;script&gt;alert(1)&lt;/script&gt;'), 'profile persists across logout and login');
    const cookie = (await visitor.storageState()).cookies.find(c => c.name === 'clubconnect_session');
    check(cookie && cookie.httpOnly && cookie.sameSite === 'Lax', 'session cookie uses HttpOnly and SameSite Lax');
    const inactive = await request.newContext();
    response = await form(inactive, 'dashboard.php', { action: 'login', email: 'inactive.member@example.test', password: 'ClubConnectDemo!2026' });
    check((await response.text()).includes('email address or password is incorrect'), 'inactive account cannot log in');
    await inactive.dispose(); await visitor.dispose();

    const expiredId = spawnSync('C:/xampp/php/php.exe', [path.join(__dirname, 'test-domain.php'), '--expired-session'], {
        env: { ...process.env, CLUBCONNECT_TEST_DB: process.env.CLUBCONNECT_TEST_DB || 'clubconnect_test_acceptance_20260928' }, encoding: 'utf8'
    });
    assert.equal(expiredId.status, 0, expiredId.stderr);
    const expiredSession = await request.newContext({ storageState: { cookies: [{ name: 'clubconnect_session', value: expiredId.stdout.trim(), domain: '127.0.0.1', path: '/', expires: -1, httpOnly: true, secure: false, sameSite: 'Lax' }], origins: [] } });
    html = await (await expiredSession.get(base + '/dashboard.php')).text();
    check(html.includes('session expired after 30 minutes') && !html.includes('Keep your details up to date'), 'inactivity timeout removes protected dashboard access');
    await expiredSession.dispose();

    const throttleEmail = `throttle.${Date.now()}@example.test`;
    for (let attempt = 0; attempt < 9; attempt++) {
        // New cookie jar each time proves the limit is independent of browser sessions.
        const fresh = await request.newContext();
        response = await form(fresh, 'dashboard.php', { action: 'login', email: throttleEmail, password: 'WrongPassword!2026' });
        if (attempt === 8) { check(response.status() === 429 && (await response.text()).includes('Too many sign-in attempts'), 'login throttle survives clearing cookies'); }
        await fresh.dispose();
    }
}

(async () => {
    await layoutChecks();
    await eventChecks();
    await httpChecks();
    console.log(`Completed ${count} browser/HTTP assertions. Screenshots: ${out}`);
})().catch(error => { console.error(error); process.exitCode = 1; });
