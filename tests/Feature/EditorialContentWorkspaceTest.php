<?php

use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Filament\Resources\CvEntries\Pages\ListCvEntries;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('renders Vita, Exhibitions and Blog as editorial workspaces', function (): void {
    CvEntry::create([
        'section' => 'Biography',
        'title' => 'Editorial Vita',
        'year_text' => '2026',
        'date_precision' => 'year',
        'state' => 'draft',
        'position' => 0,
    ]);
    Exhibition::create([
        'slug' => 'editorial-exhibition',
        'title' => 'Editorial Exhibition',
        'date_text' => '2026',
        'state' => 'draft',
        'position' => 0,
    ]);
    BlogPost::create([
        'slug' => 'editorial-blog',
        'title' => 'Editorial Blog',
        'state' => 'draft',
        'position' => 0,
    ]);

    $this->get(CvEntryResource::getUrl('index'))->assertSuccessful()->assertSee('Editorial sequence')->assertSee('Editorial Vita');
    $this->get(ExhibitionResource::getUrl('index'))->assertSuccessful()->assertSee('Exhibition programme')->assertSee('Editorial Exhibition');
    $this->get(BlogPostResource::getUrl('index'))->assertSuccessful()->assertSee('Editorial queue')->assertSee('Editorial Blog');
});

it('reorders Vita through the actual editorial workspace', function (): void {
    $first = CvEntry::create([
        'section' => 'Biography',
        'title' => 'First Vita',
        'year_text' => '2025',
        'date_precision' => 'year',
        'state' => 'draft',
        'position' => 0,
    ]);
    $second = CvEntry::create([
        'section' => 'Biography',
        'title' => 'Second Vita',
        'year_text' => '2026',
        'date_precision' => 'year',
        'state' => 'draft',
        'position' => 1,
    ]);

    Livewire::test(ListCvEntries::class)
        ->call('moveEntry', $second->id, 'up')
        ->assertSee('Second Vita');

    expect((int) $second->fresh()->position)->toBe(0)
        ->and((int) $first->fresh()->position)->toBe(1);
});

it('reorders Blog through the actual editorial workspace', function (): void {
    $first = BlogPost::create([
        'slug' => 'first-blog',
        'title' => 'First Blog',
        'state' => 'draft',
        'position' => 0,
    ]);
    $second = BlogPost::create([
        'slug' => 'second-blog',
        'title' => 'Second Blog',
        'state' => 'draft',
        'position' => 1,
    ]);

    Livewire::test(ListBlogPosts::class)
        ->call('movePost', $second->id, 'up')
        ->assertSee('Second Blog');

    expect((int) $second->fresh()->position)->toBe(0)
        ->and((int) $first->fresh()->position)->toBe(1);
});
