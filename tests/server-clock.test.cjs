const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const code=fs.readFileSync('public/assets/js/server-clock.js','utf8');
const epoch=Date.parse('2026-10-20T15:59:58.000Z'); // 11:59:58 PM in Manila.
let checks=0;
function fixture({seed=epoch,online=true,hidden=false}={}){
 let mono=0,wall=Date.parse('2001-01-01T00:00:00Z'),reply={server_time_ms:epoch},ok=true,json=true,latency=0,pending=null;
 const requests=[],intervals=[],timeouts=new Map(),events={},docEvents={},attrs={},clock={dataset:{serverTimeMs:String(seed),syncUrl:'/server-time'}},date={},time={setAttribute:(key,value)=>attrs[key]=value};
 const document={hidden,getElementById:id=>({topbarClock:clock,clockDate:date,clockDisplay:time}[id]),addEventListener:(type,fn)=>docEvents[type]=fn};
 const navigator={onLine:online};let serial=0;
 class DeviceDate extends Date{constructor(...args){super(...(args.length?args:[wall]));}static now(){return wall;}}
 const context={document,navigator,performance:{now:()=>mono},Date:DeviceDate,Intl,AbortController,window:{
  setInterval:(fn,ms)=>intervals.push({fn,ms}),setTimeout:(fn,ms)=>{const id=++serial;timeouts.set(id,{fn,ms});return id;},clearTimeout:id=>timeouts.delete(id),addEventListener:(type,fn)=>events[type]=fn},
  fetch:async(url,options)=>{requests.push({url,options});if(pending)await Promise.race([pending,new Promise((resolve,reject)=>options.signal.addEventListener('abort',()=>reject(new Error('Aborted')),{once:true}))]);mono+=latency;return{ok,headers:{get:()=>json?'application/json':'text/html'},json:async()=>reply};}};
 vm.runInNewContext(code,context);
 const settle=()=>new Promise(r=>setImmediate(r));
 return{clock,date,time,attrs,requests,intervals,timeouts,events,docEvents,document,navigator,settle,
  advance:ms=>{mono+=ms;},wall:value=>{wall=value;},reply:value=>{reply=value;},latency:value=>{latency=value;},ok:value=>{ok=value;},json:value=>{json=value;},pending:value=>{pending=value;},
  tick:()=>intervals.find(i=>i.ms===1000).fn(),sync:()=>intervals.find(i=>i.ms===300000).fn()};
}
(async()=>{
 const f=fixture();await f.settle();assert.equal(f.attrs.datetime,'2026-10-20T15:59:58.000Z');assert.equal(f.clock.dataset.clockState,'synchronized');checks++;
 assert.match(f.date.textContent,/October 20, 2026/);assert.match(f.time.textContent,/11:59.*PM/);checks++;
 assert.equal(f.requests[0].options.credentials,'omit');assert.equal(f.requests[0].options.cache,'no-store');assert.equal(f.requests[0].options.headers.Accept,'application/json');assert.equal(f.requests[0].options.headers['X-Pharxmaco-Activity'],undefined);checks++;
 f.wall(Date.parse('2099-12-31T00:00:00Z'));f.advance(1000);f.tick();assert.equal(f.attrs.datetime,'2026-10-20T15:59:59.000Z');checks++;
 f.advance(1000);f.tick();assert.match(f.date.textContent,/October 21, 2026/);assert.match(f.time.textContent,/12:00.*AM/);checks++;
 f.reply({server_time_ms:epoch+60000});f.latency(200);await f.sync();assert.equal(f.attrs.datetime,'2026-10-20T16:00:58.100Z');checks++;
 assert.equal(f.timeouts.size,0);checks++;
 const saved=f.attrs.datetime;f.reply({server_time_ms:'bad'});await f.sync();assert.equal(f.clock.dataset.clockState,'estimated');assert.equal(f.attrs.datetime,saved);f.advance(1000);f.tick();assert.ok(Date.parse(f.attrs.datetime)>Date.parse(saved));checks++;
 f.json(false);await f.sync();assert.equal(f.clock.dataset.clockState,'estimated');checks++;
 f.json(true);f.ok(false);await f.sync();assert.equal(f.clock.dataset.clockState,'estimated');checks++;
 f.ok(true);f.reply({server_time_ms:epoch+120000});await f.sync();assert.equal(f.clock.dataset.clockState,'synchronized');checks++;
 const before=f.requests.length;f.document.hidden=true;await f.sync();assert.equal(f.requests.length,before);f.document.hidden=false;f.docEvents.visibilitychange();await f.settle();assert.equal(f.requests.length,before+1);checks++;
 f.navigator.onLine=false;await f.sync();const offlineCount=f.requests.length;f.advance(120000);f.tick();assert.equal(f.requests.length,offlineCount);checks++;
 f.navigator.onLine=true;f.events.online();await f.settle();assert.equal(f.requests.length,offlineCount+1);checks++;
 f.events.focus();await f.settle();assert.equal(f.requests.length,offlineCount+2);checks++;
 let release;f.pending(new Promise(r=>release=r));const first=f.sync();const inFlight=f.requests.length;await f.sync();assert.equal(f.requests.length,inFlight);release();await first;f.pending(null);checks++;
 f.pending(new Promise(()=>{}));const timed=f.sync();[...f.timeouts.values()].find(t=>t.ms===8000).fn();await timed;assert.equal(f.clock.dataset.clockState,'estimated');assert.equal(f.timeouts.size,0);f.pending(null);await f.sync();assert.equal(f.clock.dataset.clockState,'synchronized');checks++;
 const freshOffline=fixture({online:false});assert.equal(freshOffline.requests.length,0);freshOffline.advance(3000);freshOffline.tick();assert.equal(freshOffline.attrs.datetime,'2026-10-20T16:00:01.000Z');checks++;
 const invalid=fixture({seed:0});assert.equal(invalid.requests.length,0);assert.equal(invalid.intervals.length,0);checks++;
 console.log(`Passed ${checks} server clock checks, including incorrect device time, midnight rollover, network failures and session-free synchronization.`);
})().catch(e=>{console.error(e);process.exitCode=1;});
