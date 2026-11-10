// Capture de l'export pré-stage d'une thématique (format HTML, servi en téléchargement) :
//   MOODLE_URL=http://localhost:8001 node export_shot.js <cmid> <themeid> ../images/87_request_export.png
const { chromium } = require('playwright');
const fs = require('fs');
const base = process.env.MOODLE_URL || 'http://localhost:8001';
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1366, height: 600 }, deviceScaleFactor: 1.5, acceptDownloads: true });
  const page = await ctx.newPage();
  for (let attempt = 0; attempt < 4; attempt++) {
    await page.goto(base + '/login/index.php', { waitUntil: 'networkidle' });
    if (!(await page.locator('#username').count())) break;
    await page.fill('#username', 'cmartin');
    await page.fill('#password', 'Demo-2026!');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('#loginbtn')]);
    if (!page.url().includes('/login/')) break;
  }
  const url = `${base}/mod/stage/request_answers_export.php?id=${process.argv[2]}&themeid=${process.argv[3]}&dataformat=html`;
  const html = await page.evaluate(async (u) => (await fetch(u)).text(), url);
  const view = await ctx.newPage();
  await view.setContent(html.replace('</head>', '<style>body{font:13px sans-serif;margin:16px} td,th{padding:4px 6px}</style></head>'));
  const table = view.locator('table').first();
  await table.screenshot({ path: process.argv[4] });
  await browser.close();
  console.log('ok', process.argv[4]);
})().catch(e => { console.error(e); process.exit(1); });
