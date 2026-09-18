<?php

namespace App\Models;

use App\Domain\Content\HomeTemplate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['template', 'configuration', 'skip_home', 'skip_target_section_id'])]
#[Guarded(['id', 'site_section_id'])]
final class HomePresentationSetting extends Model
{
    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'skip_home' => 'boolean',
            'skip_target_section_id' => 'integer',
        ];
    }

    /** @return BelongsTo<SiteSection, $this> */
    public function siteSection(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class);
    }

    /** @return BelongsTo<SiteSection, $this> */
    public function skipTargetSection(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class, 'skip_target_section_id');
    }

    public function template(): HomeTemplate
    {
        return HomeTemplate::from((string) $this->getAttribute('template'));
    }

    /** @return array<string, mixed> */
    public function configuration(): array
    {
        $configuration = $this->getAttribute('configuration');

        return is_array($configuration) ? $configuration : [];
    }

    /** @return array<string, mixed> */
    public function modeConfiguration(HomeTemplate $template): array
    {
        $configuration = $this->configuration();
        $mode = $configuration[$template->value] ?? [];

        return is_array($mode) ? $mode : [];
    }

    /** @return list<array<string, mixed>> */
    public function components(HomeTemplate $template): array
    {
        $components = $this->modeConfiguration($template)['components'] ?? [];

        return is_array($components) && array_is_list($components) ? $components : [];
    }
}
