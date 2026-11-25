const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const code=fs.readFileSync('public/assets/js/cashier-session.js','utf8');
let checks=0;
function fixture(renewLogin='1'){
 const events={},windowEvents={},requests=[],notices=[];let timer,reply=200,clock=0,pending;
 const element=tag=>({tag,style:{},children:[],setAttribute(name,value){this[name]=value;},append(...children){this.children.push(...children);}});
 const document={hidden:false,currentScript:{dataset:{activityUrl:'/session/activity',loginUrl:'/login',renewLogin}},
  addEventListener(type,fn){events[type]=fn;},createElement:element,querySelector(){return {prepend:notice=>notices.push(notice)};}};
 const window={addEventListener(type,fn){windowEvents[type]=fn;},setInterval(fn,ms){assert.equal(ms,30000);timer=fn;},
  dispatchEvent(event){windowEvents[event.type]?.(event);}};
 const navigator={onLine:true};
 const context={document,window,navigator,CustomEvent:class{constructor(type,init){this.type=type;this.detail=init.detail;}},Date:{now:()=>clock},
  fetch:async(url,options)=>{requests.push({url,options});if(pending)await pending;return {status:reply,ok:reply===200,headers:{get:()=> 'application/json'},json:async()=>({success:reply===200,message:'Your cashier login ended.'})};}};
 vm.runInNewContext(code,context);
 return {events,windowEvents,requests,notices,document,navigator,window,setReply:value=>reply=value,setClock:value=>clock=value,
  setPending:value=>pending=value,tick:async()=>{if(timer)await timer();await new Promise(resolve=>setImmediate(resolve));}};
}
(async()=>{
 const f=fixture();
 await f.tick();assert.equal(f.requests.length,0);checks++;
 f.events.input({isTrusted:false});await f.tick();assert.equal(f.requests.length,0);checks++;
 f.events.keydown({isTrusted:true});await f.tick();assert.equal(f.requests.length,1);assert.equal(f.requests[0].options.method,undefined);assert.equal(f.requests[0].options.headers['X-Pharxmaco-Activity'],'1');checks++;
 await f.tick();assert.equal(f.requests.length,1);checks++;
 f.document.hidden=true;f.events.pointerdown({isTrusted:true});await f.tick();assert.equal(f.requests.length,1);checks++;
 f.document.hidden=false;await f.tick();assert.equal(f.requests.length,2);checks++;
 f.navigator.onLine=false;f.events.input({isTrusted:true});await f.tick();assert.equal(f.requests.length,2);checks++;
 f.navigator.onLine=true;f.setReply(503);await f.tick();assert.equal(f.notices.length,0);checks++;
 f.setReply(200);await f.tick();assert.equal(f.requests.length,4);checks++;
 let release;f.setPending(new Promise(resolve=>release=resolve));f.events.input({isTrusted:true});const first=f.tick();await f.tick();assert.equal(f.requests.length,5);release();await first;f.setPending(null);checks++;
 f.setClock(14*60000+59000);f.events.input({isTrusted:true});await new Promise(resolve=>setImmediate(resolve));assert.equal(f.requests.length,6,'Recent interaction should renew immediately after an idle interval');checks++;
 f.setReply(401);f.events.pointerdown({isTrusted:true});await f.tick();assert.equal(f.notices.length,1);assert.equal(f.notices[0].children[1].href,'/login');checks++;
 f.events.input({isTrusted:true});await f.tick();assert.equal(f.notices.length,1);assert.equal(f.requests.length,7);checks++;
 const external=fixture();external.window.dispatchEvent({type:'pharxmaco:session-ended',detail:{message:'Ended by administrator'}});assert.equal(external.notices.length,1);assert.equal(external.notices[0].children[0].textContent,'Ended by administrator');checks++;
 external.events.input({isTrusted:true});await external.tick();assert.equal(external.requests.length,0);checks++;
 const admin=fixture('0');await admin.tick();assert.equal(admin.requests.length,0);assert.equal(admin.events.input,undefined);checks++;
 admin.window.dispatchEvent({type:'pharxmaco:session-ended',detail:{message:'Password changed. Please sign in.'}});assert.equal(admin.notices.length,1);assert.equal(admin.notices[0].children[1].href,'/login');checks++;
 await admin.tick();assert.equal(admin.requests.length,0);checks++;
 console.log(`Passed ${checks} cashier activity/admin notice checks.`);
})().catch(error=>{console.error(error);process.exitCode=1;});
