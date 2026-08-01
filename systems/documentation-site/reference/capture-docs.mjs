// Capture documentation screenshots from the live Klees admin.
//
// Usage:
//   cd apps/marketing
//   KLEES_EMAIL=you@company.com KLEES_PASSWORD=... node scripts/capture-docs.mjs
//
// Logs into https://www.klees.app/admin, walks every documented route in a
// desktop viewport (1440x900) and a phone viewport (390x844), and saves PNGs
// under public/docs-img/<slug>/<name>[-mobile].png. Re-run any time the UI
// changes — files are overwritten in place.
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const BASE = process.env.KLEES_BASE_URL ?? 'https://www.klees.app';
const EMAIL = process.env.KLEES_EMAIL;
const PASSWORD = process.env.KLEES_PASSWORD;
if (!EMAIL || !PASSWORD) {
  console.error('Set KLEES_EMAIL and KLEES_PASSWORD env vars.');
  process.exit(1);
}

const OUT = resolve(dirname(fileURLToPath(import.meta.url)), '../public/docs-img');

// One entry per screenshot the docs reference.
//   slug  -> folder under docs-img (matches the docs page slug)
//   name  -> file name
//   path  -> admin route
//   mobile-> also capture a 390x844 phone-width shot
const SHOTS = [
  { slug: 'overview', name: 'dashboard', path: '/admin/dashboard' },
  { slug: 'quickstart', name: 'dashboard', path: '/admin/dashboard' },
  { slug: 'roles-and-access', name: 'permissions-tab', path: '/admin/people' },
  { slug: 'inviting-your-team', name: 'people', path: '/admin/people' },
  { slug: 'timesheets', name: 'timesheets', path: '/admin/timesheets' },
  { slug: 'jobs-and-customers', name: 'jobs', path: '/admin/jobs' },
  { slug: 'jobs-and-customers', name: 'customers', path: '/admin/customers' },
  { slug: 'scheduling', name: 'schedule', path: '/admin/schedule' },
  { slug: 'reports', name: 'reports', path: '/admin/reports' },
  { slug: 'reports', name: 'report-runner', path: '/admin/reports/quick-summary' },
  { slug: 'live-map', name: 'live-map', path: '/admin/live' },
  { slug: 'pinshot', name: 'pinshots', path: '/admin/pinshots' },
  { slug: 'people', name: 'people', path: '/admin/people' },
  { slug: 'company-settings', name: 'settings', path: '/admin/settings' },
  { slug: 'integrations', name: 'integrations', path: '/admin/integrations' },
  { slug: 'daily-logs', name: 'daily-logs', path: '/admin/daily-logs' },
  { slug: 'equipment', name: 'equipment', path: '/admin/equipment' },
];

const run = async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();

  // ---- login ----
  console.log('Signing in…');
  await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
  await page.fill('input[type="email"], input[name="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button:has-text("Sign in")');
  // Wait for the actual session token — /admin/login also matches /admin/**,
  // so URL alone can't prove the login worked.
  await page.waitForFunction(
    () => !!localStorage.getItem('klees:access') || !!sessionStorage.getItem('klees:access'),
    { timeout: 20000 },
  );
  await page.waitForLoadState('networkidle');
  console.log('Signed in.');

  // ---- capture ----
  for (const s of SHOTS) {
    const dir = `${OUT}/${s.slug}`;
    mkdirSync(dir, { recursive: true });
    let ok = false;
    for (let attempt = 1; attempt <= 2 && !ok; attempt++) {
      try {
        await page.setViewportSize({ width: 1440, height: 900 });
        // 'load' + settle beats 'networkidle' — SPAs with polling never idle.
        await page.goto(`${BASE}${s.path}`, { waitUntil: 'load', timeout: 25000 });
        await page.waitForTimeout(2500); // let data, maps, charts settle
        if (page.url().includes('/login')) throw new Error('bounced to login — session lost');
        await page.screenshot({ path: `${dir}/${s.name}.png`, timeout: 15000 });
        console.log(`✓ ${s.slug}/${s.name}.png`);
        ok = true;
      } catch (err) {
        if (attempt === 2) console.warn(`✗ ${s.slug}/${s.name}: ${err.message.split('\n')[0]}`);
      }
    }
  }

  await browser.close();
  console.log(`\nDone. Images in apps/marketing/public/docs-img/`);
  console.log('Commit them: git add apps/marketing/public/docs-img && git commit -m "docs: screenshots"');
};

run().catch((e) => {
  console.error(e);
  process.exit(1);
});
