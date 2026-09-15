from pathlib import Path

replacements = {
    'app/Domain/Content/PublicNavigationService.php': [
        ("""        /** @var Collection<int, array{position:int,tie_breaker:int,label:string,url:?string,current:bool,active:bool,children:list<array{label:string,url:?string,current:bool}>}> $items */
        $items = $sections->map(function (SiteSection $section): array {
            /** @var EloquentCollection<int, SiteSection> $childSections */
            $childSections = $section->getRelation('children');
            /** @var list<array{label:string,url:?string,current:bool}> $children */
            $children = $childSections->map(fn (SiteSection $child): array => [
                'label' => (string) $child->getAttribute('navigation_label'),
                'url' => $this->sectionUrl($child),
                'current' => $this->routes->isCurrent($child),
            ])->values()->all();
            $childCurrent = collect($children)->contains(static fn (array $child): bool => $child['current']);
            $current = $this->routes->isCurrent($section);

            return [
                'position' => (int) $section->getAttribute('position'),
                'tie_breaker' => (int) $section->getKey(),
                'label' => (string) $section->getAttribute('navigation_label'),
                'url' => $this->sectionUrl($section),
                'current' => $current,
                'active' => $current || $childCurrent,
                'children' => $children,
            ];
        })->values();

        return $items;
""", """        /** @var list<array{position:int,tie_breaker:int,label:string,url:?string,current:bool,active:bool,children:list<array{label:string,url:?string,current:bool}>}> $items */
        $items = [];
        foreach ($sections as $section) {
            /** @var EloquentCollection<int, SiteSection> $childSections */
            $childSections = $section->getRelation('children');
            /** @var list<array{label:string,url:?string,current:bool}> $children */
            $children = $childSections->map(fn (SiteSection $child): array => [
                'label' => (string) $child->getAttribute('navigation_label'),
                'url' => $this->sectionUrl($child),
                'current' => $this->routes->isCurrent($child),
            ])->values()->all();
            $childCurrent = collect($children)->contains(static fn (array $child): bool => $child['current']);
            $current = $this->routes->isCurrent($section);

            $items[] = [
                'position' => (int) $section->getAttribute('position'),
                'tie_breaker' => (int) $section->getKey(),
                'label' => (string) $section->getAttribute('navigation_label'),
                'url' => $this->sectionUrl($section),
                'current' => $current,
                'active' => $current || $childCurrent,
                'children' => $children,
            ];
        }

        return collect($items);
"""),
    ],
    'app/Domain/Media/MediaStorageBreakdown.php': [
        ("""        $breakdown = [];
        foreach (self::AREA_LABELS as $key => $label) {
""", """        /** @var list<array{key:string,label:string,bytes:int,files:int,percent:float}> $breakdown */
        $breakdown = [];
        foreach (self::AREA_LABELS as $key => $label) {
"""),
    ],
    'app/Domain/Media/PublicMedia.php': [
        ("use Illuminate\\Database\\Eloquent\\Collection;\n", "use Illuminate\\Database\\Eloquent\\Builder;\nuse Illuminate\\Database\\Eloquent\\Collection;\n"),
        ("""            ->where(function ($usage): void {
                $usage->whereHas('blogPost', fn ($posts) => $posts
                    ->publiclyVisible()
                    ->whereHas('siteSection', fn ($section) => $section
                        ->where('type', SiteSectionType::Journal->value)
                        ->where('template', JournalTemplate::Blog->value)
                        ->where('state', 'published')))
""", """            ->where(function ($usage): void {
                $usage->whereHas('blogPost', function (Builder $posts): void {
                    (new BlogPost)->scopePubliclyVisible($posts)
                        ->whereHas('siteSection', fn ($section) => $section
                            ->where('type', SiteSectionType::Journal->value)
                            ->where('template', JournalTemplate::Blog->value)
                            ->where('state', 'published'));
                })
"""),
    ],
    'app/Filament/Pages/Activity.php': [
        ("                    ->action(fn (): mixed => $this->undo($receiptId)),", "                    ->action(function () use ($receiptId): void {\n                        $this->undo($receiptId);\n                    }),"),
    ],
    'app/Filament/Support/StorageWorkspaceOverview.php': [
        ("            $row['display_bytes'] = MediaStorageUnits::formatBytes((int) ($row['bytes'] ?? 0));", "            $row['display_bytes'] = MediaStorageUnits::formatBytes($row['bytes']);"),
    ],
}

applied = 0
missing = []
for filename, pairs in replacements.items():
    path = Path(filename)
    text = path.read_text()
    original = text
    for old, new in pairs:
        if old not in text:
            missing.append(f'{filename}: {old[:100]!r}')
            continue
        text = text.replace(old, new, 1)
        applied += 1
    if text != original:
        path.write_text(text)

print(f'Applied {applied} replacements.')
if missing:
    print('Skipped missing patterns:')
    print('\n'.join(missing))
    raise SystemExit(1)
