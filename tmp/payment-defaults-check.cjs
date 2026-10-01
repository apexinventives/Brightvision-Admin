const fs = require('fs');
const {chromium} = require('C:/Users/apexinventives/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{
 const source=fs.readFileSync('payments.php','utf8');
 let script=source.match(/<script>([\s\S]*?)<\/script>/)[1];
 script=script.replace(/const amountSettings = [^\r\n]+/,'const amountSettings = {full_amount:30000,half_first:20000,half_second:13000,quarter_each:11000};').replace(/const submittedSchedule = [^\r\n]+/,'const submittedSchedule = null;');
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try{
 const page=await browser.newPage();
 await page.goto('http://localhost');
 await page.setContent('<select id="payment_method"><option value=""></option><option>full</option><option>half</option><option>quarter</option><option>scholarship</option></select><input id="total_amount"><div id="installmentSection"><div id="installmentRows"></div></div><p id="amountHelp"></p><select id="course_id"></select><input id="student_name"><input id="student_id_number"><datalist id="studentNames"></datalist>');
 await page.addScriptTag({content:script});
 for(const [method,total,amounts] of [['full',30000,[30000]],['half',33000,[20000,13000]],['quarter',33000,[11000,11000,11000]],['scholarship',0,[0]]]){
 await page.selectOption('#payment_method',method);
 const actual=await page.evaluate(()=>({total:Number(document.getElementById('total_amount').value),amounts:[...document.querySelectorAll('#installmentRows input[type=number]')].map(e=>Number(e.value))}));
 if(actual.total!==total||JSON.stringify(actual.amounts)!==JSON.stringify(amounts))throw Error(JSON.stringify(actual));
 console.log(method+': '+actual.total+' = '+actual.amounts.join(' + '));
 }
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});
