const fs = require('fs');
const {chromium} = require('C:/Users/apexinventives/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async () => {
 const source = fs.readFileSync('payments.php', 'utf8');
 const css = source.match(/<style>([\s\S]*?)<\/style>/)[1];
 const browser = await chromium.launch({headless:true,channel:'msedge'});
 try {
  const page = await browser.newPage();
  for (const width of [375, 1280]) {
   await page.setViewportSize({width,height:720});
   await page.setContent(`<style>*{box-sizing:border-box} ${css} form{padding:24px} input{width:100%}</style><div style="white-space:nowrap"><dialog class="payment-edit-dialog"><form><h2>Edit Payment Details</h2><label>Student name<input value="Student name"></label><label>Student ID<input value="BV-110"></label><label>Total Amount (Rs.)<input value="10000"></label><p>Changing the total adjusts all installment amounts proportionally, including paid installments. Dates and paid status stay the same. Scholarship and Free Card amounts remain zero.</p><button>Cancel</button><button>Save Changes</button></form></dialog></div>`);
   await page.locator('dialog').evaluate(d => d.showModal());
   const result = await page.locator('dialog').evaluate(d => ({wrap:getComputedStyle(d).whiteSpace, width:d.clientWidth, content:d.scrollWidth,left:d.getBoundingClientRect().left,right:d.getBoundingClientRect().right}));
   if(result.wrap !== 'normal' || result.content > result.width || result.left < 0 || result.right > width) throw new Error(JSON.stringify(result));
   console.log(`${width}px: popup wraps correctly and fits viewport`);
  }
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
