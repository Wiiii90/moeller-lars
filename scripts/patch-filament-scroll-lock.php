<?php

declare(strict_types=1);

/**
 * Versioned, build-time correction of Filament's *existing* modal scroll lock.
 *
 * Patch the single Filament Support runtime before "filament:upgrade" publishes
 * its assets. Never register a second Alpine modal implementation or runtime
 * scroll-lock listener in application JavaScript.
 *
 * Update and revalidate this patch when upgrading filament/support.
 */

$root = dirname(__DIR__);
$lockPath = $root.'/composer.lock';
$bundlePath = $root.'/vendor/filament/support/dist/index.js';

$lock = json_decode((string) file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);
$support = array_values(array_filter(
    $lock['packages'] ?? [],
    static fn (array $package): bool => ($package['name'] ?? null) === 'filament/support',
));

if (count($support) !== 1 || $support[0]['version'] !== 'v5.7.6') {
    fwrite(STDERR, "Filament modal scroll-lock patch: unsupported filament/support version. Review the upstream implementation before upgrading.\n");
    exit(1);
}

$original = <<<'JS'
var nr=0,kr=null,Ns=()=>{if(nr===0){let e=document.documentElement.style.overflow,t=document.documentElement.style.paddingRight,r=window.innerWidth-document.documentElement.clientWidth,n=window.getComputedStyle(document.documentElement).scrollbarGutter;document.documentElement.style.overflow="hidden",n&&n!=="auto"?kr=()=>{document.documentElement.style.overflow=e}:(document.documentElement.style.paddingRight=`${r}px`,kr=()=>{document.documentElement.style.overflow=e,document.documentElement.style.paddingRight=t})}nr++},js=()=>{nr!==0&&(nr--,nr===0&&(kr?.(),kr=null))},qo=
JS;

$replacement = <<<'JS'
var nr=0,kr=null,Ns=()=>{
    if(nr===0){
        const root=document.documentElement;
        const body=document.body;
        const x=window.scrollX;
        const y=window.scrollY;
        const originalHeight=root.scrollHeight;
        const hadVerticalScrollbar=originalHeight>root.clientHeight;
        const original={
            rootOverflow:root.style.overflow,
            rootOverflowX:root.style.overflowX,
            rootOverflowY:root.style.overflowY,
            rootMinHeight:root.style.minHeight,
            bodyPosition:body.style.position,
            bodyTop:body.style.top,
            bodyLeft:body.style.left,
            bodyWidth:body.style.width
        };
        // On a short page there is no document scroll position to freeze.
        // Keep the body in flow: fixing/unfixing it creates a transient
        // document-height change (and a one-frame scrollbar on close).
        if(!hadVerticalScrollbar){
            root.style.overflow="hidden";
            kr=()=>{root.style.overflow=original.rootOverflow};
        }else{
            const holdPosition=()=>{
                if(window.scrollX!==x||window.scrollY!==y){
                    window.scrollTo({left:x,top:y,behavior:"instant"});
                }
            };
            root.style.minHeight=`${originalHeight}px`;
            root.style.overflowX="hidden";
            root.style.overflowY="scroll";
            body.style.position="fixed";
            body.style.top=`-${y}px`;
            body.style.left=`-${x}px`;
            body.style.width="100%";
            window.addEventListener("scroll",holdPosition,{passive:true});
            kr=()=>{
                window.removeEventListener("scroll",holdPosition);
                root.style.overflow=original.rootOverflow;
                root.style.overflowX=original.rootOverflowX;
                root.style.overflowY=original.rootOverflowY;
                root.style.minHeight=original.rootMinHeight;
                body.style.position=original.bodyPosition;
                body.style.top=original.bodyTop;
                body.style.left=original.bodyLeft;
                body.style.width=original.bodyWidth;
                window.scrollTo({left:x,top:y,behavior:"instant"});
            };
        }
    }
    nr++;
},js=()=>{
    if(nr===0)return;
    nr--;
    if(nr===0){kr?.();kr=null}
},qo=
JS;

$bundle = @file_get_contents($bundlePath);
if ($bundle === false) {
    fwrite(STDERR, "Filament modal scroll-lock patch: support runtime is missing: {$bundlePath}\n");
    exit(1);
}

if (substr_count($bundle, $replacement) === 1 && ! str_contains($bundle, $original)) {
    echo "Filament modal scroll-lock patch already applied.\n";
    exit(0);
}

if (substr_count($bundle, $original) !== 1 || str_contains($bundle, $replacement)) {
    fwrite(STDERR, "Filament modal scroll-lock patch: upstream runtime differs from the reviewed 5.7.6 implementation; refusing to patch.\n");
    exit(1);
}

$updated = str_replace($original, $replacement, $bundle, $replacements);
if ($replacements !== 1 || file_put_contents($bundlePath, $updated) === false) {
    fwrite(STDERR, "Filament modal scroll-lock patch: failed to modify support runtime.\n");
    exit(1);
}

echo "Filament modal scroll-lock patched (single owner, native scrollbar preserved).\n";
