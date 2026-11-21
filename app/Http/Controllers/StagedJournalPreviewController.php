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
        $usages = $this->mediaUsages($form);
        $title = trim((string) ($form['title'] ?? ''));
        $title = $title !== '' ? $title : ($template === JournalTemplate::Blog ? 'Untitled post' : 'Untitled exhibition');

        if ($template === JournalTemplate::Blog) {
            $post = new BlogPost;
            $post->forceFill([
                'id' => -1,
                'site_section_id' => $section,
                'title' => $title,
                'state' => 'draft',
                'body' => $this->richSource($form['body'] ?? null),
                'excerpt' => $this->richSource($form['excerpt'] ?? null, false),
                // A staged draft has no public publication date yet.
                'published_at' => null,
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
            'id' => -1,
            'site_section_id' => $section,
            'title' => $title,
            'state' => 'draft',
            'starts_on' => $this->partialDate($form['starts_on'] ?? null, 'starts_on'),
            'ends_on' => $this->partialDate($form['ends_on'] ?? null, 'ends_on'),
            'description' => $this->richSource($form['description'] ?? null),
            'organizer' => $form['organizer'] ?? null,
            'venue' => $form['venue'] ?? null,
            'gallery_enabled' => (bool) ($form['gallery_enabled'] ?? false),
            'gallery_presentation' => $form['gallery_presentation'] ?? 'grid',
            'image_viewer_enabled' => (bool) ($form['image_viewer_enabled'] ?? false),
            'map_enabled' => false,
            'show_vernissage' => false,
            'show_organizer' => true,
            'show_venue' => true,
            'show_external_url' => true,
            'show_location_text' => true,
            'show_postal_code' => true,
            'show_city' => true,
            'show_country' => true,
        ]);
        $exhibition->setRelation('mediaUsages', $usages);

        return view('pages.exhibitions', [
            'section' => $page, 'settings' => JournalSetting::forSection($page),
            'exhibitions' => new Collection([$exhibition]),
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

    /** @param array<string,mixed> $form */
    private function mediaUsages(array $form): Collection
    {
        $rows = [];
        if (filled($form['cover_media_asset_id'] ?? null)) {
            $rows[] = [
                'role' => JournalEntryMedia::ROLE_COVER,
                'media_asset_id' => $form['cover_media_asset_id'],
                'video_controls_enabled' => $form['cover_video_controls_enabled'] ?? true,
            ];
        }
        foreach (is_array($form['gallery_images'] ?? null) ? $form['gallery_images'] : [] as $row) {
            if (is_array($row) && filled($row['media_asset_id'] ?? null)) {
                $rows[] = [
                    'role' => JournalEntryMedia::ROLE_GALLERY,
                    'media_asset_id' => $row['media_asset_id'],
                    'video_controls_enabled' => $row['video_controls_enabled'] ?? true,
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
