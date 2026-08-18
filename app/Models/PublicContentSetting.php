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
    'exhibitions_navigation_label',
    'exhibitions_navigation_position',
    'contact_state',
    'contact_status_text',
    'contact_icon',
    'contact_recipient_email',
    'public_email',
    'instagram_handle',
    'legal_disclaimer',
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
            'exhibitions_navigation_position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $setting): void {
            if ((int) $setting->getAttribute('id') !== 1) {
                throw new LogicException('The public content setting singleton must use id 1.');
            }

            $navigationItems = [];
            if ((bool) $setting->getAttribute('cv_enabled')) {
                $navigationItems['cv'] = [
                    'label' => $setting->getAttribute('cv_navigation_label'),
                    'position' => $setting->getAttribute('cv_navigation_position'),
                ];
            }
            if ((bool) $setting->getAttribute('exhibitions_enabled')) {
                $navigationItems['exhibitions'] = [
                    'label' => $setting->getAttribute('exhibitions_navigation_label'),
                    'position' => $setting->getAttribute('exhibitions_navigation_position'),
                ];
            }

            $usedPositions = [];
            foreach ($navigationItems as $key => $item) {
                $label = $item['label'];
                $position = $item['position'];

                if (! is_string($label) || trim($label) === '') {
                    throw ValidationException::withMessages([
                        $key.'_navigation_label' => ucfirst($key).' navigation label is required while the section is public.',
                    ]);
                }
                if (! is_int($position) && filter_var($position, FILTER_VALIDATE_INT) === false) {
                    throw ValidationException::withMessages([
                        $key.'_navigation_position' => ucfirst($key).' navigation position is invalid.',
                    ]);
                }

                $position = (int) $position;
                if (isset($usedPositions[$position])) {
                    throw ValidationException::withMessages([
                        $key.'_navigation_position' => 'Public navigation positions must be unique.',
                    ]);
                }
                $usedPositions[$position] = true;

                if (ArtworkCategory::query()
                    ->where('state', 'published')
                    ->where('show_in_navigation', true)
                    ->where('position', $position)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        $key.'_navigation_position' => 'A visible artwork category already uses this navigation position.',
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

            $publicEmail = $setting->getAttribute('public_email');
            if ($publicEmail !== null && (! is_string($publicEmail) || filter_var($publicEmail, FILTER_VALIDATE_EMAIL) === false)) {
                throw ValidationException::withMessages([
                    'public_email' => 'The public email address is invalid.',
                ]);
            }

            $contactRecipientEmail = $setting->getAttribute('contact_recipient_email');
            if ($contactRecipientEmail !== null && (! is_string($contactRecipientEmail) || filter_var($contactRecipientEmail, FILTER_VALIDATE_EMAIL) === false)) {
                throw ValidationException::withMessages([
                    'contact_recipient_email' => 'The contact form recipient email address is invalid.',
                ]);
            }

            $instagramHandle = $setting->getAttribute('instagram_handle');
            if ($instagramHandle !== null && (! is_string($instagramHandle) || preg_match('/^[A-Za-z0-9._]{1,30}$/', $instagramHandle) !== 1)) {
                throw ValidationException::withMessages([
                    'instagram_handle' => 'The Instagram handle is invalid.',
                ]);
            }

            $legalDisclaimer = $setting->getAttribute('legal_disclaimer');
            if ($legalDisclaimer !== null && (! is_string($legalDisclaimer) || trim($legalDisclaimer) === '')) {
                throw ValidationException::withMessages([
                    'legal_disclaimer' => 'The legal disclaimer must contain text or be empty.',
                ]);
            }
        });

        static::deleting(function (): never {
            throw new LogicException('The public content setting singleton cannot be deleted.');
        });
    }
}
