const { chromium } = require('/opt/node-tools/node_modules/playwright');
const OUT = process.argv[2];
// Stub of the PayPal JS SDK (real SDK host is blocked in this sandbox).
const STUB = `
window.paypal = {
  Buttons: function (o) {
    return { render: function (el) {
      var b = document.createElement('button'); b.type='button'; b.id='stub-paypal'; b.textContent='PayPal (stub)';
      b.style.cssText='width:100%;height:48px;background:#ffc439;border:0;border-radius:6px;font-weight:700';
      var c = document.createElement('button'); c.type='button'; c.id='stub-cancel'; c.textContent='Cancel (stub)';
      b.onclick = async function(){ try { var id = await o.createOrder({paymentSource:'paypal'}); window.__lastOrder=id;
        await fetch('/wp-json/mockpp/v1/approve/'+id,{method:'POST'}); await o.onApprove({orderID:id},{restart:function(){window.__restarted=true;}}); } catch(e){ o.onError && o.onError(e);} };
      c.onclick = async function(){ try { var id = await o.createOrder({paymentSource:'paypal'}); o.onCancel({orderID:id}); } catch(e){} };
      el.appendChild(b); el.appendChild(c); return Promise.resolve(); } };
  },
  CardFields: function(){ return { isEligible: function(){ return false; } }; }
};`;
(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' }).catch(() => chromium.launch());
  const results = [];
  const ok = (n, c) => { results.push((c ? 'PASS  ' : 'FAIL  ') + n); };
  for (const vp of [{ name: 'desktop', width: 1280, height: 900 }, { name: 'mobile', width: 390, height: 844 }]) {
    const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
    const page = await ctx.newPage();
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    await page.route('https://www.paypal.com/sdk/js**', r => r.fulfill({ contentType: 'application/javascript', body: STUB }));
    await page.goto('http://localhost:8899/pay/?ref=INV-3003&amount=120');
    await page.screenshot({ path: `${OUT}/${vp.name}-1-form.png`, fullPage: true });
    ok(`${vp.name}: URL prefill`, (await page.inputValue('input[name=reference]')) === 'INV-3003' && (await page.inputValue('input[name=amount]')) === '120.00');
    // client validation
    await page.fill('input[name=amount]', '0');
    await page.click('button[type=submit]');
    ok(`${vp.name}: validation errors shown`, (await page.locator('[data-error-for=name]').innerText()).length > 0 && (await page.locator('[data-error-for=amount]').innerText()).length > 0);
    await page.screenshot({ path: `${OUT}/${vp.name}-2-errors.png`, fullPage: true });
    await page.fill('input[name=name]', 'Jane Client');
    await page.fill('input[name=email]', 'jane@example.com');
    await page.fill('input[name=amount]', '120.50');
    await page.fill('textarea[name=description]', 'Logo design – final payment');
    await page.click('button[type=submit]');
    await page.waitForSelector('#stub-paypal');
    ok(`${vp.name}: summary total`, (await page.locator('.phx-pay__summary-total dd').innerText()).includes('120.50'));
    await page.screenshot({ path: `${OUT}/${vp.name}-3-review.png`, fullPage: true });
    await page.click('#stub-cancel');
    await page.waitForSelector('.phx-pay__alert--info');
    ok(`${vp.name}: cancel message`, (await page.locator('.phx-pay__alert').innerText()).includes('cancelled'));
    await page.click('#stub-paypal');
    await page.waitForSelector('[data-step=done]:not([hidden])');
    const title = await page.locator('[data-role=result-title]').innerText();
    ok(`${vp.name}: success screen after server capture`, title.includes('successful'));
    await page.screenshot({ path: `${OUT}/${vp.name}-4-success.png`, fullPage: true });
    ok(`${vp.name}: no JS errors`, errors.length === 0);
    ok(`${vp.name}: no horizontal scroll`, await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
    await ctx.close();
  }
  // Fixed amount + declined
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.route('https://www.paypal.com/sdk/js**', r => r.fulfill({ contentType: 'application/javascript', body: STUB }));
  await page.goto('http://localhost:8899/pay-fixed/');
  ok('fixed: amount shown and not editable', (await page.locator('.phx-pay__fixed-amount strong').innerText()).includes('250.00') && (await page.locator('input[name=amount][type=text]').count()) === 0);
  await page.fill('input[name=name]', 'Jane'); await page.fill('input[name=email]', 'jane@example.com');
  await page.screenshot({ path: `${OUT}/fixed-form.png`, fullPage: true });
  // tamper hidden amount: server must ignore
  await page.evaluate(() => { document.querySelector('input[name=amount]').value = '1.00'; });
  await page.click('button[type=submit]');
  await page.waitForSelector('#stub-paypal');
  await page.click('#stub-paypal');
  await page.waitForSelector('[data-step=done]:not([hidden])');
  const details = await page.locator('[data-role=result-details]').innerText();
  ok('fixed: tampered browser amount ignored (charged 250.00)', details.includes('250.00'));
  // Missing SDK -> error state
  const p2 = await ctx.newPage();
  await p2.route('https://www.paypal.com/sdk/js**', r => r.abort());
  await p2.goto('http://localhost:8899/pay/');
  await p2.fill('input[name=name]', 'A'); await p2.fill('input[name=email]', 'a@example.com'); await p2.fill('input[name=amount]', '10');
  await p2.click('button[type=submit]');
  await p2.waitForSelector('.phx-pay__alert--error');
  ok('SDK load failure shows helpful error', (await p2.locator('.phx-pay__alert').innerText()).includes('could not be loaded'));
  await p2.screenshot({ path: `${OUT}/sdk-error.png`, fullPage: true });
  await browser.close();
  console.log(results.join('\n'));
})();
