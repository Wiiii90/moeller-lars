import test from 'node:test';
import assert from 'node:assert/strict';
import {
    FOLIO_VARIANTS, MENU_VARIANTS, FOLIO_PLANE_BASE,
    buildCornerFrames, buildMenuCornerFrames, buildShadowFrames, buildCornerImpulse,
    buildPageImpulse, animatePageHeight, visibleHeightTransition, capturePublicFolio,
    advancePublicFolio, animatePublicHeroChange,
} from '../../resources/js/public-folio-motion.js';

const CONTENT_SHADOW = 'rgba(255,255,255,.31) -5px 7px 0px 0px';
const MENU_SHADOW = 'rgba(65,70,65,.22) 0px -1px 0px 0px inset, rgba(255,255,255,.31) -7px 2px 0px 2px';

function fixture({ height = 540, menu = true, content = true } = {}) {
    const effects = [], animations = [], computed = new Map();
    const create = (name, shadow, topLeft, bottomLeft, topRight, bottomRight) => {
        const el = {
            name, style: { overflow: '' }, height,
            getBoundingClientRect() { return { height: this.height }; },
            getAnimations() { return []; },
            animate(frames, options) {
                const a = { name, frames, options, finished: new Promise(() => {}),
                    cancel() { a.cancelled = true; } };
                animations.push(a);
                return a;
            },
        };
        computed.set(el, {
            '': { boxShadow: shadow },
            '::before': { content: '""', display: 'block',
                top: topLeft, bottom: bottomLeft, left: name === 'menu' ? '-8.32px' : '-9.28px',
                width: name === 'menu' ? '8.32px' : '9.28px' },
            '::after': { content: '""', display: 'block',
                top: topRight, bottom: bottomRight, right: name === 'menu' ? '-7.04px' : '-7.68px',
                width: name === 'menu' ? '7.04px' : '7.68px' },
        });
        return el;
    };
    const paper = content ? create('content', CONTENT_SHADOW, '17.6px','64px','60.8px','18.4px') : null;
    const nav = menu ? create('menu',MENU_SHADOW,'0px','32%','0px','1px') : null;
    const body = content ? create('body','none','0px','0px','0px','0px') : null;
    const root = { querySelector: selector => ({
        '#content': paper, '.site-navigation': nav,
        '#content > .public-content-reveal': body,
    })[selector] ?? null };
    return { root, body, paper, nav, effects, animations, computed };
}

function browser(run, options = {}) {
    const scene = fixture(options);
    const saved = {};
    for (const key of ['document','getComputedStyle','KeyframeEffect','Animation']) saved[key] = globalThis[key];
    globalThis.document = { timeline: {}, getAnimations: () => [] };
    globalThis.getComputedStyle = (el,pseudo) => scene.computed.get(el)[pseudo || ''];
    globalThis.KeyframeEffect = class {
        constructor(el, frames, options) { Object.assign(this,{el,frames,options}); scene.effects.push(this); }
    };
    globalThis.Animation = class {
        constructor(effect,timeline) { this.effect=effect; this.finished=Promise.resolve(); }
        play() { this.playing=true; }
    };
    try { return run(scene); }
    finally { Object.entries(saved).forEach(([key,value]) => { globalThis[key]=value; }); }
}

function shadowLayers(value) {
    const chunks=[];let depth=0,start=0;
    for (let i=0;i<value.length;i++){
        if(value[i]==='(')depth++;
        else if(value[i]===')')depth--;
        else if(value[i]===','&&depth===0){chunks.push(value.slice(start,i));start=i+1;}
    }
    chunks.push(value.slice(start));
    return chunks;
}
function coords(value) {
    const chunks=shadowLayers(value);
    const outer=chunks.at(-1);
    return [...outer.matchAll(/(-?\d+(?:\.\d+)?)px/g)]
        .slice(0,2).map(m=>Number(m[1]));
}
function projected(value) {
    return shadowLayers(value).slice(-1).map(layer=>{
        const values=[...layer.matchAll(/(-?\d+(?:\.\d+)?)px/g)]
            .slice(0,4).map(m=>Number(m[1]));
        const [x,y]=values,spread=values.length>=3?values.at(-1):0;
        return {left:x-spread,right:x+spread,bottom:y+spread,top:y-spread};
    });
}

test('accepted original lines, bounded menu ends and visible paper baselines',()=>{
    assert.deepEqual(FOLIO_VARIANTS[0],['1.10rem','4rem','3.8rem','1.15rem']);
    assert.deepEqual(MENU_VARIANTS[0],['0rem','32%','0rem','1px']);
    assert.ok(MENU_VARIANTS.every(v=>v.every(p=>parseFloat(p)>=0)));
    assert.deepEqual(FOLIO_PLANE_BASE.menu,[-7,2]);
    assert.deepEqual(FOLIO_PLANE_BASE.content,[-5,7]);
});

test('ambient and navigation-impulse corners only move one axis per frame',()=>{
    const start={top:'18px',bottom:'64px',left:'-11.2px',width:'8.8px'};
    const edge={side:'left',offset:'-.58rem',width:'.58rem'};
    const sequences=[
        buildCornerFrames(start,'1.25rem','4.14rem',edge),
        buildCornerImpulse(start,start,edge,true),
        buildCornerImpulse(start,start,edge,false),
    ];
    for(const [index,frames] of sequences.entries()){
        assert.equal(frames[0].top,frames.at(-1).top);
        assert.equal(frames[0].bottom,frames.at(-1).bottom);
        if(index===0){
            assert.equal(frames[0].left,frames.at(-1).left);
            assert.equal(frames[0].width,frames.at(-1).width);
        } else {
            // A one-shot must finish on canonical CSS, not the old in-flight
            // offset. Comparing starting strings would hide the real snap.
            assert.equal(frames.at(-1).left,edge.offset);
            assert.equal(frames.at(-1).width,edge.width);
        }
        assert.equal(frames[0].offset,0);
        assert.equal(frames.at(-1).offset,1);
        for(let i=1;i<frames.length;i++){
            const a=frames[i-1],b=frames[i];
            const vertical=a.top!==b.top||a.bottom!==b.bottom;
            const horizontal=a.left!==b.left||a.width!==b.width;
            assert.ok(!(vertical&&horizontal),'not diagonal');
            assert.ok(b.offset>a.offset, 'all frame offsets must strictly increase');
        }
    }
});

test('rapid successive builds never accumulate corner offset or jump at completion',()=>{
    const base={top:'17.6px',bottom:'64px',left:'-11.2px',width:'8.8px'};
    const edge={side:'left',offset:'-.58rem',width:'.58rem'};
    // Equivalent to a click in the middle of a prior build, with its corner
    // already pushed out and shortened.
    const active={top:'24px',bottom:'70px',left:'-15px',width:'12.6px'};
    for (const growing of [true,false]) {
        const frames=buildCornerImpulse(active,base,edge,growing);
        assert.equal(frames[0].left,active.left);
        assert.deepEqual([frames.at(-1).top,frames.at(-1).bottom,
            frames.at(-1).left,frames.at(-1).width],
            [base.top,base.bottom,edge.offset,edge.width]);
        const out=frames.find(f=>f.left.includes(' - 3.1px)'));
        assert.equal(out.left,'calc(-.58rem - 3.1px)');
        assert.equal(out.width,'calc(.58rem + 3.1px)');
        assert.ok(frames.every(f=>!f.left.includes('-15px - 3.1px')),
            'a second click cannot add distance to an already displaced line');
        assert.ok(frames.some(f=>f.top==='calc(17.6px + 40.00px)'
            || f.top==='calc(17.6px + 32.00px)'),
            'the vertical contour visibly contracts at the top');
        assert.ok(frames.some(f=>f.bottom==='calc(64px + 46.00px)'
            || f.bottom==='calc(64px + 38.00px)'),
            'the lower endpoint visibly contracts');
    }
});

test('short pages keep at least 40px of visible contour during a build',()=>{
    const base={top:'17px',bottom:'60px',left:'-9.28px',width:'9.28px'};
    const edge={side:'left',offset:'-.58rem',width:'.58rem'};
    const frames=buildCornerImpulse(base,base,edge,true,160);
    const cutTop=parseFloat(frames[2].top.split('+')[1]);
    const cutBottom=parseFloat(frames[3].bottom.split('+')[1]);
    assert.ok(cutTop+cutBottom<=160-17-60-40+.01);
    assert.ok(cutTop>0&&cutBottom>0);
});

test('menu lines never touch bottom, can reverse early, and left always ends higher',()=>{
    const configurations=[
        {side:'left',width:'8.32px',distance:'-8.32px',baseline:'32%',high:'73%',middle:'42%'},
        {side:'right',width:'7.04px',distance:'-7.04px',baseline:'1px',high:'26%',middle:'6%'},
    ];
    const samples=configurations.map((conf,index)=>{
        const start={top:'0px',bottom:conf.baseline,width:conf.width,[conf.side]:conf.distance};
        const frames=buildMenuCornerFrames(start,index);
        assert.equal(frames.length,10);
        assert.deepEqual(frames.map(f=>f.offset),[0,.08,.29,.37,.54,.61,.80,.86,.97,1]);
        assert.ok(frames.every(f=>f.top==='0px'), 'always anchored to upper edge');
        assert.ok(frames.every(f=>f[conf.side]===conf.distance&&f.width===conf.width));
        assert.ok(frames.every(f=>parseFloat(f.bottom)>0), 'never touches lower edge');
        assert.equal(frames[0].bottom,frames.at(-1).bottom);
        assert.deepEqual(frames.map(f=>f.bottom),[
            conf.baseline,conf.baseline,
            index===0?'60%':'17%', index===0?'60%':'17%',
            conf.middle,conf.middle,conf.high,conf.high,conf.baseline,conf.baseline,
        ]);
        return frames;
    });
    const pixels=(value,height=60)=>value.endsWith('%')
        ? parseFloat(value)*height/100 : parseFloat(value);
    // Since cycles are asynchronous, compare EVERY possible phase pair.
    for (const left of samples[0]){
        for (const right of samples[1]){
            assert.ok(pixels(left.bottom)>pixels(right.bottom),
                'the left stroke must always be shorter than right');
        }
    }
});

test('exactly one pale outer paper is used even during animation',()=>{
    for(const type of ['content','menu']){
        const original=type==='menu'?MENU_SHADOW:CONTENT_SHADOW;
        const cases=[buildShadowFrames(original,type),
            buildPageImpulse(original,original,type,true),
            buildPageImpulse(original,original,type,false)];
        for(const frames of cases)for(const frame of frames){
            const layers=shadowLayers(frame.boxShadow);
            assert.equal(layers.length,type==='menu'?2:1,
                'one outer sheet, with optional inset rule');
            const exterior=layers.at(-1);
            assert.ok(!/rgba\(28,\s*35,\s*32/.test(exterior));
            assert.ok(/rgba\(255,\s*255,\s*255/.test(exterior)
                ||exterior.includes('var(--public-folio-soft)'));
            const [sheet]=projected(frame.boxShadow);
            assert.ok(sheet.left<-.3,'one paper backing must show on left');
            assert.ok(sheet.bottom>.3,'one paper backing must show below');
            if(type==='menu') {
                assert.ok(Math.abs(sheet.top)<.001,
                    'the menu backing remains flush with the top edge');
                assert.ok(sheet.right<=-5+.001,
                    'menu paper keeps at least 5px inset on the right');
            }
        }
    }
});

test('menu backing stays between the white left contour and its 5px right inset',()=>{
    const frames=buildShadowFrames(MENU_SHADOW,'menu');
    assert.ok(frames.some(frame=>coords(frame.boxShadow)[1]<2),
        'menu backing retains its vertical ambient movement');
    assert.ok(frames.some(frame=>coords(frame.boxShadow)[0]!==-7),
        'menu backing retains its gentle horizontal ambient movement');
    for(const frame of frames){
        const [sheet]=projected(frame.boxShadow);
        assert.ok(Math.abs(sheet.top)<.001,'the menu backing stays flush with the top');
        assert.ok(sheet.right<=-5+.001,'the right inset must remain at least 5px');
        assert.ok(sheet.left>=-9-.001,'the backing must not cover the white left stroke');
    }
});

test('menu never receives a separate page-build backing impulse',()=>{
    for(const growing of [true,false]){
        const frames=buildPageImpulse(MENU_SHADOW,MENU_SHADOW,'menu',growing);
        assert.ok(frames.every(frame=>frame.boxShadow===MENU_SHADOW));
    }
});

test('ambient Content lines take one vertical excursion per endpoint and return smoothly',()=>{
    for(const side of ['left','right']){
        const start={top:'18px',bottom:'64px',width:'9px',[side]:'-9px'};
        const edge={side,offset:'-.58rem',width:'.58rem'};
        const frames=buildCornerFrames(start,'1.53rem','4.30rem',edge);
        assert.equal(frames.length,11);
        assert.deepEqual(frames.map(f=>f.offset),
            [0,.12,.26,.36,.49,.62,.70,.81,.91,.98,1]);
        assert.equal(frames[0].top,frames.at(-1).top);
        assert.equal(frames[0].bottom,frames.at(-1).bottom);
        const vertical=frames.slice(1).filter((f,i)=>
            f.top!==frames[i].top||f.bottom!==frames[i].bottom);
        assert.equal(vertical.length,4,
            'one top and bottom extension, followed by one return each');
        assert.ok(frames.every((f,i)=>i===0||f.offset>frames[i-1].offset));
    }
});

test('ambient and page-build backing motion stays within a visible one-sheet corridor',()=>{
    for(const type of ['content','menu']){
        const original=type==='menu'?MENU_SHADOW:CONTENT_SHADOW;
        for(const frames of [
            buildShadowFrames(original,type),
            buildPageImpulse(original,original,type,true),
            buildPageImpulse(original,original,type,false),
        ]) {
            assert.equal(frames[0].boxShadow,original);
            assert.equal(frames.at(-1).boxShadow,original);
            for(let i=1;i<frames.length;i++){
                const previous=coords(frames[i-1].boxShadow);
                const next=coords(frames[i].boxShadow);
                assert.ok(next.filter((x,j)=>x!==previous[j]).length<=1,
                    'no diagonal sheet movement');
            }
        }
    }
});

test('single-sheet page-build impulse is stronger than calm ambient travel',()=>{
    const growing=buildPageImpulse(CONTENT_SHADOW,CONTENT_SHADOW,'content',true);
    assert.ok(Math.min(...growing.map(f=>coords(f.boxShadow)[0]))<-8);
    const shrinking=buildPageImpulse(CONTENT_SHADOW,CONTENT_SHADOW,'content',false);
    assert.ok(Math.max(...shrinking.map(f=>coords(f.boxShadow)[0]))>-4);
});

test('long pages skip invisible heights while short pages animate their visible extent',()=>{
    assert.deepEqual(visibleHeightTransition(9000,280,800,180), {from:668,to:280});
    assert.deepEqual(visibleHeightTransition(280,9000,800,180), {from:280,to:668});
    assert.deepEqual(visibleHeightTransition(9000,7000,800,180), {from:668,to:668});
    assert.deepEqual(visibleHeightTransition(340,600,800,180), {from:340,to:600});
    assert.deepEqual(visibleHeightTransition(8000,250,800,-300), {from:848,to:250});
    assert.deepEqual(visibleHeightTransition(8000,250,800,1400), {from:96,to:96});
    // Non-browser/test consumers retain their natural dimensions.
    assert.deepEqual(visibleHeightTransition(900,300,null), {from:900,to:300});
});

test('rapid Hero click with no remaining visible height change releases old clipping',()=>{
    const previous=globalThis.window;
    globalThis.window={innerHeight:800};
    try {
        browser(({root,animations,body})=>{
            const first=animatePageHeight(320,root);
            assert.ok(first);
            assert.equal(body.style.overflow,'clip');
            body.height=910;
            const skipped=animatePageHeight(900,root);
            assert.equal(skipped,null);
            assert.equal(first.cancelled,true);
            assert.equal(body.style.overflow,'');
            assert.equal(animations.filter(a=>a.name==='body').length,1);
        },{height:900});
    } finally {globalThis.window=previous;}
});

test('height animation itself uses the viewport cap, not the old gallery height',()=>{
    const previous=globalThis.window;
    globalThis.window={innerHeight:800};
    try {
        browser(({root,animations,body})=>{
            const animation=animatePageHeight(9000,root);
            assert.ok(animation);
            assert.deepEqual(animation.frames,[{height:'848px'},{height:'280px'}]);
            assert.equal(animation.options.duration,780);
            assert.equal(body.style.overflow,'clip');
            assert.equal(animations.filter(a=>a.name==='body').length,1);
        },{height:280});
    } finally {globalThis.window=previous;}
});

test('every public page uses the same height transition and clips ONLY the inner body',()=>{
    browser(({root,body,animations})=>{
        const transition=animatePageHeight(940,root);
        assert.ok(transition);
        assert.equal(body.style.overflow,'clip');
        assert.deepEqual(transition.frames,[{height:'940px'},{height:'440px'}]);
        assert.equal(transition.options.duration,780);
        assert.equal(animations.filter(x=>x.name==='body').length,1);
        assert.equal(animatePageHeight(444,root),null);
    },{height:440});
});

test('initial document keeps slow breathing on content and menu',()=>{
    browser(({root,effects,animations})=>{
        assert.equal(advancePublicFolio(root),true);
        assert.equal(effects.length,4);
        assert.ok(effects.every(e=>e.options.iterations===Infinity));
        assert.deepEqual(effects.map(e=>e.options.duration),[25100,29700,34200,38800]);
        assert.equal(animations.filter(a=>a.options.iterations===Infinity).length,2);
    });
});

test('SPA swaps for any page type trigger a coordinated pulse and height build',()=>{
    browser(({root})=>assert.equal(capturePublicFolio(root),true),{height:780});
    browser(({root,effects,animations,body})=>{
        assert.equal(advancePublicFolio(root),true);
        assert.equal(effects.length,4);
        assert.deepEqual(effects.map(e=>e.options.iterations),[1,1,Infinity,Infinity]);
        assert.equal(animations.filter(a=>a.name==='content').length,1);
        assert.equal(animations.filter(a=>a.name==='menu').length,1);
        assert.equal(animations.find(a=>a.name==='menu').options.iterations,Infinity);
        const reveal=animations.find(a=>a.name==='body');
        assert.ok(reveal);
        assert.deepEqual(reveal.frames,[{height:'780px'},{height:'420px'}]);
        assert.equal(body.style.overflow,'clip');
        assert.equal(animations.find(a=>a.name==='content').options.duration,1240);
    },{height:420});
});

test('same-height public page swaps still start the machine impulse',()=>{
    browser(({root})=>capturePublicFolio(root),{height:500});
    browser(({root,animations,effects})=>{
        advancePublicFolio(root);
        assert.equal(animations.some(a=>a.name==='body'),false);
        assert.equal(effects.length,4);
        assert.deepEqual(effects.map(e=>e.options.iterations),[1,1,Infinity,Infinity]);
        assert.ok(animations.some(a=>a.name==='content'&&a.options.duration===1240));
    },{height:500});
});

test('missing public DOM does not add overlays or animations',()=>{
    browser(({root,effects,animations})=>{
        assert.equal(advancePublicFolio(root),false);
        assert.equal(effects.length,0);
        assert.equal(animations.length,0);
    },{content:false,menu:false});
});

test('Home Hero in-place slide changes pulse ONLY content and reuse page-height animation', () => {
    browser(({root,body,paper,nav,effects,animations})=>{
        let changed=0;
        const done=animatePublicHeroChange(()=>{
            changed++;
            body.height=840;
        },root);
        assert.equal(done,true);
        assert.equal(changed,1);
        assert.equal(effects.length,2);
        assert.ok(effects.every(effect=>effect.el===paper&&effect.options.iterations===1));
        assert.equal(animations.filter(animation=>animation.name==='content').length,1);
        assert.equal(animations.filter(animation=>animation.name==='menu').length,0);
        const height=animations.find(animation=>animation.name==='body');
        assert.deepEqual(height.frames,[{height:'540px'},{height:'840px'}]);
        assert.equal(body.style.overflow,'clip');
        assert.equal(nav.name,'menu');
    });
});

test('Home Hero change cancels only old content animation and leaves menu breathing intact', () => {
    browser(({root,body,paper,nav,animations})=>{
        const oldContent={effect:{target:paper},cancel(){this.cancelled=true}};
        const oldMenu={effect:{target:nav},cancel(){this.cancelled=true}};
        globalThis.document.getAnimations=()=>[oldContent,oldMenu];
        let called=0;
        assert.equal(animatePublicHeroChange(()=>{called++;body.height=710;},root),true);
        assert.equal(called,1);
        assert.equal(oldContent.cancelled,true);
        assert.equal(oldMenu.cancelled,undefined);
        assert.equal(animations.filter(animation=>animation.name==='menu').length,0);
    });
});
