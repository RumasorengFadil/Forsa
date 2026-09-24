// Dependency-free contract tests for PRD 25–29. Run: node tests/ai/mascot.test.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
let frames = new Map(), id=0, now=0, reduced=false, draws=0;
const events={}, winEvents={};
const el=()=>({dataset:{},style:{},classList:{toggle(){}},setAttribute(){},addEventListener(k,fn){this[k]=fn;},focus(){this.focused=true;}});
const mascot=Object.assign(el(),{offsetWidth:100,offsetHeight:120,getBoundingClientRect:()=>({}),querySelector:()=>({})});
const bubble=el();
const panel=Object.assign(el(),{getBoundingClientRect:()=>({width:420}),querySelector:()=>({getBoundingClientRect:()=>({left:876,top:14,width:36})})});
const window={ForsaAi:{MascotRenderer:{create:()=>({draw(...args){draws++;window.lastPose=args;}})}},scrollY:0,innerWidth:1280,innerHeight:720,
    matchMedia:()=>({get matches(){return reduced;}}),addEventListener(k,fn){winEvents[k]=fn;},removeEventListener(){}};
const document={hidden:false,addEventListener(k,fn){events[k]=fn;}};
const context=vm.createContext({window,document,Math,getComputedStyle:()=>({right:'24',bottom:'24'}),
    requestAnimationFrame:fn=>{frames.set(++id,fn);return id;},cancelAnimationFrame:i=>frames.delete(i)});
for(const name of ['mascot-animation','mascot-scroll','mascot-controller']) vm.runInContext(fs.readFileSync(`public/assets/js/ai/${name}.js`,'utf8'),context);
function step(ms=20){now+=ms;const callbacks=[...frames.values()];frames.clear();callbacks.forEach(fn=>fn(now));}
function run(ms){for(let t=0;t<ms;t+=20)step();}
function scroll(y){window.scrollY=y;winEvents.scroll();step();}
function key(key, extras={}) {
    const event={key,ctrlKey:false,metaKey:false,altKey:false,shiftKey:false,
        preventDefault(){this.prevented=true;},...extras};
    events.keydown(event);return event;
}
const calls=[];
const controller=window.ForsaAi.MascotController.create({mascotEl:mascot,greetingEl:bubble,sidebarEl:panel,
    onPrepareSidebar:()=>calls.push('prepare'),onActivateSidebar:()=>calls.push('activate')});
assert.equal(mascot.dataset.state,'hidden');
assert.equal(mascot.style.visibility,'hidden');
key('w');assert.equal(mascot.dataset.state,'hidden');
key('K',{ctrlKey:true,shiftKey:true});
assert.equal(mascot.dataset.state,'climbing');
assert.equal(bubble.textContent,'Halo, ada yang bisa saya bantu...?');
step();run(9980);assert.equal(bubble.textContent,'Halo, ada yang bisa saya bantu...?');
step(); const greeting=bubble.textContent;assert.notEqual(greeting,'Halo, ada yang bisa saya bantu...?');
run(10000);assert.notEqual(bubble.textContent,greeting);
const before=draws;document.hidden=true;events.visibilitychange();run(1000);assert.equal(draws,before);
document.hidden=false;events.visibilitychange();run(60);assert.ok(draws>before);
scroll(30);assert.equal(mascot.dataset.state,'idle');scroll(51);assert.equal(mascot.dataset.state,'hiding');
scroll(0);run(720);assert.equal(mascot.dataset.state,'climbing');run(1020);assert.equal(mascot.dataset.state,'idle');
scroll(100);run(720);assert.equal(mascot.dataset.state,'hidden');assert.equal(mascot.tabIndex,-1);
scroll(80);assert.equal(mascot.dataset.state,'hidden');scroll(59);assert.equal(mascot.dataset.state,'climbing');run(1020);
mascot.click();mascot.click();assert.equal(mascot.dataset.state,'reacting');assert.deepEqual(calls,[]);
run(300);assert.equal(mascot.dataset.state,'grabbing');assert.deepEqual(calls,[]);
run(360);assert.equal(mascot.dataset.state,'pulling_sidebar');assert.deepEqual(calls,['prepare']);
run(760);assert.equal(mascot.dataset.state,'sidebar_locked');assert.deepEqual(calls,['prepare']);
run(160);assert.equal(mascot.dataset.state,'jumping_to_sidebar');run(660);
assert.equal(mascot.dataset.state,'sidebar_guide');assert.deepEqual(calls,['prepare','activate']);
controller.notifySidebarClosed();mascot.click();run(680);controller.notifySidebarClosed();run(3000);
assert.equal(mascot.dataset.state,'idle');assert.deepEqual(calls,['prepare','activate','prepare']);
// Typing, editor, repeat, IME, modifiers and previously handled events stay untouched.
for(const extras of [
    {target:{closest:()=>true}}, {target:{isContentEditable:true}},
    {composedPath:()=>[{isContentEditable:true}]}, {repeat:true}, {isComposing:true},
    {defaultPrevented:true}, {ctrlKey:true}, {metaKey:true}, {altKey:true}, {shiftKey:true}
]) { assert.ok(!key('a',extras).prevented);assert.equal(mascot.dataset.state,'idle'); }
key('a');run(800);assert.equal(mascot.dataset.state,'walking');assert.equal(window.lastPose[4],-1);
const mid=mascot.style.transform;
key('d');run(60);assert.equal(mascot.dataset.state,'walking');assert.equal(window.lastPose[4],1);
run(8000);assert.equal(mascot.dataset.state,'idle');assert.match(mascot.style.transform,/translate\(0px/);
key('a');run(8000);assert.equal(mascot.dataset.side,'left');assert.equal(mascot.dataset.state,'idle');
key('s');run(720);assert.equal(mascot.dataset.state,'hidden');key('w');run(1020);assert.equal(mascot.dataset.state,'idle');
// Master disable prevents scroll or W from bringing it back.
key('K',{metaKey:true,shiftKey:true});run(720);scroll(0);key('w');run(1100);assert.equal(mascot.dataset.state,'hidden');
key('K',{metaKey:true,shiftKey:true});run(1020);assert.equal(mascot.dataset.state,'idle');
reduced=true;key('d');step();assert.equal(mascot.dataset.state,'idle');assert.equal(mascot.dataset.side,'right');
mascot.click();run(160);assert.equal(mascot.dataset.state,'sidebar_guide');
assert.equal(frames.size,1);
console.log('PASS: hidden default, shortcut guards, walking direction/reversal/corners, W/S, master toggle, scroll gating, reduced motion and existing sidebar/greeting regressions');

// Config reaches the controller; invalid values retain the default interval.
for (const configured of [2500, undefined, 0, -100, NaN, Infinity]) {
    frames.clear();
    window.ForsaAiConfig = { mascotGreetingIntervalMs: configured };
    const configuredBubble = el();
    window.ForsaAi.MascotController.create({mascotEl:mascot, greetingEl:configuredBubble, sidebarEl:panel,
        onPrepareSidebar(){}, onActivateSidebar(){}});
    key('K',{ctrlKey:true,shiftKey:true});
    const expected = configured === 2500 ? 2500 : 10000;
    step(); run(expected - 20);
    assert.equal(configuredBubble.textContent, 'Halo, ada yang bisa saya bantu...?');
    step(); assert.notEqual(configuredBubble.textContent, 'Halo, ada yang bisa saya bantu...?');
    const first = configuredBubble.textContent;
    run(expected); assert.notEqual(configuredBubble.textContent, first);
}
console.log('PASS: configured interval through controller, fallback for missing/zero/negative/nonfinite values');

// Walk config controls real elapsed travel time, including fallback.
for (const configured of [2000, 4000, 0, -1, undefined]) {
    frames.clear(); reduced=false;
    window.ForsaAiConfig = {mascotWalkDurationMs:configured};
    const app = el();
    let panelOpen = false;
    panel.getBoundingClientRect = () => {
        let offset = panel.style.transform ? Number(panel.style.transform.match(/[-\d.]+/)[0]) : (panelOpen ? 0 : 100);
        if (panel.style.transform?.includes('%') || !panel.style.transform) offset *= 4.2;
        const left = panel.dataset.side === 'left' ? offset : 860+offset;
        return {width:420,left,right:left+420};
    };
    panel.style.transform='translateX(100%)';panel.dataset.side='right';
    const c=window.ForsaAi.MascotController.create({mascotEl:mascot,greetingEl:el(),sidebarEl:panel,appShellEl:app,
        onPrepareSidebar(){panelOpen=true;},onActivateSidebar(){}});
    key('K',{ctrlKey:true,shiftKey:true});step();run(1020);
    key('a');const duration=configured>0?configured:6000;
    run(duration-20);assert.equal(mascot.dataset.state,'walking');
    step();assert.equal(mascot.dataset.state,'idle');assert.equal(mascot.dataset.side,'left');
    mascot.click();assert.equal(panel.dataset.side,'left');
    run(660);assert.match(panel.style.transform,/translateX\(-/);
    run(760);assert.equal(app.style.marginLeft,'420px');assert.equal(app.style.marginRight,'0px');
    run(840);assert.equal(mascot.dataset.state,'sidebar_guide');
    // Expanded width / mobile replacement use the same layout sync.
    window.innerWidth=600;step();assert.equal(app.inert,true);assert.equal(app.style.visibility,'hidden');
    window.innerWidth=1280;panelOpen=false;c.notifySidebarClosed();
    key('d');run(duration);mascot.click();assert.equal(panel.dataset.side,'right');
    run(1420);assert.equal(app.style.marginRight,'420px');assert.equal(app.style.marginLeft,'0px');
}
console.log('PASS: walk duration config/fallback, left/right panel pull and content reservation, mobile replacement');
