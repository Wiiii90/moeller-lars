<?php

namespace App\Filament\Support;

use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeRoutingSettingsService;
use App\Domain\Content\SiteSectionType;
use App\Filament\Support\Controls\AdminControl;
use App\Models\HomePresentationSetting;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Validation\ValidationException;

final class HomeRoutingDialog
{
    /** @var array<int, string>|null */
    private ?array $targetOptionsCache = null;

    public function __construct(
        private readonly HomePresentationResolver $resolver,
        private readonly HomeRoutingSettingsService $routing,
        private readonly SiteNodeRoute $routes,
    ) {}

    /** @return array{skip_home:bool,skip_target_section_id:?int} */
    public function fill(?HomePresentationSetting $settings = null): array
    {
        $settings ??= $this->resolver->settings();
        $routing = $this->routing->configuration($settings);
        $targetId = $routing['skip_target_section_id'];
        if ($targetId !== null && ! array_key_exists($targetId, $this->targetOptions())) {
            $targetId = null;
        }

        return [
            'skip_home' => $routing['skip_home'],
            'skip_target_section_id' => $targetId,
        ];
    }

    /** @return list<mixed> */
    public function schema(?Closure $onToggleUpdated = null): array
    {
        $skipHome = AdminControl::toggle('skip_home', 'Skip Home')->live();
        if ($onToggleUpdated !== null) {
            $skipHome->afterStateUpdated($onToggleUpdated);
        }

        return [
            $skipHome,
            Select::make('skip_target_section_id')
                ->label('Skip to')
                ->options(fn (): array => $this->targetOptions())
                ->placeholder(__('Next page in order'))
                ->visible(fn (callable $get): bool => (bool) $get('skip_home'))
                ->native()
                ->nullable(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): bool
    {
        $settings = $this->resolver->settings();
        $enabled = (bool) ($data['skip_home'] ?? false);
        $targetId = $this->nullablePositiveInt($data['skip_target_section_id'] ?? null);

        if ($enabled && $targetId === null && ! $this->resolver->skipTarget() instanceof SiteSection) {
            throw ValidationException::withMessages([
                'skip_target_section_id' => 'Add or publish an eligible top-level page before enabling Skip Home.',
            ]);
        }

        return $this->routing->update($settings, $enabled, $enabled ? $targetId : null);
    }

    /** @return array{skip_home:bool,skip_target_label:?string,skip_target_section_id:?int,skip_target_options:array<int,string>} */
    public function tableState(): array
    {
        $settings = $this->resolver->settings();
        $target = $this->resolver->skipTarget();
        $targetLabel = $target instanceof SiteSection
            ? trim((string) ($target->getAttribute('navigation_label') ?: $target->getAttribute('title')))
            : null;

        return [
            'skip_home' => $this->routing->enabled($settings),
            'skip_target_label' => $targetLabel !== '' ? $targetLabel : null,
            'skip_target_section_id' => $this->routing->configuredTargetId($settings),
            'skip_target_options' => $this->targetOptions(),
        ];
    }

    /** @return array<int, string> */
    public function targetOptions(): array
    {
        if ($this->targetOptionsCache !== null) {
            return $this->targetOptionsCache;
        }

        return $this->targetOptionsCache = SiteSection::query()
            ->whereNull('parent_id')
            ->where('state', 'published')
            ->where('type', '<>', SiteSectionType::Home->value)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->filter(fn (SiteSection $section): bool => $section->nodeType()->hasPublicPage()
                && $section->nodeType() !== SiteSectionType::NavigationNode
                && $this->routes->path($section) !== null)
            ->mapWithKeys(fn (SiteSection $section): array => [
                (int) $section->getKey() => trim((string) ($section->getAttribute('navigation_label') ?: $section->getAttribute('title'))),
            ])
            ->all();
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT);

        return $id === false || $id <= 0 ? null : (int) $id;
    }
}
