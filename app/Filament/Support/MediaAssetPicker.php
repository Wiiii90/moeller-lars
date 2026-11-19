<?php

namespace App\Filament\Support;

use Closure;
use Filament\Forms\Components\Select;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;

/**
 * The canonical Storage select keeps Filament's field state, relationships,
 * validation, and save callbacks. Only its option transport is paginated.
 */
final class MediaAssetPicker extends Select
{
    protected string $view = 'filament.forms.components.media-asset-picker';

    protected ?Closure $loadMediaPageUsing = null;

    public function mediaPages(Closure $callback): static
    {
        $this->loadMediaPageUsing = $callback;

        return $this;
    }

    /**
     * @return array{options:list<array{value:string,label:string}>,hasMore:bool}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function loadMediaPage(int $offset = 0, ?string $search = null): array
    {
        if ($this->loadMediaPageUsing === null) {
            return ['options' => [], 'hasMore' => false];
        }

        return $this->evaluate($this->loadMediaPageUsing, [
            'offset' => max(0, $offset),
            'search' => mb_substr(trim($search ?? ''), 0, 160),
        ]);
    }
}
