from pathlib import Path

replacements = {
    'app/Domain/Content/PublicNavigationService.php': [
        ("""    /**
     * @return Collection<int, array{
     *     position:int,
     *     tie_breaker:int,
     *     label:string,
     *     url:?string,
     *     current:bool,
     *     active:bool,
     *     children:list<array{label:string,url:?string,current:bool}>
     * }>
     */
""", """    /** @return Collection<int, array<string, mixed>> */
"""),
        ("""        /** @var list<array{position:int,tie_breaker:int,label:string,url:?string,current:bool,active:bool,children:list<array{label:string,url:?string,current:bool}>}> $items */
        $items = [];
""", """        /** @var list<array<string, mixed>> $items */
        $items = [];
"""),
        ("""            $items[] = [
                'position' => (int) $section->getAttribute('position'),
                'tie_breaker' => (int) $section->getKey(),
                'label' => (string) $section->getAttribute('navigation_label'),
                'url' => $this->sectionUrl($section),
                'current' => $current,
                'active' => $current || $childCurrent,
                'children' => $children,
            ];
""", """            /** @var array<string, mixed> $item */
            $item = [
                'position' => (int) $section->getAttribute('position'),
                'tie_breaker' => (int) $section->getKey(),
                'label' => (string) $section->getAttribute('navigation_label'),
                'url' => $this->sectionUrl($section),
                'current' => $current,
                'active' => $current || $childCurrent,
                'children' => $children,
            ];
            $items[] = $item;
"""),
        ("""        return collect($items);
""", """        /** @var Collection<int, array<string, mixed>> $collection */
        $collection = collect($items);

        return $collection;
"""),
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
