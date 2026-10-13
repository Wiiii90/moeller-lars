<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeRoutingSettingsService;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Domain\Content\SiteSectionType;
use App\Filament\Pages\SitePages;
use App\Filament\Support\PagesSettingsDialog;
use App\Models\ArtworkCategory;
use App\Models\AuditEvent;
use App\Models\AdminNotification;
use App\Models\BlogPost;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;


function pagesRepairAdmin(): User
{
    return User::factory()->admin()->create();
}
