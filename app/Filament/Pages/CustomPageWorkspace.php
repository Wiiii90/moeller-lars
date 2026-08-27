<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\CustomPageWorkspaceChildOrdering;
use App\Filament\Pages\Concerns\CustomPageWorkspaceChildProjection;
use App\Filament\Pages\Concerns\CustomPageWorkspaceComponentActions;
use App\Filament\Pages\Concerns\CustomPageWorkspaceCvActions;
use App\Filament\Pages\Concerns\CustomPageWorkspaceForms;
use App\Filament\Pages\Concerns\CustomPageWorkspaceLifecycle;
use App\Filament\Pages\Concerns\CustomPageWorkspaceListContactActions;
use App\Filament\Pages\Concerns\CustomPageWorkspacePresentationHelpers;
use App\Filament\Pages\Concerns\CustomPageWorkspaceProjectionState;
use App\Filament\Pages\Concerns\CustomPageWorkspaceSecondaryForms;
use App\Filament\Pages\Concerns\CustomPageWorkspaceTargetHelpers;
use Filament\Pages\Page;

final class CustomPageWorkspace extends Page
{
    use CustomPageWorkspaceLifecycle;
    use CustomPageWorkspaceComponentActions;
    use CustomPageWorkspaceListContactActions;
    use CustomPageWorkspaceCvActions;
    use CustomPageWorkspaceChildOrdering;
    use CustomPageWorkspaceProjectionState;
    use CustomPageWorkspaceChildProjection;
    use CustomPageWorkspaceTargetHelpers;
    use CustomPageWorkspaceForms;
    use CustomPageWorkspaceSecondaryForms;
    use CustomPageWorkspacePresentationHelpers;

    private const PAGE_SIZES = [25, 50, 100];

    private const DEFAULT_PAGE_SIZE = 25;

    /** @var array<string, string> */
    private const COMPONENT_LABELS = [
        'image' => 'Image',
        'cv_list' => 'CV List',
        'text' => 'Rich Text',
        'list' => 'List',
        'divider' => 'Divider',
        'contact' => 'Contact',
        'legal_disclaimer' => 'Legal Disclaimer',
    ];

    /** @var array<string, string> */
    private const CONTACT_CHILD_LABELS = [
        'public_email' => 'Public Email',
        'social_links' => 'Social Media Links',
        'contact_form' => 'Contact Form',
    ];

    /** @var array<string, string> */
    private const DIVIDER_LABELS = [
        'thin' => 'Thin',
        'subtle' => 'Subtle',
        'strong' => 'Strong',
        'dotted' => 'Dotted',
    ];

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'pages/custom/{section}';

    protected static ?string $title = 'Custom Page';

    protected string $view = 'filament.pages.custom-page-workspace';

    public int $sectionId;

    public int $settingsId;

    public string $pageTitle = '';

    public ?string $publicUrl = null;

    public ?string $previewUrl = null;

    /** @var array<string, mixed> */
    public array $analytics = [];

    /** @var array<string, string> */
    public array $componentTypeOptions = self::COMPONENT_LABELS;

    /** @var array<string, string> */
    public array $dividerVariantOptions = self::DIVIDER_LABELS;

    /** @var array<string, string> */
    public array $availableSocialPlatforms = [];

    /** @var list<array{label:string,value:string,description:string}> */
    public array $metrics = [];

    /** @var list<array<string, mixed>> */
    public array $components = [];

    public int $unfilteredComponentCount = 0;

    public string $componentSearch = '';

    public string $componentType = 'any';

    /** @var list<string> */
    public array $selectedComponentTargets = [];

    /** @var list<string> */
    public array $selectedChildTargets = [];

    public bool $hasCvList = false;

    public int $cvEntryCount = 0;

    public int $page = 1;

    public int $pageSize = self::DEFAULT_PAGE_SIZE;

    public int $total = 0;

    public int $pages = 1;
}
