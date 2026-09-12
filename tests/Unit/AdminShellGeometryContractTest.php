<?php

it('keeps desktop admin shell geometry independent of document scrollbar presence', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/admin/layouts.css');

    expect($css)
        ->toMatch('/html\.fi,\s*\.fi-sidebar-nav\s*\{\s*scrollbar-gutter:\s*auto\s*!important;/s')
        ->toMatch('/@media\s*\(min-width:\s*1024px\).*?html\.fi,\s*body\.fi-body\s*\{\s*overflow-x:\s*clip;.*?\.fi-layout\s*\{\s*width:\s*100vw;/s')
        ->not->toContain('scrollbar-gutter: stable')
        ->not->toContain('translateX(');
});
