<?php

namespace App\Filament\Support;

use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeRoutingSettingsService;
use App\Domain\Content\SiteNodeType;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Validation\ValidationException;

final class HomeRoutingDialog
{
    public function __construct(
        private readonly HomePresentationResolver $resolver,
        private readonly HomeRoutingSettingsService $routing,
        private readonly SiteNodeRoute $routes,
    ) {}

    /** @return array{skip_home:bool,skip_target_section_id:?int} */
    public function fill(): array
    {
        $routing = $this->routing->configuration($this->resolver->settings());

        return [
            'skip_home' => $routing['skip_home'],
            'skip_target_section_id' => $routing['skip_target_section_id'],
        ];
    }

    /** @return list<mixed> */
    public function schema(): array
    {
        return [
            Toggle::make('skip_home')
                ->label('Skip Home')
                ->helperText('Redirect the public root instead of showing Home.')
                ->live(),
            Select::make('skip_target_section_id')
                ->label('Skip to')
                ->options(fn (): array => $this->targetOptions())
                ->placeholder('Next page in order')
                ->helperText('Leave this on Next page in order to follow the current page order automatically.')
                ->visible(fn (callable $get): bool => (bool) $get('skip_home'))
                ->native()
                ->nullable(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): void
    {
        $settings = $this->resolver->settings();
        $enabled = (bool) ($data['skip_home'] ?? false);
        $targetId = $this->nullablePositiveInt($data['skip_target_section_id'] ?? null);

        if ($enabled && $targetId === null && ! $this->resolver->skipTarget() instanceof SiteSection) {
            throw ValidationException::withMessages([
                'skip_target_section_id' => 'Add or publish an eligible top-level page before enabling Skip Home.',
            ]);
        }

        $this->routing->update($settings, $enabled, $enabled ? $targetId : null);
    }

    /** @return array<int, string> */
    private function targetOptions(): array
    {
        return SiteSection::query()
            ->whereNull('parent_id')
            ->where('state', 'published')
            ->where('type', '<>', SiteNodeType::Home->value)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->filter(fn (SiteSection $section): bool => $section->nodeType()->hasPublicPage()
                && $section->nodeType() !== SiteNodeType::NavigationNode
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
