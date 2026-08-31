<?php

it('keeps Blog and Exhibitions on one Journal presentation source with one visual column', function (): void {
    $blog = trim((string) file_get_contents(resource_path('views/filament/resources/blog-posts/pages/list-blog-posts.blade.php')));
    $exhibitions = trim((string) file_get_contents(resource_path('views/filament/resources/exhibitions/pages/list-exhibitions.blade.php')));
    $view = (string) file_get_contents(resource_path('views/filament/pages/journal-workspace.blade.php'));

    expect($blog)->toBe("@include('filament.pages.journal-workspace')")
        ->and($exhibitions)->toBe($blog)
        ->and($view)->toContain('<th scope="col" class="journal-visual">Image</th>')
        ->and($view)->toContain("\$entry['thumbnail_url']")
        ->and($view)->toContain('class="journal-visual__thumbnail"')
        ->and($view)->toContain('aria-label="No image">—</span>')
        ->and($view)->toContain('<x-admin.toolbar>')
        ->and($view)->not->toContain('JournalEntryMedia::query')
        ->and($view)->not->toContain('::query(')
        ->and($view)->not->toContain('style=')
        ->and($view)->not->toContain('>View</');
});

it('keeps a permanent shared add row between the Journal table and pager for both templates', function (): void {
    $view = (string) file_get_contents(resource_path('views/filament/pages/journal-workspace.blade.php'));

    expect($view)
        ->toContain("\$entryLabelSingular = \$isBlog ? 'post' : 'exhibition';")
        ->toContain("</x-admin.table>\n\n            <x-admin.add-row wire:click=\"mountAction('{{ \$isBlog ? 'addPost' : 'addExhibition' }}')\">Add {{ \$entryLabelSingular }}</x-admin.add-row>\n\n            <footer class=\"admin-pager\">")
        ->toContain('class="journal-workspace__entries"');
});

it('projects Blog and Exhibition cover thumbnails before the shared Blade renders rows', function (): void {
    $workspace = (string) file_get_contents(app_path('Filament/Pages/JournalWorkspace.php'));

    expect(substr_count($workspace, "'thumbnail_url' => \$this->coverThumbnailUrl("))->toBe(2)
        ->and(substr_count($workspace, "where('role', JournalEntryMedia::ROLE_COVER)->with('mediaAsset.variants')"))->toBe(2)
        ->and($workspace)->toContain('private function coverThumbnailUrl(BlogPost|Exhibition $entry): ?string')
        ->and($workspace)->toContain("route('admin.media.variant', \$variant)");
});

it('keeps Journal visual and action geometry on the current shared data workspace contract', function (): void {
    $journalCss = (string) file_get_contents(resource_path('css/admin/journal.css'));
    $dataCss = (string) file_get_contents(resource_path('css/admin/data-workspace.css'));
    $taskCss = (string) file_get_contents(resource_path('css/admin/task-surfaces.css'));

    expect($journalCss)->toContain('.journal-visual {')
        ->and($journalCss)->toContain('width: 5rem;')
        ->and($journalCss)->toContain('.journal-visual__thumbnail {')
        ->and($journalCss)->toContain('width: 3.6rem;', 'height: 3rem;', 'object-fit: cover;')
        ->and($journalCss)->toContain('.admin-section.journal-workspace__entries {', 'border-bottom: 0;')
        ->and($taskCss)->toContain('.admin-table__actions {')
        ->and($taskCss)->toContain('text-align: left !important;')
        ->and($taskCss)->toContain('.admin-table__actions > .admin-toolbar {')
        ->and($taskCss)->toContain('justify-content: flex-start;')
        ->and($taskCss)->toContain('white-space: nowrap;')
        ->and($taskCss)->toContain('.admin-action--state {', 'min-width: 7.75rem;')
        ->and($taskCss)->not->toContain('.admin-bottom-add')
        ->and($dataCss)->toContain('.admin-pager,')
        ->and($dataCss)->not->toContain('.admin-action--state');
});

it('keeps the shared Journal editor Gallery source and the central rich text fix intact', function (): void {
    $schema = (string) file_get_contents(app_path('Filament/Support/JournalEntryEditorSchema.php'));
    $richText = (string) file_get_contents(app_path('Filament/Support/AdminRichText.php'));

    expect($schema)->toContain('return self::entry($schema, JournalTemplate::Blog);')
        ->and($schema)->toContain('return self::entry($schema, JournalTemplate::Exhibitions);')
        ->and(substr_count($schema, 'private static function galleryImages()'))->toBe(1)
        ->and($schema)->toContain("if (\$template === JournalTemplate::Exhibitions) {\n            \$components[] = self::mapSection();")
        ->and($richText)->toContain("'x-on:admin-rich-text-image-insert'")
        ->and($richText)->not->toContain('$el.addEventListener(\'admin-rich-text-image-insert\'');
});
