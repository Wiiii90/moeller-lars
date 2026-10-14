import test from 'node:test';
import assert from 'node:assert/strict';
import { adjacentHeroIndex, initializeHomeHero } from '../../resources/js/home-hero.js';

test('Hero artwork navigation wraps within the resolved group in both directions', () => {
    assert.equal(adjacentHeroIndex(0, 1, 3), 1);
    assert.equal(adjacentHeroIndex(2, 1, 3), 0);
    assert.equal(adjacentHeroIndex(0, -1, 3), 2);
    assert.equal(adjacentHeroIndex(1, -1, 3), 0);
    assert.equal(adjacentHeroIndex(0, -1, 2), 1);
});

test('Hero navigation rejects invalid group indices and directions', () => {
    assert.throws(() => adjacentHeroIndex(0, 1, 0), RangeError);
    assert.throws(() => adjacentHeroIndex(3, 1, 3), RangeError);
    assert.throws(() => adjacentHeroIndex(0, 0, 3), RangeError);
    assert.throws(() => adjacentHeroIndex(-1, -1, 3), RangeError);
});

test('Hero slide navigation sends exactly one content change to the transition owner', () => {
    let click;
    const slides=[{hidden:false},{hidden:true},{hidden:true}];
    const group={
        contains:button=>button===control,
        querySelectorAll:()=>slides,
        addEventListener:(type,handler)=>{ assert.equal(type,'click');click=handler; },
    };
    const root={querySelectorAll:()=>[group]};
    const control={dataset:{homeHeroDirection:'next'}};
    const focus={focused:false,focus(){this.focused=true;}};
    for(const slide of slides){
        slide.querySelectorAll=()=>[];
        slide.querySelector=()=>focus;
    }
    const seen=[];
    initializeHomeHero(root,change=>{
        seen.push(slides.findIndex(s=>!s.hidden));
        change();
        seen.push(slides.findIndex(s=>!s.hidden));
    });
    click({target:{closest:()=>control}});
    click({target:{closest:()=>control}});
    click({target:{closest:()=>control}});
    assert.deepEqual(seen,[0,1,1,2,2,0]);
    assert.equal(slides[0].hidden,false);
    assert.equal(focus.focused,true);
});
