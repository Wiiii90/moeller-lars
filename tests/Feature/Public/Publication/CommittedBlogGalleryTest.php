<?php

use App\Domain\Content\BlogEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Publication\PublicationService;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\PublicContentSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('serves a committed Blog gallery without visual captions while retaining authored media rights and keeps Working changes private', function (): void {
    Storage::fake('committed-blog-gallery-test');
    config(['media.disk' => 'committed-blog-gallery-test']);

    // The public Home gate is independent of Journal publication. Keep this
    // fixture focused on the Journal's real public routes.
    foreach (['public', 'committed'] as $schema) {
        DB::table($schema.'.home_presentation_settings')->update(['template' => 'custom']);
    }

    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    PublicContentSetting::general()->update([
        'default_media_copyright_notice' => 'Inherited gallery rights notice',
    ]);

    $assets = [];
    foreach ([
        ['north', 'North study image', 'Photograph by North Studio', MediaAsset::COPYRIGHT_INHERIT, null],
        ['south', 'South study image', 'Photograph by South Studio', MediaAsset::COPYRIGHT_OVERRIDE, 'Individual gallery rights notice'],
    ] as [$name, $alt, $credit, $noticeMode, $notice]) {
        $originalKey = 'originals/blog-gallery-'.$name.'.jpg';
        $variantKey = 'variants/blog-gallery-'.$name.'.webp';
        $original = 'original-'.$name;
        $thumbnail = 'thumbnail-'.$name;
        Storage::disk(config('media.disk'))->put($originalKey, $original);
        Storage::disk(config('media.disk'))->put($variantKey, $thumbnail);

        $asset = MediaAsset::query()->create([
            'storage_key' => $originalKey,
            'original_filename' => 'blog-gallery-'.$name.'.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => strlen($original),
            'sha256' => hash('sha256', $original),
            'state' => 'available',
            'alt_text' => $alt,
            'credit' => $credit,
            'copyright_notice_mode' => $noticeMode,
            'copyright_notice' => $notice,
            'width' => 100,
            'height' => 100,
        ]);
        MediaVariant::query()->create([
            'media_asset_id' => $asset->getKey(),
            'variant_kind' => 'thumbnail',
            'storage_key' => $variantKey,
            'mime_type' => 'image/webp',
            'byte_size' => strlen($thumbnail),
            'sha256' => hash('sha256', $thumbnail),
            'transform_profile' => 'public-v1',
            'state' => 'available',
            'width' => 50,
            'height' => 50,
        ]);
        $assets[] = $asset;
    }

    $sections = app(SiteSectionEditorialService::class);
    $section = $sections->createJournal('Gallery Journal', 'gallery-journal-public', JournalTemplate::Blog->value);
    $sections->updatePlacement($section, 'published', null);

    $editor = app(BlogEditorialService::class);
    $post = $editor->createDraft([
        'site_section_id' => $section->getKey(),
        'title' => 'Gallery publication story',
        'slug' => 'gallery-publication-story',
        'body' => 'Two photographed works with individually authored credits.',
    ]);
    $editor->update($post, [
        ...$post->fresh()->attributesToArray(),
        'gallery_presentation' => 'grid',
        'cover_media_asset_id' => $assets[0]->getKey(),
        'gallery_images' => [
            ['media_asset_id' => $assets[0]->getKey()],
            ['media_asset_id' => $assets[1]->getKey()],
        ],
    ]);
    $editor->publish($post);

    $publicUrl = route('journal.show', ['section' => $section->slug, 'slug' => $post->slug]);
    $previewUrl = route('preview.journal.show', ['section' => $section->slug, 'slug' => $post->slug]);
    $indexUrl = route('site.section', ['section' => $section->slug]);
    $variantUrl = route('media.variant', ['mediaVariant' => $assets[1]->variants()->firstOrFail()->getKey()]);

    // Anonymous visitors cannot see Working pages or media. An admin can
    // inspect the same content through the protected Preview route.
    Auth::guard('web')->logout();
    $this->get($publicUrl)->assertNotFound();
    $this->get($variantUrl)->assertNotFound();
    $this->actingAs($actor, 'web');
    $this->get($previewUrl)
        ->assertOk()
        ->assertSee('Gallery publication story')
        ->assertSee('journal-entry-gallery--grid', false)
        ->assertDontSee('Photograph by North Studio')
        ->assertDontSee('Photograph by South Studio')
        ->assertDontSee('Inherited gallery rights notice')
        ->assertDontSee('Individual gallery rights notice');

    $publication = app(PublicationService::class);
    expect($publication->commit($actor))->not->toBeNull();
    foreach ($assets as $asset) {
        expect($asset->fresh()->credit)->toBe($asset->credit)
            ->and(DB::table('committed.media_assets')->where('id', $asset->id)->value('credit'))->toBe($asset->credit);
    }
    Auth::guard('web')->logout();

    $this->get($indexUrl)
        ->assertOk()
        ->assertSee('Gallery publication story')
        ->assertDontSee('Photograph by North Studio')
        ->assertDontSee('Inherited gallery rights notice');

    $this->get($publicUrl)
        ->assertOk()
        ->assertSee('journal-entry-gallery--grid', false)
        ->assertSeeInOrder(['North study image', 'South study image'])
        ->assertDontSee('Photograph by North Studio')
        ->assertDontSee('Photograph by South Studio')
        ->assertDontSee('Inherited gallery rights notice')
        ->assertDontSee('Individual gallery rights notice')
        ->assertDontSee('data-journal-slide-next', false);
    $this->get($variantUrl)->assertOk()->assertHeader('Content-Type', 'image/webp');

    // Changing the presentation stages only Working. Public visitors keep
    // seeing the grid until the separate, explicit Commit.
    $this->actingAs($actor, 'web');
    $editor->update($post, [
        ...$post->fresh()->attributesToArray(),
        'gallery_presentation' => 'slideshow',
    ]);

    $this->get($previewUrl)
        ->assertOk()
        ->assertSee('data-journal-slide-next', false)
        ->assertSee('1 / 2');
    Auth::guard('web')->logout();
    $this->get($publicUrl)
        ->assertOk()
        ->assertSee('journal-entry-gallery--grid', false)
        ->assertDontSee('data-journal-slide-next', false);

    $this->actingAs($actor, 'web');
    expect($publication->commit($actor))->not->toBeNull();
    Auth::guard('web')->logout();

    $this->get($publicUrl)
        ->assertOk()
        ->assertSee('data-journal-slideshow', false)
        ->assertSee('data-journal-slide-next', false)
        ->assertSee('1 / 2')
        ->assertDontSee('Photograph by North Studio')
        ->assertDontSee('Photograph by South Studio')
        ->assertDontSee('Inherited gallery rights notice')
        ->assertDontSee('Individual gallery rights notice');
});
