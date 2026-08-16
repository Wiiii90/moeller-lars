<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LogicException;

#[Fillable([
    'cv_enabled',
    'exhibitions_enabled',
    'cv_navigation_label',
    'cv_navigation_position',
    'contact_state',
    'contact_status_text',
    'contact_icon',
])]
#[Guarded(['id'])]
class PublicContentSetting extends Model
{
    protected $table = 'public_content_settings';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'cv_enabled' => 'boolean',
            'exhibitions_enabled' => 'boolean',
            'cv_navigation_position' => 'integer',
        ];
    }

    public function cvSurfaceEnabled(): bool
    {
        return (bool) $this->getAttribute('cv_enabled') || (bool) $this->getAttribute('exhibitions_enabled');
    }

    protected static function booted(): void
    {
        static::saving(function (self $setting): void {
            if ((int) $setting->getAttribute('id') !== 1) {
                throw new LogicException('The public content setting singleton must use id 1.');
            }

            if ($setting->cvSurfaceEnabled()) {
                $label = $setting->getAttribute('cv_navigation_label');
                $position = $setting->getAttribute('cv_navigation_position');

                if (! is_string($label) || trim($label) === '') {
                    throw ValidationException::withMessages([
                        'cv_navigation_label' => 'The CV navigation label is required while CV or Exhibitions is public.',
                    ]);
                }

                if (! is_int($position) && filter_var($position, FILTER_VALIDATE_INT) === false) {
                    throw ValidationException::withMessages([
                        'cv_navigation_position' => 'The CV navigation position is invalid.',
                    ]);
                }

                if (ArtworkCategory::query()
                    ->where('state', 'published')
                    ->where('show_in_navigation', true)
                    ->where('position', (int) $position)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'cv_navigation_position' => 'A visible artwork category already uses this navigation position.',
                    ]);
                }
            }

            if ($setting->getAttribute('contact_state') === 'under_construction') {
                $statusText = $setting->getAttribute('contact_status_text');
                if (! is_string($statusText) || trim($statusText) === '') {
                    throw ValidationException::withMessages([
                        'contact_status_text' => 'A status text is required while Contact is under construction.',
                    ]);
                }
            }
        });

        static::deleting(function (): never {
            throw new LogicException('The public content setting singleton cannot be deleted.');
        });
    }
}
