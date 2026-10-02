const fs = require('fs');
const {execFileSync} = require('child_process');
const {request, chromium} = require('C:/Users/apexinventives/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const php='C:/xampp/php/php.exe';
const assert=(ok,msg)=>{if(!ok) throw Error(msg);};
const run=(args)=>execFileSync(php,args,{encoding:'utf8'});
(async()=>{
 run(['tmp/permissions-fixture.php','setup']);
 const fixture=JSON.parse(fs.readFileSync('tmp/permissions-fixture.json','utf8'));
 const baseURL='http://localhost/Brightvision-Admin/';
 const admin=await request.newContext({baseURL}); const staff=await request.newContext({baseURL});
 let browser;
 const setPermissions=permissions=>run(['-r',`require 'config/database.php'; $c=getConnection(); $s=$c->prepare('UPDATE account_permissions p JOIN admins a ON a.id=p.admin_id SET p.permissions_json=? WHERE a.username=?'); $j=$argv[1]; $u=$argv[2]; $s->bind_param('ss',$j,$u); $s->execute();`,JSON.stringify(permissions),fixture.prefix+'_staff']);
 const checkPage=async(ctx,url)=>{const r=await ctx.get(url); const h=await r.text(); assert(r.status()===200,url+' failed '+r.status()); assert(!/Parse error|Fatal error|Warning:|Notice:/.test(h),url+' PHP diagnostic'); for(const m of h.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)) new Function(m[1]); return h;};
 try {
  await admin.post('login.php',{form:{username:fixture.username,password:fixture.password}});
  const html=await checkPage(admin,'accounts.php'); const csrf=html.match(/name="csrf_token" value="([a-f0-9]+)"/)[1];
  const form={csrf_token:csrf,action:'create',full_name:'Granular permission fixture',username:fixture.prefix+'_staff',email:fixture.prefix+'_staff@example.invalid',password:fixture.password,role:'staff',is_active:'1','permissions[payments][view]':'1','permissions[reports][view]':'1','permissions[sidebar][view]':'1'};
  const create=await admin.post('accounts.php',{form}); assert((await create.text()).includes('Account and permissions saved'),'Create failed');
  await staff.post('login.php',{form:{username:form.username,password:fixture.password}});
  let h=await checkPage(staff,'payments.php'); assert(!h.includes('id="payment-form"')&&!h.includes('id="payment-amount-settings"')&&!h.includes('<dialog id="edit-payment'),'Amount-bearing forms leaked'); assert(h.includes('const amountSettings = {}'),'JavaScript amount defaults leaked');
  h=await checkPage(staff,'payment-reports.php'); assert(!h.includes('Export CSV')&&!h.includes('Export PDF')&&h.includes('Hidden'),'Report controls or amounts leaked');
  for(const route of ['users.php','view-user.php?id=0','add-user.php','edit-user.php?id=0','delete-user.php?id=0','reservations.php','approve-reservation.php?id=0','delete-reservation.php?id=0','settings.php','profile.php','activity-log.php','accounts.php','bv-growth-upload.php','logout.php','payment-reports.php?format=csv','payment-reports.php?format=method_csv','payment-reports.php?format=pdf']) assert((await staff.get(route,{maxRedirects:0})).status()===403,'Unauthorized route allowed '+route);
  for(const action of ['save_amount_settings','delete_payment','edit_student','delete_course','create_course','toggle_installment','create','invalid']) assert((await staff.post('payments.php',{form:{action}})).status()===403,'Unauthorized payment action '+action);
  setPermissions({sidebar:{view:true},dashboard:{view:true,students:true},students:{view:true},settings:{view:true},profile:{view:true},accounts:{view:true},activity:{view:true}});
  h=await checkPage(staff,'index.php'); assert(h.includes('Total Students')&&!h.includes('Total Reservations')&&!h.includes('Recent Students'),'Dashboard card permissions failed');
  h=await checkPage(staff,'users.php'); assert(!h.includes('title="View"')&&!h.includes('title="Edit"')&&!h.includes('title="Delete"'),'Student action permissions failed');
  for(const route of ['settings.php','profile.php','accounts.php','activity-log.php']) await checkPage(staff,route);
  for(const route of ['settings.php','profile.php','accounts.php']) assert((await staff.post(route,{form:{action:'update'}})).status()===403,'Read-only mutation allowed '+route);
  setPermissions({reports:{view:true,amounts:true,csv:true},payments:{view:true,amounts:true,edit_student:true},reservations:{view:true,approve:true}});
  assert((await staff.get('payment-reports.php?format=csv')).status()===200,'Granted CSV failed'); assert((await staff.get('payment-reports.php?format=pdf')).status()===403,'PDF allowed with CSV grant');
  setPermissions({reports:{view:true,csv:true}}); assert((await staff.get('payment-reports.php?format=csv')).status()===403,'Export bypassed amount permission');
  setPermissions({reservations:{view:true,approve:true}});
  assert((await staff.post('update-approval.php',{data:{id:0,status:1}})).status()===400,'Approve grant blocked by obsolete manage check');
  assert((await staff.post('update-approval.php',{data:{id:1,status:1,phone:'0000000000'}})).status()===403,'Approval endpoint bypassed SMS permission');
  assert((await staff.post('save-sms-log.php',{data:{}})).status()===403,'SMS log bypassed permission');
  setPermissions({reservations:{view:true,sms:true}});
  assert((await staff.post('save-sms-log.php',{data:{}})).status()===400,'SMS grant blocked by obsolete manage check');
  for(const route of ['index.php','users.php','reservations.php','payments.php','payment-reports.php','settings.php','profile.php','accounts.php','activity-log.php','bv-growth-upload.php']) await checkPage(admin,route);
  browser=await chromium.launch({headless:true,channel:"msedge"}); const page=await browser.newPage(); const errors=[]; page.on('pageerror',e=>errors.push(e.message));
  await page.goto(baseURL+'login.php'); await page.locator('[name="username"]').fill(fixture.username); await page.locator('[name="password"]').fill(fixture.password); await page.locator('button[type="submit"]').click();
  for(const route of ['settings.php','accounts.php','payments.php','profile.php']) {await page.goto(baseURL+route);}
  assert(!errors.length,'Browser JS errors: '+errors.join('; '));
  console.log('PASS: granular routes, actions, dashboard cards, amount redaction, export gates, live permission changes, PHP and JavaScript, browser pages.');
 } finally {if(browser) await browser.close(); await admin.dispose(); await staff.dispose(); run(['tmp/permissions-fixture.php','cleanup']);}
})().catch(e=>{console.error(e);process.exitCode=1;});
