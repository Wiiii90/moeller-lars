import test from 'node:test';
import assert from 'node:assert/strict';
import {
    FOLIO_PLANE_BASE,
    buildCornerFrames, buildMenuCornerFrames, buildMenuBackingFrames, buildShadowFrames, buildCornerImpulse,
    buildPageImpulse, animatePageHeight, animatePageWidth, visibleHeightTransition, capturePublicFolio,
    advancePublicFolio, animatePublicHeroChange,
} from '../../resources/js/public-folio-motion.js';

const CONTENT_SHADOW = 'rgba(255,255,255,.20) -3px 4px 0px 0px';
const MENU_SHADOW = 'rgba(65,70,65,.22) 0px -1px 0px 0px inset';

function fixture({ height = 540, menu = true, content = true } = {}) {
    const effects = [], animations = [], nativeAnimations = [], computed = new Map();
    const create = (name, shadow, topLeft, bottomLeft, topRight, bottomRight) => {
        const el = {
            name, style: { overflow: '' }, height,
            classList: { contains: () => false },
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
                top: topLeft, bottom: bottomLeft, left: name === 'menu' ? '-6px' : '-7px',
                width: name === 'menu' ? '6px' : '7px' },
            '::after': { content: '""', display: 'block',
                top: topRight, bottom: bottomRight, right: name === 'menu' ? '-5.5px' : '-6px',
                width: name === 'menu' ? '5.5px' : '6px' },
        });
        return el;
    };
    const paper = content ? create('content', CONTENT_SHADOW, '20px','55px','55px','18px') : null;
    const nav = menu ? create('menu',MENU_SHADOW,'0px','40px','0px','8px') : null;
    const body = content ? create('body','none','0px','0px','0px','0px') : null;
    const root = { querySelector: selector => ({
        '#content': paper, '.site-navigation': nav,
        '#content > .public-content-reveal': body,
    })[selector] ?? null };
    return { root, body, paper, nav, effects, animations, nativeAnimations, computed };
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
        constructor(effect,timeline) {
            this.effect=effect; this.finished=Promise.resolve();
            scene.nativeAnimations.push(this);
        }
        play() { this.playing=true; }
        cancel() { this.cancelled=true; }
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

const numeric = value => {
    const numbers = [...String(value).matchAll(/(-?\d+(?:\.\d+)?)px/g)].map(m=>Number(m[1]));
    return numbers.length===2 ? numbers[0]+numbers[1] : numbers[0];
};

test('same folio motion owner expands and contracts real page width without touching the menu', () => {
    browser(({root, paper, animations}) => {
        paper.getBoundingClientRect = () => ({height: 500, width: 800});
        assert.equal(animatePageWidth(650, root)?.options.duration, 480);
        assert.deepEqual(animations.find(a => a.name === 'content' && a.frames[0].width)?.frames,
            [{width: '650px'}, {width: '800px'}]);
        assert.deepEqual(animations.find(a => a.name === 'body')?.frames,
            [{overflow:'clip'}, {overflow:'clip'}]);
        assert.equal(animations.find(a => a.name === 'body')?.options.duration, 480);
        paper.getBoundingClientRect = () => ({height: 500, width: 650});
        assert.equal(animatePageWidth(800, root)?.options.duration, 480);
        assert.equal(animations.filter(a => a.name === 'content' && a.frames[0].width).length, 2);
        assert.equal(animations[0].cancelled, true);
        assert.equal(animations[1].cancelled, true);
        assert.equal(animations.some(a => a.name === 'menu'), false);
    });
});

test('a real SPA width change unfolds both sides with the vertical reveal under one owner', () => {
    browser(({root, paper}) => {
        paper.getBoundingClientRect = () => ({height: 500, width: 800});
        capturePublicFolio(root);
    }, {height: 500});
    browser(({root, paper, animations, body}) => {
        paper.classList.contains = name => name === 'site-content--landscape-artwork';
        paper.getBoundingClientRect = () => ({height: 600, width: 960});
        advancePublicFolio(root);
        const width = animations.find(a => a.name === 'content' && a.frames[0].width);
        assert.deepEqual(width.frames, [{width:'800px'}, {width:'960px'}]);
        assert.equal(width.options.duration, 480);
        const clips = animations.filter(a => a.name === 'body');
        assert.equal(clips.length, 2);
        assert.deepEqual(clips.map(a => a.options.duration), [480, 480]);
        assert.ok(clips.every(a => a.frames.every(frame => frame.overflow === 'clip')));
        assert.equal(body.style.overflow, '');
    }, {height: 600});
});

test('ordinary public routes never animate shell width even when dimensions differ', () => {
    browser(({root, paper}) => {
        paper.getBoundingClientRect = () => ({height: 500, width: 800});
        capturePublicFolio(root);
    }, {height: 500});
    browser(({root, paper, animations}) => {
        paper.getBoundingClientRect = () => ({height: 600, width: 1000});
        advancePublicFolio(root);
        assert.equal(animations.filter(a => a.name === 'content' && a.frames[0]?.width).length, 0);
        assert.equal(animations.some(a => a.name === 'body' && a.frames[0]?.overflow === 'clip'), true);
    }, {height: 600});
});

test('leaving expanded Artwork restores General width with a closing animation', () => {
    browser(({root, paper}) => {
        paper.classList.contains = name => name === 'site-content--landscape-artwork';
        paper.getBoundingClientRect = () => ({height: 600, width: 1000});
        capturePublicFolio(root);
    }, {height: 600});
    browser(({root, paper, animations}) => {
        paper.getBoundingClientRect = () => ({height: 500, width: 800});
        advancePublicFolio(root);
        assert.deepEqual(animations.find(a => a.name === 'content' && a.frames[0]?.width)?.frames,
            [{width:'1000px'}, {width:'800px'}]);
    }, {height: 500});
});

test('width transition yields to unchanged geometry and respects reduced motion', () => {
    browser(({root, paper}) => {
        paper.getBoundingClientRect = () => ({height: 400, width: 650});
        assert.equal(animatePageWidth(650, root), null);
        assert.equal(animatePageWidth(649, root), null);
        assert.equal(animatePageWidth(null, root), null);
    });
    const oldWindow = globalThis.window;
    globalThis.window = { matchMedia: () => ({ matches: true }) };
    try {
        browser(({root, paper}) => {
            paper.getBoundingClientRect = () => ({height: 400, width: 900});
            assert.equal(animatePageWidth(650, root), null);
        });
    } finally {
        globalThis.window = oldWindow;
    }
});

test('Content and Menu share softer, closer paper baselines',()=>{
    assert.deepEqual(FOLIO_PLANE_BASE.content,[-3,4]);
    assert.deepEqual(FOLIO_PLANE_BASE.menu,FOLIO_PLANE_BASE.content);
});

test('ambient Content lines move only a couple of pixels with long rests',()=>{
    for(const base of [
        {top:'18px',bottom:'55px',left:'-7px',width:'7px'},
        {top:'47px',bottom:'16px',right:'-6px',width:'6px'},
    ]){
        const f=buildCornerFrames(base);
        assert.deepEqual(f.map(s=>s.offset),[0,.25,.31,.37,.60,.66,.72,.95,1]);
        assert.equal(f[0].top,base.top);
        assert.equal(f.at(-1).top,base.top);
        assert.equal(f.at(-1).bottom,base.bottom);
        assert.ok(f.every(s=>s.left===base.left&&s.right===base.right&&s.width===base.width));
        assert.ok(f.every(s=>Math.abs(numeric(s.top)-numeric(base.top))<=1.5));
        assert.ok(f.every(s=>Math.abs(numeric(s.bottom)-numeric(base.bottom))<=2));
        assert.ok(f[7].top===base.top&&f[7].bottom===base.bottom);
    }
});

test('Content page builds are short, subtle and cannot accumulate horizontal offset',()=>{
    const edge={side:'left',offset:'-7px',width:'7px'};
    const base={top:'20px',bottom:'55px',left:'-7px',width:'7px'};
    const active={top:'26px',bottom:'65px',left:'-13px',width:'13px'};
    for(const growing of [true,false]){
        const f=buildCornerImpulse(active,base,edge,growing);
        assert.equal(f[0].left,active.left);
        assert.deepEqual([f.at(-1).top,f.at(-1).bottom,f.at(-1).left,f.at(-1).width],
            [base.top,base.bottom,edge.offset,edge.width]);
        assert.equal(f[1].left,'calc(-7px - .7px)');
        assert.equal(f[1].width,'calc(7px + .7px)');
        assert.ok(f.every(s=>!s.left.includes('-13px - ')));
        assert.ok(parseFloat(f[2].top.split('+')[1])<=6);
        assert.ok(parseFloat(f[3].bottom.split('+')[1])<=8);
    }
});

test('short-page contour contraction retains at least 40px of visible line',()=>{
    const base={top:'17px',bottom:'60px',left:'-7px',width:'7px'};
    const f=buildCornerImpulse(base,base,{side:'left',offset:'-7px',width:'7px'},true,120);
    const a=parseFloat(f[2].top.split('+')[1]),b=parseFloat(f[3].bottom.split('+')[1]);
    assert.ok(a+b<=120-17-60-40+.01);
});

test('Menu corner outlines are top-connected, calm and always left-shorter',()=>{
    const pair=[
        buildMenuCornerFrames({top:'0px',bottom:'40px',left:'-6px',width:'6px'},0),
        buildMenuCornerFrames({top:'0px',bottom:'7px',right:'-5.5px',width:'5.5px'},1),
    ];
    for(const f of pair){
        assert.ok(f.every(s=>s.top==='0px'));
        assert.ok(f.every(s=>numeric(s.bottom)>0));
        assert.equal(f[0].bottom,f.at(-1).bottom);
        assert.ok(f.every(s=>s.offset>=0&&s.offset<=1));
    }
    for(const l of pair[0])for(const r of pair[1])
        assert.ok(numeric(l.bottom)>numeric(r.bottom)+20);
});

test('ambient Content shadow uses just two subpixel motions and long holds',()=>{
    const f=buildShadowFrames(CONTENT_SHADOW);
    assert.ok(f.every(s=>shadowLayers(s.boxShadow).length===1));
    assert.equal(f.at(-1).boxShadow,CONTENT_SHADOW);
    for(const s of f){
        const [x,y]=coords(s.boxShadow);
        assert.ok(x>=-3.4&&x<=-3);
        assert.ok(y>=4&&y<=4.5);
    }
});

test('Menu backing keeps the same base and only a fractional-pixel excursion',()=>{
    const f=buildMenuBackingFrames();
    assert.equal(f[0]['--public-menu-sheet-x'],'-3px');
    assert.equal(f[0]['--public-menu-sheet-y'],'4px');
    assert.equal(f.at(-1)['--public-menu-sheet-x'],'-3px');
    assert.equal(f.at(-1)['--public-menu-sheet-y'],'4px');
    assert.ok(f.every(s=>{
        const x=parseFloat(s['--public-menu-sheet-x']),y=parseFloat(s['--public-menu-sheet-y']);
        return x>=-3.35&&x<=-3&&y>=4&&y<=4.45;
    }));
});

test('Page-build shadow pulses are much smaller than the old folding motion',()=>{
    for(const growing of [true,false]){
        const f=buildPageImpulse(CONTENT_SHADOW,CONTENT_SHADOW,growing);
        assert.equal(f[0].boxShadow,CONTENT_SHADOW);
        assert.equal(f.at(-1).boxShadow,CONTENT_SHADOW);
        for(const s of f){
            const [x,y]=coords(s.boxShadow);
            assert.ok(x>=-3.65&&x<=-2.65);
            assert.ok(y>=3.65&&y<=4.8);
        }
    }
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
            assert.ok(first.frames.every(frame=>frame.overflow==='clip'));
            assert.equal(body.style.overflow,'');
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
            assert.deepEqual(animation.frames,[{height:'848px',overflow:'clip'},{height:'280px',overflow:'clip'}]);
            assert.equal(animation.options.duration,480);
            assert.equal(body.style.overflow,'');
            assert.equal(animations.filter(a=>a.name==='body').length,1);
        },{height:280});
    } finally {globalThis.window=previous;}
});

test('every public page uses the same height transition and clips ONLY the inner body',()=>{
    browser(({root,body,animations})=>{
        const transition=animatePageHeight(940,root);
        assert.ok(transition);
        assert.equal(body.style.overflow,'');
        assert.deepEqual(transition.frames,[{height:'940px',overflow:'clip'},{height:'440px',overflow:'clip'}]);
        assert.equal(transition.options.duration,480);
        assert.equal(animations.filter(x=>x.name==='body').length,1);
        assert.equal(animatePageHeight(444,root),null);
    },{height:440});
});

test('initial document keeps slow breathing on content and menu',()=>{
    browser(({root,effects,animations})=>{
        assert.equal(advancePublicFolio(root),true);
        assert.equal(effects.length,4);
        assert.ok(effects.every(e=>e.options.iterations===Infinity));
        assert.deepEqual(effects.map(e=>e.options.duration),[98000,113000,117000,133000]);
        assert.equal(animations.filter(a=>a.options.iterations===Infinity).length,2);
    });
});

test('outgoing SPA pages retire all infinite folio animations before DOM replacement', () => {
    browser(({root, animations, nativeAnimations}) => {
        advancePublicFolio(root);
        assert.equal(nativeAnimations.length, 4);
        assert.equal(animations.filter(a => a.options.iterations === Infinity).length, 2);
        capturePublicFolio(root);
        assert.ok(nativeAnimations.every(a => a.cancelled));
        assert.ok(animations.filter(a => a.options.iterations === Infinity).every(a => a.cancelled));
    });
});

test('restarting ambient on the same element never stacks infinite animations', () => {
    browser(({root, animations, nativeAnimations}) => {
        advancePublicFolio(root);
        const firstNative = [...nativeAnimations];
        const firstWeb = animations.filter(a => a.options.iterations === Infinity);
        advancePublicFolio(root);
        assert.ok(firstNative.every(a => a.cancelled));
        assert.ok(firstWeb.every(a => a.cancelled));
        assert.equal(nativeAnimations.filter(a => !a.cancelled).length, 4);
        assert.equal(animations.filter(a => a.options.iterations === Infinity && !a.cancelled).length, 2);
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
        assert.deepEqual(reveal.frames,[{height:'780px',overflow:'clip'},{height:'420px',overflow:'clip'}]);
        assert.equal(body.style.overflow,'');
        assert.equal(animations.find(a=>a.name==='content').options.duration,620);
    },{height:420});
});

test('same-height public page swaps still start the machine impulse',()=>{
    browser(({root})=>capturePublicFolio(root),{height:500});
    browser(({root,animations,effects})=>{
        advancePublicFolio(root);
        assert.equal(animations.some(a=>a.name==='body'),false);
        assert.equal(effects.length,4);
        assert.deepEqual(effects.map(e=>e.options.iterations),[1,1,Infinity,Infinity]);
        assert.ok(animations.some(a=>a.name==='content'&&a.options.duration===620));
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
        assert.deepEqual(height.frames,[{height:'540px',overflow:'clip'},{height:'840px',overflow:'clip'}]);
        assert.equal(body.style.overflow,'');
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
