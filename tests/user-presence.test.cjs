const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const code=fs.readFileSync('public/assets/js/user-presence.js','utf8');let checks=0;
function fixture(){
 const requests=[],events={},docEvents={},timers=new Map();let interval,reply=200,json=true,pending=null,serial=0;
 const document={hidden:false,currentScript:{dataset:{presenceToken:'a'.repeat(64),heartbeatUrl:'/presence/heartbeat'}},querySelector:()=>null,addEventListener:(name,fn)=>docEvents[name]=fn};
 const navigator={onLine:true},window={setInterval:fn=>interval=fn,setTimeout:(fn,ms)=>{const id=++serial;timers.set(id,{fn,ms});return id;},clearTimeout:id=>timers.delete(id),addEventListener:(name,fn)=>events[name]=fn};
 const settle=()=>new Promise(r=>setImmediate(r));
 vm.runInNewContext(code,{document,navigator,window,AbortController,fetch:async(url,options)=>{requests.push({url,options});if(pending)await Promise.race([pending,new Promise((r,j)=>options.signal.addEventListener('abort',()=>j(Error('Aborted')),{once:true}))]);return{status:reply,ok:reply===200,headers:{get:()=>json?'application/json':'text/html'},json:async()=>({success:reply===200})};}});
 return{requests,events,docEvents,document,navigator,timers,settle,tick:async()=>{await interval();await settle();},reply:value=>reply=value,json:value=>json=value,pending:value=>pending=value};
}
(async()=>{
 const f=fixture();await f.settle();assert.equal(f.requests.length,1);assert.equal(f.requests[0].options.method,'POST');assert.equal(f.requests[0].options.credentials,'omit');assert.equal(f.requests[0].options.headers['X-Pharxmaco-Presence'],'a'.repeat(64));assert.equal(f.requests[0].options.headers['X-Pharxmaco-Activity'],undefined);assert.equal(f.requests[0].url,'/presence/heartbeat');checks++;
 await f.tick();assert.equal(f.requests.length,2);checks++;
 f.document.hidden=true;await f.tick();assert.equal(f.requests.length,2);checks++;
 f.document.hidden=false;f.docEvents.visibilitychange();await f.settle();assert.equal(f.requests.length,3);checks++;
 f.navigator.onLine=false;await f.tick();assert.equal(f.requests.length,3);f.navigator.onLine=true;f.events.online();await f.settle();assert.equal(f.requests.length,4);checks++;
 f.events.focus();await f.settle();assert.equal(f.requests.length,5);checks++;
 let release;f.pending(new Promise(r=>release=r));const first=f.tick();await f.tick();assert.equal(f.requests.length,6);release();await first;f.pending(null);checks++;
 f.reply(503);await f.tick();const failed=f.requests.length;f.reply(200);await f.tick();assert.equal(f.requests.length,failed+1);checks++;
 f.json(false);await f.tick();const bad=f.requests.length;f.json(true);await f.tick();assert.equal(f.requests.length,bad+1);checks++;
 f.pending(new Promise(()=>{}));const waiting=f.tick();[...f.timers.values()][0].fn();await waiting;assert.equal(f.timers.size,0);f.pending(null);await f.tick();checks++;
 f.reply(401);await f.tick();const ended=f.requests.length;await f.tick();f.events.focus();await f.settle();assert.equal(f.requests.length,ended);checks++;
 const revoked=fixture();await revoked.settle();revoked.events['pharxmaco:session-ended']();await revoked.tick();assert.equal(revoked.requests.length,1);checks++;
 console.log(`Passed ${checks} stateless presence heartbeat checks.`);
})().catch(e=>{console.error(e);process.exitCode=1;});
