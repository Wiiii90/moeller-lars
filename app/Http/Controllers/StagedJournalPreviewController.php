<?php

namespace App\Http\Controllers;

use App\Domain\Content\ExhibitionDate;
use App\Domain\Content\JournalMediaRenderer;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\SiteSectionType;
use App\Domain\Media\PublicMedia;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\JournalEntryMedia;
use App\Models\JournalSetting;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

final class StagedJournalPreviewController extends Controller
{
    public function __construct(
        private readonly SafeRichTextRenderer $richText,
        private readonly PublicMedia $media,
        private readonly JournalMediaRenderer $journalMedia,
        private readonly SiteNodeRoute $siteNodeRoute,
    ) {}

    /** Reuse the canonical Public templates with an in-memory Stage entry. */
    public function show(int $section, string $token): View
    {
        $snapshot = session()->get('journal.stage.preview.'.$token);
        abort_unless(is_array($snapshot) && (int) ($snapshot['section_id'] ?? 0) === $section, 404);

        $page = SiteSection::query()->whereKey($section)
            ->where('type', SiteSectionType::Journal->value)->firstOrFail();
        $template = $page->journalTemplate();
        abort_unless($template instanceof JournalTemplate && $template->value === ($snapshot['template'] ?? null), 404);
        $form = is_array($snapshot['form'] ?? null) ? $snapshot['form'] : [];
        $entryId = filter_var($snapshot['entry_id'] ?? null, FILTER_VALIDATE_INT);
        $existing = $entryId !== false && $entryId > 0
            ? ($template === JournalTemplate::Blog
                ? BlogPost::query()->where('site_section_id', $section)->findOrFail($entryId)
                : Exhibition::query()->where('site_section_id', $section)->findOrFail($entryId))
            : null;
        $settings = JournalSetting::forSection($page);
        $defaultVideoControls = (bool) ($settings->getAttribute('default_video_controls_enabled') ?? true);
        $usages = $this->mediaUsages($form, $defaultVideoControls, $existing);
        $title = trim((string) ($form['title'] ?? ''));
        $title = $title !== '' ? $title : ($template === JournalTemplate::Blog ? 'Untitled post' : 'Untitled exhibition');

        if ($template === JournalTemplate::Blog) {
            $post = new BlogPost;
            $post->forceFill([
                ...($existing instanceof BlogPost ? $existing->getAttributes() : []),
                'id' => $existing?->getKey() ?? -1,
                'site_section_id' => $section,
                'title' => $title,
                'state' => $existing?->getAttribute('state') ?? 'draft',
                'body' => $this->richSource($form['body'] ?? null),
                'excerpt' => $this->richSource($form['excerpt'] ?? null, false),
                'published_at' => $existing?->getAttribute('published_at'),
                'gallery_presentation' => $form['gallery_presentation'] ?? 'sequence',
                'image_viewer_enabled' => (bool) ($form['image_viewer_enabled'] ?? false),
            ]);
            $post->setRelation('mediaUsages', $usages);

            return view('pages.blog.show', [
                'section' => $page, 'post' => $post,
                'richText' => $this->richText, 'media' => $this->media,
                'journalMedia' => $this->journalMedia, 'siteNodeRoute' => $this->siteNodeRoute,
            ]);
        }

        $exhibition = new Exhibition;
        $exhibition->forceFill([
            ...($existing instanceof Exhibition ? $existing->getAttributes() : []),
            'id' => $existing?->getKey() ?? -1,
            'site_section_id' => $section,
            'title' => $title,
            'state' => $existing?->getAttribute('state') ?? 'draft',
            'starts_on' => $this->partialDate($form['starts_on'] ?? null, 'starts_on'),
            'ends_on' => $this->partialDate($form['ends_on'] ?? ($existing?->getAttribute('ends_on')), 'ends_on'),
            'description' => $this->richSource($form['description'] ?? null),
            'organizer' => $form['organizer'] ?? null,
            'venue' => $form['venue'] ?? null,
            'gallery_enabled' => (bool) ($form['gallery_enabled'] ?? false),
            'gallery_presentation' => $form['gallery_presentation'] ?? 'grid',
            'image_viewer_enabled' => (bool) ($form['image_viewer_enabled'] ?? $existing?->getAttribute('image_viewer_enabled') ?? false),
            'location_text' => $form['location_text'] ?? $existing?->getAttribute('location_text'),
            'postal_code' => $form['postal_code'] ?? $existing?->getAttribute('postal_code'),
            'city' => $form['city'] ?? $existing?->getAttribute('city'),
            'country' => $form['country'] ?? $existing?->getAttribute('country'),
            'external_url' => $form['external_url'] ?? $existing?->getAttribute('external_url'),
            // A changed address has not yet been geocoded; keep old pins out.
            'map_enabled' => $existing instanceof Exhibition
                && collect(['location_text', 'postal_code', 'city', 'country'])->every(
                    static fn (string $field): bool => ($form[$field] ?? null) === $existing->getAttribute($field),
                )
                ? (bool) $existing->getAttribute('map_enabled') : false,
            'show_vernissage' => (bool) ($existing?->getAttribute('show_vernissage') ?? false),
            'show_organizer' => (bool) ($existing?->getAttribute('show_organizer') ?? true),
            'show_venue' => (bool) ($existing?->getAttribute('show_venue') ?? true),
            'show_external_url' => (bool) ($existing?->getAttribute('show_external_url') ?? true),
            'show_location_text' => (bool) ($existing?->getAttribute('show_location_text') ?? true),
            'show_postal_code' => (bool) ($existing?->getAttribute('show_postal_code') ?? true),
            'show_city' => (bool) ($existing?->getAttribute('show_city') ?? true),
            'show_country' => (bool) ($existing?->getAttribute('show_country') ?? true),
        ]);
        $exhibition->setRelation('mediaUsages', $usages);

        $exhibitions = $existing instanceof Exhibition
            ? Exhibition::query()->where('site_section_id', $section)
                ->where('state', '<>', 'archived')
                ->with('mediaUsages.mediaAsset.variants')->orderBy('position')->orderBy('id')->get()
                ->map(static fn (Exhibition $item): Exhibition =>
                    (int) $item->getKey() === (int) $existing->getKey() ? $exhibition : $item)
            : new Collection([$exhibition]);

        return view('pages.exhibitions', [
            'section' => $page, 'settings' => JournalSetting::forSection($page),
            'exhibitions' => $exhibitions,
            'richText' => $this->richText, 'media' => $this->media,
            'journalMedia' => $this->journalMedia, 'siteNodeRoute' => $this->siteNodeRoute,
        ]);
    }

    private function richSource(mixed $source, bool $allowMedia = true): ?string
    {
        if (! is_string($source) || trim($source) === '') {
            return null;
        }
        try {
            $this->richText->assertValid($source, allowEmbeddedMedia: $allowMedia);
        } catch (Throwable) {
            return null;
        }

        return $source;
    }

    private function partialDate(mixed $date, string $field): ?string
    {
        try {
            return ExhibitionDate::normalize($date, $field);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string,array<int,list<bool>>> $original */
    private function previewVideoControls(array &$original, string $role, int $assetId, bool $fallback): bool
    {
        if (($original[$role][$assetId] ?? []) === []) {
            return $fallback;
        }

        return (bool) array_shift($original[$role][$assetId]);
    }

    /** @param array<string,mixed> $form */
    private function mediaUsages(array $form, bool $defaultVideoControls, BlogPost|Exhibition|null $existing): Collection
    {
        $original = [];
        if ($existing !== null) {
            foreach ($existing->mediaUsages()->orderBy('position')->orderBy('id')->get() as $usage) {
                $role = (string) $usage->getAttribute('role');
                $assetId = (int) $usage->getAttribute('media_asset_id');
                $original[$role][$assetId][] = $usage->videoControlsOverride() ?? $defaultVideoControls;
            }
        }
        $rows = [];
        if (filled($form['cover_media_asset_id'] ?? null)) {
            $rows[] = [
                'role' => JournalEntryMedia::ROLE_COVER,
                'media_asset_id' => $form['cover_media_asset_id'],
                'video_controls_enabled' => $this->previewVideoControls(
                    $original, JournalEntryMedia::ROLE_COVER, (int) $form['cover_media_asset_id'], $defaultVideoControls,
                ),
            ];
        }
        foreach (is_array($form['gallery_images'] ?? null) ? $form['gallery_images'] : [] as $row) {
            if (is_array($row) && filled($row['media_asset_id'] ?? null)) {
                $rows[] = [
                    'role' => JournalEntryMedia::ROLE_GALLERY,
                    'media_asset_id' => $row['media_asset_id'],
                    'video_controls_enabled' => $this->previewVideoControls(
                        $original, JournalEntryMedia::ROLE_GALLERY, (int) $row['media_asset_id'], $defaultVideoControls,
                    ),
                ];
            }
        }

        $assetIds = array_map(static fn (array $row): int => (int) $row['media_asset_id'], $rows);
        $assets = MediaAsset::query()->with('variants')->whereIn('id', $assetIds)
            ->where('state', 'available')->get()->keyBy('id');
        $usages = new Collection;
        foreach ($rows as $position => $row) {
            $asset = $assets->get((int) $row['media_asset_id']);
            if (! $asset instanceof MediaAsset) {
                continue;
            }
            $usage = new JournalEntryMedia;
            $usage->forceFill([
                'id' => -($position + 1),
                'media_asset_id' => (int) $asset->getKey(),
                'role' => $row['role'],
                'position' => $position,
                'video_controls_enabled' => (bool) $row['video_controls_enabled'],
            ]);
            $usage->setRelation('mediaAsset', $asset);
            $usages->push($usage);
        }

        return $usages;
    }
}
