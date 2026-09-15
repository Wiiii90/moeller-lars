<?php

use App\Domain\Content\SiteSectionType;
use App\Filament\Pages\Activity;
use App\Filament\Pages\Analytics;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\General;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\SiteNodePresentation;

it('derives compact admin icons from central semantics with explicit solid and custom exceptions', function (): void {
    foreach (AdminIcon::cases() as $icon) {
        if ($icon === AdminIcon::Pinned) {
            expect($icon->value)->toBe('heroicon-s-bookmark')
                ->and($icon->mini())->toBe('heroicon-s-bookmark');

            continue;
        }

        if ($icon === AdminIcon::NavigationNode) {
            expect($icon->value)->toBe('admin-node')
                ->and($icon->mini())->toBe('admin-node');

            continue;
        }

        expect($icon->value)->toStartWith('heroicon-o-')
            ->and($icon->mini())->toBe(str_replace('heroicon-o-', 'heroicon-m-', $icon->value));
    }
});

it('keeps static admin navigation icons in the central registry', function (): void {
    $icons = [
        Dashboard::class => AdminIcon::Dashboard,
        General::class => AdminIcon::General,
        SitePages::class => AdminIcon::Pages,
        Analytics::class => AdminIcon::Analytics,
        Activity::class => AdminIcon::Activity,
        ArtworkResource::class => AdminIcon::Artwork,
        BlogPostResource::class => AdminIcon::BlogPost,
        CvEntryResource::class => AdminIcon::CvEntry,
        ExhibitionResource::class => AdminIcon::Exhibition,
        MediaAssetResource::class => AdminIcon::Storage,
    ];

    foreach ($icons as $class => $expectedIcon) {
        $property = (new ReflectionClass($class))->getProperty('navigationIcon');

        expect($property->getValue())->toBe($expectedIcon);
    }
});

it('keeps dynamic site node icons in the central registry', function (): void {
    $presentation = new SiteNodePresentation;
    $icons = [
        SiteSectionType::Home->value => AdminIcon::Home,
        SiteSectionType::Gallery->value => AdminIcon::Gallery,
        SiteSectionType::Journal->value => AdminIcon::Journal,
        SiteSectionType::CustomPage->value => AdminIcon::CustomPage,
        SiteSectionType::NavigationNode->value => AdminIcon::NavigationNode,
    ];

    foreach (SiteSectionType::cases() as $type) {
        expect($presentation->icon($type))->toBe($icons[$type->value]);
    }
});
