<?php

namespace App\Filament\Pages;

use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Content\SiteNodeType;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Filament\Support\SiteNodePresentation;
use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;
use UnitEnum;

final class SitePages extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Pages';

    protected static ?string $title = 'Pages';

    protected static ?string $slug = 'pages';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.site-pages';

    /** @var list<array<string, mixed>> */
    public array $sections = [];

    /** @var list<array{id: int, label: string, type: string}> */
    public array $parentCandidates = [];

    private ?SiteSectionOrderService $orderService = null;

    public function mount(): void
    {
        $this->loadSections();
    }

    public function moveSection(int $sectionId, string $direction): void
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($sectionId);
        if ($this->orderService()->move($section, $direction)) {
            Notification::make()->title('Site order updated')->success()->send();
            $this->loadSections();
        }
    }

    public function toggleSectionState(int $sectionId): void
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($sectionId);
        if (! $section->nodeType()->canChangePlacement()) {
            return;
        }

        $state = (string) $section->getAttribute('state') === 'published' ? 'hidden' : 'published';
        $this->updatePlacement($section, $state, (bool) $section->getAttribute('show_in_navigation'), $section->getAttribute('parent_id'));
    }

    public function toggleSectionNavigation(int $sectionId): void
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($sectionId);
        if (! $section->nodeType()->canChangePlacement()) {
            return;
        }

        $this->updatePlacement(
            $section,
            (string) $section->getAttribute('state'),
            ! (bool) $section->getAttribute('show_in_navigation'),
            $section->getAttribute('parent_id'),
        );
    }

    public function moveSectionParent(int $sectionId, int|string|null $parentSectionId): void
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($sectionId);
        $parentId = filled($parentSectionId) ? (int) $parentSectionId : null;
        $this->updatePlacement(
            $section,
            (string) $section->getAttribute('state'),
            (bool) $section->getAttribute('show_in_navigation'),
            $parentId,
        );
    }

    public function deleteSection(int $sectionId): void
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($sectionId);
        $type = $section->nodeType();
        if (! $type->canDelete()) {
            return;
        }

        try {
            if ($type === SiteNodeType::Gallery) {
                /** @var ArtworkCategory $gallery */
                $gallery = ArtworkCategory::query()->findOrFail((int) $section->getAttribute('artwork_category_id'));
                app(GalleryEditorialService::class)->delete($gallery);
            } else {
                app(SiteSectionEditorialService::class)->deleteConfigurableSection($section);
            }

            Notification::make()->title('Page removed')->success()->send();
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            Notification::make()
                ->title('Page was not removed')
                ->body(is_string($message) ? $message : 'This page cannot be removed yet.')
                ->danger()
                ->send();
        }

        $this->loadSections();
    }

    private function loadSections(): void
    {
        $this->orderService = app(SiteSectionOrderService::class);

        /** @var Builder<SiteSection> $topLevelQuery */
        $topLevelQuery = SiteSection::query()->whereNull('parent_id');

        /** @var EloquentCollection<int, SiteSection> $topLevel */
        $topLevel = $topLevelQuery
            ->with([
                'customPageSetting',
                'children' => static function (Relation $relation): void {
                    $query = $relation->getQuery();
                    $query->with('customPageSetting');
                    $query->orderBy('position');
                    $query->orderBy('id');
                },
            ])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $this->parentCandidates = $topLevel
            ->filter(static fn (SiteSection $section): bool => $section->nodeType()->canContainChildren())
            ->map(static fn (SiteSection $section): array => [
                'id' => (int) $section->getKey(),
                'label' => (string) ($section->getAttribute('navigation_label') ?: $section->getAttribute('title')),
                'type' => $section->nodeType()->value,
            ])
            ->values()
            ->all();

        $rows = [];
        foreach ($topLevel as $section) {
            $row = $this->row($section, 0);
            /** @var EloquentCollection<int, SiteSection> $children */
            $children = $section->getRelation('children');
            $row['children'] = $children
                ->map(fn (SiteSection $child): array => $this->row($child, 1))
                ->values()
                ->all();
            $rows[] = $row;
        }

        $this->sections = $rows;
    }

    /** @return array<string, mixed> */
    private function row(SiteSection $section, int $depth): array
    {
        $type = $section->nodeType();
        $journalTemplate = $section->journalTemplate();
        $hasChildren = $section->relationLoaded('children') && $section->getRelation('children')->isNotEmpty();
        $workspaceUrl = app(SiteNodePresentation::class)->workspaceUrl($section);
        $order = $this->orderService();

        $validParentIds = collect($this->parentCandidates)
            ->filter(function (array $candidate) use ($section, $type): bool {
                if ($candidate['id'] === (int) $section->getKey()) {
                    return false;
                }

                $parentType = SiteNodeType::tryFrom($candidate['type']);

                return $parentType !== null && $type->canBeChildOf($parentType);
            })
            ->pluck('id')
            ->all();

        return [
            'id' => (int) $section->getKey(),
            'type' => $type->value,
            'template' => $journalTemplate?->value,
            'type_label' => $type->label($journalTemplate),
            'title' => (string) $section->getAttribute('title'),
            'navigation_label' => $section->getAttribute('navigation_label'),
            'state' => (string) $section->getAttribute('state'),
            'visible' => (bool) $section->getAttribute('show_in_navigation'),
            'position' => (int) $section->getAttribute('position'),
            'parent_id' => $section->getAttribute('parent_id'),
            'has_children' => $hasChildren,
            'depth' => $depth,
            'public_url' => app(SiteNodeRoute::class)->url($section),
            'can_move_up' => $order->canMove($section, 'up'),
            'can_move_down' => $order->canMove($section, 'down'),
            'can_delete' => $type->canDelete(),
            'fixed_placement' => ! $type->canChangePlacement(),
            'can_choose_parent' => $type->canHaveParent() && ! $hasChildren,
            'valid_parent_ids' => $validParentIds,
            'workspace_url' => $workspaceUrl,
        ];
    }

    private function orderService(): SiteSectionOrderService
    {
        return $this->orderService ??= app(SiteSectionOrderService::class);
    }

    private function updatePlacement(SiteSection $section, string $state, bool $visible, mixed $parentId): void
    {
        try {
            app(SiteSectionEditorialService::class)->updatePlacement(
                $section,
                $state,
                $visible,
                $parentId === null ? null : (int) $parentId,
            );
            Notification::make()->title('Section placement updated')->success()->send();
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            Notification::make()
                ->title('Section placement unchanged')
                ->body(is_string($message) ? $message : 'The requested section placement is invalid.')
                ->danger()
                ->send();
        }

        $this->loadSections();
    }
}
