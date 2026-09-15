from pathlib import Path


def replace_exact(path: str, old: str, new: str) -> None:
    file = Path(path)
    text = file.read_text()
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"{path}: expected exactly one match, got {count}")
    file.write_text(text.replace(old, new, 1))


replace_exact(
    "tests/Feature/GeneralAdminWorkspaceTest.php",
    "use App\\Domain\\Content\\PublicAppearance;\n",
    "use App\\Domain\\Content\\PublicAppearance;\nuse App\\Domain\\Publication\\PublicationService;\n",
)

replace_exact(
    "tests/Feature/GeneralAdminWorkspaceTest.php",
    '''it('renders default solid and gradient appearance through a request-local CSP nonce', function (): void {
    $settings = PublicContentSetting::general();
    app(AdminSettingsService::class)->updatePublicContent($settings, [
        'background_mode' => null,
        'background_color' => null,
        'background_gradient_start' => null,
        'background_gradient_end' => null,
        'background_gradient_angle' => null,
    ]);

    $defaultResponse = $this->get(route('home'))->assertOk();
    $defaultCsp = (string) $defaultResponse->headers->get('Content-Security-Policy');
    $defaultHtml = (string) $defaultResponse->getContent();
    $defaultNonce = generalPublicStyleNonce($defaultResponse);

    expect($defaultCsp)->toContain("style-src 'self' 'nonce-{$defaultNonce}'")
        ->and($defaultCsp)->not->toContain("'unsafe-inline'")
        ->and($defaultHtml)->not->toContain('--public-page:');

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_mode' => 'solid',
        'background_color' => '#123456',
    ]);
    $solidResponse = $this->get(route('home'))->assertOk();
    $solidCsp = (string) $solidResponse->headers->get('Content-Security-Policy');
    $solidHtml = (string) $solidResponse->getContent();
    $solidNonce = generalPublicStyleNonce($solidResponse);

    expect($solidNonce)->not->toBe($defaultNonce)
        ->and($solidCsp)->toContain("'nonce-{$solidNonce}'")
        ->and($solidCsp)->not->toContain("'unsafe-inline'")
        ->and($solidHtml)->toContain('--public-page: #123456');
    generalAssertPublicStyleUsesNonce($solidHtml, $solidNonce, '--public-page: #123456');

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_mode' => 'gradient',
        'background_gradient_start' => '#112233',
        'background_gradient_end' => '#AABBCC',
        'background_gradient_angle' => 45,
    ]);
    $gradientResponse = $this->get(route('home'))->assertOk();
    $gradientCsp = (string) $gradientResponse->headers->get('Content-Security-Policy');
    $gradientHtml = (string) $gradientResponse->getContent();
    $gradientNonce = generalPublicStyleNonce($gradientResponse);

    expect($gradientNonce)->not->toBe($solidNonce)
        ->and($gradientCsp)->toContain("'nonce-{$gradientNonce}'")
        ->and($gradientCsp)->not->toContain("'unsafe-inline'")
        ->and($gradientHtml)->toContain('--public-page: linear-gradient(45deg, #112233, #AABBCC) fixed')
        ->and($gradientHtml)->not->toContain('javascript:');
    generalAssertPublicStyleUsesNonce(
        $gradientHtml,
        $gradientNonce,
        '--public-page: linear-gradient(45deg, #112233, #AABBCC) fixed',
    );
});''',
    '''it('renders only committed default solid and gradient appearance through a request-local CSP nonce', function (): void {
    $actor = User::query()->firstOrFail();
    $settings = PublicContentSetting::general();
    app(AdminSettingsService::class)->updatePublicContent($settings, [
        'background_mode' => null,
        'background_color' => null,
        'background_gradient_start' => null,
        'background_gradient_end' => null,
        'background_gradient_angle' => null,
    ]);

    $defaultResponse = $this->get(route('home'))->assertOk();
    $defaultCsp = (string) $defaultResponse->headers->get('Content-Security-Policy');
    $defaultHtml = (string) $defaultResponse->getContent();
    $defaultNonce = generalPublicStyleNonce($defaultResponse);

    expect($defaultCsp)->toContain("style-src 'self' 'nonce-{$defaultNonce}'")
        ->and($defaultCsp)->not->toContain("'unsafe-inline'")
        ->and($defaultHtml)->not->toContain('--public-page:');

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_mode' => 'solid',
        'background_color' => '#123456',
    ]);

    $stagedSolidHtml = (string) $this->get(route('home'))->assertOk()->getContent();
    expect($stagedSolidHtml)->not->toContain('--public-page: #123456');

    app(PublicationService::class)->commit($actor, 'Publish solid appearance');
    $solidResponse = $this->get(route('home'))->assertOk();
    $solidCsp = (string) $solidResponse->headers->get('Content-Security-Policy');
    $solidHtml = (string) $solidResponse->getContent();
    $solidNonce = generalPublicStyleNonce($solidResponse);

    expect($solidNonce)->not->toBe($defaultNonce)
        ->and($solidCsp)->toContain("'nonce-{$solidNonce}'")
        ->and($solidCsp)->not->toContain("'unsafe-inline'")
        ->and($solidHtml)->toContain('--public-page: #123456');
    generalAssertPublicStyleUsesNonce($solidHtml, $solidNonce, '--public-page: #123456');

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_mode' => 'gradient',
        'background_gradient_start' => '#112233',
        'background_gradient_end' => '#AABBCC',
        'background_gradient_angle' => 45,
    ]);

    $stagedGradientHtml = (string) $this->get(route('home'))->assertOk()->getContent();
    expect($stagedGradientHtml)->toContain('--public-page: #123456')
        ->and($stagedGradientHtml)->not->toContain('--public-page: linear-gradient(45deg, #112233, #AABBCC) fixed');

    app(PublicationService::class)->commit($actor, 'Publish gradient appearance');
    $gradientResponse = $this->get(route('home'))->assertOk();
    $gradientCsp = (string) $gradientResponse->headers->get('Content-Security-Policy');
    $gradientHtml = (string) $gradientResponse->getContent();
    $gradientNonce = generalPublicStyleNonce($gradientResponse);

    expect($gradientNonce)->not->toBe($solidNonce)
        ->and($gradientCsp)->toContain("'nonce-{$gradientNonce}'")
        ->and($gradientCsp)->not->toContain("'unsafe-inline'")
        ->and($gradientHtml)->toContain('--public-page: linear-gradient(45deg, #112233, #AABBCC) fixed')
        ->and($gradientHtml)->not->toContain('javascript:');
    generalAssertPublicStyleUsesNonce(
        $gradientHtml,
        $gradientNonce,
        '--public-page: linear-gradient(45deg, #112233, #AABBCC) fixed',
    );
});''',
)

replace_exact(
    "tests/Feature/GeneralAdminWorkspaceTest.php",
    '''        ->and($socialSource)->toContain('wire:blur="updateSocialLink')
        ->and($socialSource)->not->toContain('wire:model.debounce.300ms="data.social_links')''',
    '''        ->and($socialSource)->toContain("mountAction('editSocialLink'")
        ->and($socialSource)->toContain("mountAction('addSocialLink')")
        ->and($socialSource)->not->toContain('wire:model.debounce.300ms="data.social_links')''',
)

replace_exact(
    "tests/Feature/PublicationVersioningTest.php",
    '''it('keeps publication commit history permanent at the database boundary', function (): void {
    $checkpoint = PublicationCheckpoint::query()->latest('id')->firstOrFail();

    expect(fn () => DB::table('publication_checkpoints')->where('id', $checkpoint->getKey())->delete())
        ->toThrow(QueryException::class)
        ->and(PublicationCheckpoint::query()->whereKey($checkpoint->getKey())->exists())->toBeTrue();
});''',
    '''it('keeps publication commit history permanent at the database boundary', function (): void {
    $checkpoint = PublicationCheckpoint::query()->latest('id')->firstOrFail();
    $pdo = DB::connection()->getPdo();
    $pdo->exec('SAVEPOINT publication_history_guard');

    try {
        expect(fn () => DB::table('publication_checkpoints')->where('id', $checkpoint->getKey())->delete())
            ->toThrow(QueryException::class);
    } finally {
        $pdo->exec('ROLLBACK TO SAVEPOINT publication_history_guard');
    }

    expect(PublicationCheckpoint::query()->whereKey($checkpoint->getKey())->exists())->toBeTrue();
});''',
)

replace_exact(
    "tests/Feature/PublicationWorkflowTest.php",
    "it('defers deletion of files that still belong to the committed snapshot until Commit', function (): void {",
    "it('retains files referenced by a retained publication snapshot after a later Commit', function (): void {",
)
replace_exact(
    "tests/Feature/PublicationWorkflowTest.php",
    '''    Storage::disk(config('media.disk'))->assertMissing($asset->getAttribute('storage_key'));
    Storage::disk(config('media.disk'))->assertMissing($variant->getAttribute('storage_key'));
    expect(DB::table('publication_media_cleanups')->count())->toBe(0);''',
    '''    Storage::disk(config('media.disk'))->assertExists($asset->getAttribute('storage_key'));
    Storage::disk(config('media.disk'))->assertExists($variant->getAttribute('storage_key'));
    expect(DB::table('publication_media_cleanups')->count())->toBe(2);''',
)

replace_exact(
    "tests/Feature/SiteSectionArchitectureTest.php",
    '''        expect($child->canHaveParent())->toBeTrue()
            ->and($child->canContainChildren())->toBeTrue()
            ->and($child->canChangePlacement())->toBeTrue();''',
    '''        expect($child->canHaveParent())->toBeTrue()
            ->and($child->canContainChildren())->toBeTrue()
            ->and($child->canChangePlacement())->toBe($child !== SiteNodeType::Home);''',
)

replace_exact(
    "tests/Feature/SiteSectionPagesBrowserRepairTest.php",
    '''it('preserves Home publication delete and conversion guards even when Home is reparented', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);
    $home = SiteSection::query()->where('type', SiteNodeType::Home->value)->firstOrFail();
    $parent = $service->createCustomPage('Home Parent', 'home-parent-repair');

    expect($order->moveTo($home, (int) $parent->getKey(), 0))->toBeTrue();
    expect($home->refresh()->parent_id)->toBe((int) $parent->getKey())
        ->and($home->state)->toBe('published');

    expect(fn () => $service->updatePlacement($home, 'hidden', false, (int) $parent->getKey()))
        ->toThrow(ValidationException::class, 'Home is always published.')
        ->and(fn () => $service->convertType($home, SiteNodeType::CustomPage->value))
        ->toThrow(ValidationException::class, 'Home cannot be converted')
        ->and(fn () => $home->refresh()->delete())
        ->toThrow(ValidationException::class, 'Home cannot be deleted.');
});''',
    '''it('keeps Home fixed at the top level and preserves its publication delete and conversion guards', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);
    $home = SiteSection::query()->where('type', SiteNodeType::Home->value)->firstOrFail();
    $parent = $service->createCustomPage('Home Parent', 'home-parent-repair');

    expect(fn () => $order->moveTo($home, (int) $parent->getKey(), 0))
        ->toThrow(ValidationException::class, 'Home is always the first top-level page.');
    expect($home->refresh()->parent_id)->toBeNull()
        ->and($home->state)->toBe('published');

    expect(fn () => $service->updatePlacement($home, 'hidden', false, (int) $parent->getKey()))
        ->toThrow(ValidationException::class, 'Home is always published.')
        ->and(fn () => $service->convertType($home, SiteNodeType::CustomPage->value))
        ->toThrow(ValidationException::class, 'Home cannot be converted')
        ->and(fn () => $home->refresh()->delete())
        ->toThrow(ValidationException::class, 'Home cannot be deleted.');
});''',
)

replace_exact(
    "tests/Feature/StorageVisualizationContractTest.php",
    "it('uses canonical icon-labelled actions and the shared data-table add row in storage tables', function (): void {",
    "it('uses canonical icon-labelled actions in the storage table', function (): void {",
)
replace_exact(
    "tests/Feature/StorageVisualizationContractTest.php",
    '''        ->assertSee('Download')
        ->assertSee('Delete')
        ->assertSee('admin-add-row--data', false)
        ->assertDontSee('admin-add-row--compact', false);''',
    '''        ->assertSee('Download')
        ->assertSee('Delete')
        ->assertDontSee('admin-add-row--compact', false);''',
)
replace_exact(
    "tests/Feature/StorageVisualizationContractTest.php",
    '''    expect($dashboardView)
        ->toContain('<x-admin.storage-capacity-visual :capacity="$storage" compact />');''',
    '''    expect($dashboardView)
        ->toContain('<x-admin.storage-capacity-visual')
        ->toContain(':capacity="$storage"')
        ->toContain(':breakdown="$storage[\'breakdown\']"')
        ->toContain(':segments="$storage[\'segments\']"');''',
)
