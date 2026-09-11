<?php

namespace App\Livewire\Admin;

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\PublicAppearance;
use App\Models\PublicContentSetting;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class GeneralLayoutControls extends Component
{
    public int|string $pageWidth = PublicAppearance::DEFAULT_PAGE_WIDTH;

    public int|string $contentPadding = PublicAppearance::DEFAULT_CONTENT_PADDING;

    public function mount(): void
    {
        $settings = PublicContentSetting::general();
        $this->pageWidth = PublicAppearance::normalizePageWidth($settings->getAttribute('public_page_width'));
        $this->contentPadding = PublicAppearance::normalizeContentPadding($settings->getAttribute('public_content_padding'));
    }

    public function updatedPageWidth(): void
    {
        $this->validateOnly('pageWidth', ['pageWidth' => 'required|integer|min:640|max:1440']);
        $this->pageWidth = PublicAppearance::normalizePageWidth($this->pageWidth);
        $this->persist('public_page_width', (int) $this->pageWidth);
    }

    public function updatedContentPadding(): void
    {
        $this->validateOnly('contentPadding', ['contentPadding' => 'required|integer|min:24|max:180']);
        $this->contentPadding = PublicAppearance::normalizeContentPadding($this->contentPadding);
        $this->persist('public_content_padding', (int) $this->contentPadding);
    }

    public function render(): View
    {
        return view('livewire.admin.general-layout-controls');
    }

    private function persist(string $field, int $value): void
    {
        app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [$field => $value]);
        $this->dispatch('general-appearance-updated');
    }
}
