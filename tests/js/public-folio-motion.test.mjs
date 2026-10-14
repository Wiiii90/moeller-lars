import test from 'node:test';
import assert from 'node:assert/strict';
import {
    FOLIO_VARIANTS, MENU_VARIANTS, FOLIO_PLANE_BASE,
    buildCornerFrames, buildMenuCornerFrames, buildShadowFrames, buildCornerImpulse,
    buildPageImpulse, animatePageHeight, capturePublicFolio,
    advancePublicFolio, animatePublicHeroChange,
} from '../../resources/js/public-folio-motion.js';

const CONTENT_SHADOW = 'rgba(28,35,32,.09) -2px 3px 0px 0px, rgba(255,255,255,.31) -5px 7px 0px 0px, rgba(28,35,32,.12) -8px 10px 0px 0px';
const MENU_SHADOW = 'rgba(65,70,65,.22) 0px -1px 0px 0px inset, rgba(28,35,32,.12) -1px 1px 0px 1px, rgba(255,255,255,.31) -3px 2px 0px 1px';

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
                top: topLeft, bottom: bottomLeft, left: name === 'menu' ? '-4.96px' : '-11.2px',
                width: name === 'menu' ? '8.8px' : '8.8px' },
            '::after': { content: '""', display: 'block',
                top: topRight, bottom: bottomRight, right: name === 'menu' ? '-5.12px' : '-8.8px',
                width: name === 'menu' ? '8.96px' : '8.8px' },
        });
        return el;
    };
    const paper = content ? create('content', CONTENT_SHADOW, '17.6px','64px','60.8px','18.4px') : null;
    const nav = menu ? create('menu',MENU_SHADOW,'0px','0px','0px','0px') : null;
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

function coords(value) {
    const chunks=[];let depth=0,start=0;
    for(let i=0;i<value.length;i++){
        if(value[i]==='(')depth++;
        else if(value[i]===')')depth--;
        else if(value[i]===','&&depth===0){chunks.push(value.slice(start,i));start=i+1;}
    }
    chunks.push(value.slice(start));
    assert.equal(chunks.length,3);
    return chunks.slice(1).flatMap(v => [...v.matchAll(/(-?\d+(?:\.\d+)?)px/g)]
        .slice(0,2).map(m=>Number(m[1])));
}

test('accepted original lines, bounded menu ends and visible paper baselines',()=>{
    assert.deepEqual(FOLIO_VARIANTS[0],['1.10rem','4rem','3.8rem','1.15rem']);
    assert.deepEqual(MENU_VARIANTS[0],['0rem','0rem','0rem','0rem']);
    assert.ok(MENU_VARIANTS.every(v=>v.every(p=>parseFloat(p)>=0)));
    assert.deepEqual(FOLIO_PLANE_BASE.menu,[-1,1,-3,2]);
    assert.deepEqual(FOLIO_PLANE_BASE.content,[-5,7,-8,10]);
});

test('ambient and navigation-impulse corners only move one axis per frame',()=>{
    const start={top:'18px',bottom:'64px',left:'-11.2px',width:'8.8px'};
    const edge={side:'left',offset:'-.7rem',width:'.55rem'};
    const sequences=[
        buildCornerFrames(start,{top:'1.1rem',bottom:'4rem'},'1.25rem','4.14rem',edge),
        buildCornerImpulse(start,edge,true),
        buildCornerImpulse(start,edge,false),
    ];
    for(const frames of sequences){
        assert.deepEqual([frames[0].top,frames[0].bottom,frames[0].left,frames[0].width],
            [frames.at(-1).top,frames.at(-1).bottom,frames.at(-1).left,frames.at(-1).width]);
        for(let i=1;i<frames.length;i++){
            const a=frames[i-1],b=frames[i];
            const vertical=a.top!==b.top||a.bottom!==b.bottom;
            const horizontal=a.left!==b.left||a.width!==b.width;
            assert.ok(!(vertical&&horizontal),'not diagonal');
        }
    }
});

test('menu corners remain attached to the TOP edge and move only their lower joint',()=>{
    for (const [index,side,value,width] of [
        [0,'left','-5.6px','5.6px'], [1,'right','-5.76px','5.76px'],
    ]) {
        const frames=buildMenuCornerFrames({
            top:'0px',bottom:'0px',[side]:value,width,
        },index);
        assert.ok(frames.every(frame=>frame.top==='0px'));
        assert.ok(frames.every(frame=>frame.width===width&&frame[side]===value));
        assert.ok(frames.every(frame=>parseFloat(frame.bottom)>=0));
        assert.ok(frames.some(frame=>parseFloat(frame.bottom)>0));
        assert.equal(frames[0].bottom,frames.at(-1).bottom);
    }
});

test('ambient paper, growth and contraction preserve both exposed rear layers',()=>{
    for(const type of ['content','menu']){
        const start=type==='content'?CONTENT_SHADOW:MENU_SHADOW;
        const sequences=[
            buildShadowFrames(start,type),
            buildPageImpulse(start,start,type,true),
            buildPageImpulse(start,start,type,false),
        ];
        for(const frames of sequences){
            assert.equal(frames[0].boxShadow,start);
            assert.equal(frames.at(-1).boxShadow,start);
            for(const frame of frames){
                const [middleX,middleY,rearX,rearY]=coords(frame.boxShadow);
                assert.ok(rearX<=middleX-1.9, 'rear plane must show on left');
                assert.ok(rearY>=middleY+.7, 'rear plane must show below');
                assert.ok(middleX<0, 'middle plane must show beyond white');
                assert.ok(middleY>=.5, 'middle plane must show at bottom');
            }
        }
    }
});

test('navigation impulses are visibly stronger than ambient excursions',()=>{
    const growing=buildPageImpulse(CONTENT_SHADOW,CONTENT_SHADOW,'content',true);
    assert.ok(Math.min(...growing.map(f=>coords(f.boxShadow)[2]))<-12);
    const shrinking=buildPageImpulse(CONTENT_SHADOW,CONTENT_SHADOW,'content',false);
    assert.ok(Math.max(...shrinking.map(f=>coords(f.boxShadow)[0]))>-4);
});

test('every public page uses the same height transition and clips ONLY the inner body',()=>{
    browser(({root,body,animations})=>{
        const transition=animatePageHeight(940,root);
        assert.ok(transition);
        assert.equal(body.style.overflow,'clip');
        assert.deepEqual(transition.frames,[{height:'940px'},{height:'440px'}]);
        assert.equal(transition.options.duration,980);
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
        assert.equal(animations.find(a=>a.name==='content').options.duration,1480);
    },{height:420});
});

test('same-height public page swaps still start the machine impulse',()=>{
    browser(({root})=>capturePublicFolio(root),{height:500});
    browser(({root,animations,effects})=>{
        advancePublicFolio(root);
        assert.equal(animations.some(a=>a.name==='body'),false);
        assert.equal(effects.length,4);
        assert.deepEqual(effects.map(e=>e.options.iterations),[1,1,Infinity,Infinity]);
        assert.ok(animations.some(a=>a.name==='content'&&a.options.duration===1480));
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
