const fs = require('fs');
const {chromium} = require('C:/Users/apexinventives/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{
 const source=fs.readFileSync('payments.php','utf8');
 const css=source.match(/<style>([\s\S]*?)<\/style>/)[1];
 let script=source.match(/<script>([\s\S]*?)<\/script>/)[1];
 script=script.replace(/const amountSettings = [^\r\n]+/,'const amountSettings = {full_amount:30000,half_first:20000,half_second:13000,quarter_each:11000};').replace(/const submittedSchedule = [^\r\n]+/,'const submittedSchedule = null;');
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try {
 const page=await browser.newPage();
 await page.goto('http://localhost');
 for(const width of [375,1280]) {
 await page.setViewportSize({width,height:800});
 await page.setContent(`<style>*{box-sizing:border-box}${css}form{padding:24px}input:not([type=checkbox]),select{width:100%}.payment-edit-dialog label{display:block}</style><div style="white-space:nowrap"><dialog class="payment-edit-dialog"><form><h2>Edit Payment Details</h2><select class="edit-payment-method"><option value="full">Full Payment</option><option value="half" selected>Half Payment</option><option value="quarter">Quarter Payment</option><option value="scholarship">Scholarship</option><option value="free_card">Free Card</option></select><div class="edit-payment-rows"><div><input type="number" value="20000"><input type="date" value="2026-10-01"><input type="checkbox" checked></div><div><input type="number" value="13000"><input type="date" value="2026-11-15"><input type="checkbox"></div></div><input type="number" name="total_amount" readonly><p>Customize each amount, date, and paid status. The total is calculated automatically.</p></form></dialog></div><select id="payment_method"><option value=""></option></select><input id="total_amount"><div id="installmentSection"><div id="installmentRows"></div></div><p id="amountHelp"></p><select id="course_id"></select><input id="student_name"><input id="student_id_number"><datalist id="studentNames"></datalist>`);
 await page.addScriptTag({content:script});
 await page.locator('dialog').evaluate(d=>d.showModal());
 await page.selectOption('.edit-payment-method','quarter');
 const amounts=page.locator('.edit-payment-rows input[type=number]');
 if(await amounts.count()!==3)throw Error('Quarter does not show three rows');
 const dates=page.locator('.edit-payment-rows input[type=date]');
 if(await dates.nth(1).inputValue()!=='2026-11-15')throw Error('Second date lost on method change');
 await amounts.nth(0).fill('10000'); await amounts.nth(1).fill('12000');
 if(await page.locator('dialog input[name=total_amount]').inputValue()!=='33000.00')throw Error('Custom total incorrect');
 await dates.nth(1).fill('2026-11-25'); await dates.nth(2).fill('2026-12-30');
 await page.locator('.edit-payment-rows input[type=checkbox]').nth(1).check();
 if(await page.locator('dialog input[name=total_amount]').inputValue()!=='33000.00')throw Error('Date or switch changed total');
 await page.selectOption('.edit-payment-method','half');
 if(await amounts.count()!==2 || await dates.nth(1).inputValue()!=='2026-11-25')throw Error('Half rows/dates incorrect');
 await page.selectOption('.edit-payment-method','full');
 if(await amounts.count()!==1)throw Error('Full rows incorrect');
 await page.selectOption('.edit-payment-method','free_card');
 if(await amounts.nth(0).inputValue()!=='0.00' || !await amounts.nth(0).getAttribute('readonly')) {
  const readOnly=await amounts.nth(0).evaluate(e=>e.readOnly);
  if(!readOnly)throw Error('Free Card editable');
 }
 const bounds=await page.locator('dialog').evaluate(d=>({left:d.getBoundingClientRect().left,right:d.getBoundingClientRect().right,width:d.clientWidth,scroll:d.scrollWidth}));
 if(bounds.left<0 || bounds.right>width || bounds.scroll>bounds.width)throw Error('Popup overflow');
 console.log(`${width}px: payment row counts, custom total, second/third dates, paid switches and popup layout passed`);
 }
 } finally {await browser.close();}
})().catch(e=>{console.error(e.message);process.exit(1)});
