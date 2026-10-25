// Captures d'écran du site de démonstration (voir README.md) :
//   MOODLE_URL=http://localhost:8001 node shot.js plan.json ../images
// Fenêtre de 1366 px à l'échelle 1,5 : les images font 2049 px de large, comme celles des guides.
const { chromium } = require('playwright');
const fs = require('fs');
const base = process.env.MOODLE_URL || 'http://localhost:8001';
const plan = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const out = process.argv[3] || 'shots';


(async () => {
  const browser = await chromium.launch();
  const sessions = {};
  async function session(user) {
    if (sessions[user]) return sessions[user];
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 1.5, locale: 'fr-FR' });
    const page = await ctx.newPage();
    await page.goto(base + '/login/index.php');
    await page.fill('#username', user);
    await page.fill('#password', 'Demo-2026!');
    await page.click('#loginbtn');
    await page.waitForLoadState('networkidle');
    await page.addStyleTag({ content: '.drawer-toggles, .drawer-toggler { display: none !important; }' });
    sessions[user] = page;
    return page;
  }
  for (const step of plan) {
    const page = await session(step.user);
    if (step.url) {
      await page.goto(base + step.url, { waitUntil: 'networkidle' });
    }
    for (const action of step.actions || []) {
      const [kind, a, b] = action;
      if (kind === 'click') await page.click(a);
      else if (kind === 'fill') await page.fill(a, b);
      else if (kind === 'select') await page.selectOption(a, b);
      else if (kind === 'check') await page.check(a);
      else if (kind === 'wait') await page.waitForLoadState('networkidle');
      else if (kind === 'eval') await page.evaluate(a);
      else if (kind === 'expandall') await page.evaluate(() => document.querySelectorAll('fieldset.collapsible .fheader.collapsed').forEach(a => a.click()));
      else if (kind === 'expand') await page.evaluate((sel) => { const a = document.querySelector(sel + ' .fheader'); if (a && a.classList.contains('collapsed')) a.click(); }, a);
      else if (kind === 'clicktext') { await page.getByText(a, { exact: true }).first().click(); await page.waitForLoadState('networkidle'); }
      else if (kind === 'scrollto') await page.locator(a).scrollIntoViewIfNeeded();
    }
    if (!step.file) continue;
    await page.addStyleTag({ content: '.drawer-toggles, .drawer-toggler { display: none !important; }' });
    // Les notifications flottantes et le sélecteur de thème ne font pas partie de l'écran utile.
    await page.evaluate((keep) => document.querySelectorAll((keep ? '' : '#user-notifications .alert, .toast-wrapper, ') + '[data-region="footer-container-popover"], .footer-popover, .btn-footer-popover, .usertour').forEach(e => e.remove()), !!step.keepalerts);
    const file = `${out}/${step.file}.png`;
    if (step.from || step.to) {
      const top = step.from ? await page.locator(step.from).first().boundingBox() : { y: 0 };
      const toLoc = step.to ? page.locator(step.to).first() : page.locator(step.bottom || '#region-main').first();
      const bot = await toLoc.boundingBox();
      const scrollY = await page.evaluate(() => window.scrollY);
      const y = step.from ? Math.max(0, top.y + scrollY - 16) : 0;
      const end = step.to ? (bot.y + scrollY - 12) : (bot.y + bot.height + scrollY + 24);
      const height = Math.min(end - y, step.maxheight || 4000);
      await page.screenshot({ path: file, fullPage: true, clip: { x: 0, y, width: 1366, height } });
    } else if (step.element) {
      const loc = page.locator(step.element).first();
      await loc.scrollIntoViewIfNeeded();
      await loc.screenshot({ path: file });
    } else {
      const bottom = await page.evaluate((sel) => {
        const el = document.querySelector(sel) || document.body;
        const r = el.getBoundingClientRect();
        return Math.ceil(r.bottom + window.scrollY) + 32;
      }, step.bottom || '#region-main');
      const height = Math.min(bottom, step.maxheight || 4000);
      await page.screenshot({ path: file, fullPage: true, clip: { x: 0, y: 0, width: 1366, height } });
    }
    console.log('ok', step.file);
  }
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
