from pathlib import Path

replacements = {
    'app/Domain/Admin/DashboardFeed.php': [
        ("        return collect(config('dashboard-feed.items', []))\n", "        /** @var Collection<int, array<string, mixed>> $items */\n        $items = collect(config('dashboard-feed.items', []))\n"),
        ("            ->filter()\n            ->values();\n    }\n\n    /** @return array<string, mixed> */\n    private function projectContact", "            ->filter()\n            ->values();\n\n        return $items;\n    }\n\n    /** @return array<string, mixed> */\n    private function projectContact"),
    ],
    'app/Domain/Content/CustomPageEditorialService.php': [
        ("        foreach ($targets as $target) {\n            if (! is_array($target) || ! is_int($target['index'] ?? null) || ! is_string($target['type'] ?? null)) {\n                throw ValidationException::withMessages(['component' => 'The component sequence is invalid.']);\n            }\n\n            $index = $target['index'];\n", "        foreach ($targets as $target) {\n            $index = $target['index'];\n"),
        ("        if ($itemIndex < 0 || ! array_key_exists($itemIndex, $items) || ! is_array($items[$itemIndex])) {", "        if ($itemIndex < 0 || ! array_key_exists($itemIndex, $items)) {"),
        ("            if (is_array($child) && ($child['type'] ?? null) === $childType) {", "            if (($child['type'] ?? null) === $childType) {"),
        ("            'legal_disclaimer' => ['type' => 'legal_disclaimer', 'published' => $published],\n        };", "            'legal_disclaimer' => ['type' => 'legal_disclaimer', 'published' => $published],\n            default => throw new InvalidArgumentException('Unsupported component type.'),\n        };"),
        ("            if (is_array($block) && ($block['type'] ?? null) === $type) {", "            if (($block['type'] ?? null) === $type) {"),
    ],
    'app/Domain/Content/HomeHeroConfigurationService.php': [
        ("            ? ($manualGroup[0]['artwork_id'] ?? null)", "            ? $manualGroup[0]['artwork_id']"),
    ],
    'app/Domain/Content/HomePresentationEditorialService.php': [
        ("        foreach ($targets as $target) {\n            if (! is_array($target)\n                || ! is_int($target['index'] ?? null)\n                || ! is_string($target['type'] ?? null)) {\n                throw ValidationException::withMessages([\n                    'component' => 'The selected Home component target is invalid.',\n                ]);\n            }\n\n            $index = $target['index'];\n", "        foreach ($targets as $target) {\n            $index = $target['index'];\n"),
    ],
    'app/Domain/Content/HomePresentationResolver.php': [
        ("            ->filter(fn (mixed $component): bool => is_array($component)\n                && ($component['type'] ?? null) === 'image'\n", "            ->filter(fn (array $component): bool => ($component['type'] ?? null) === 'image'\n"),
    ],
    'app/Domain/Content/PublicNavigationService.php': [
        ("        return $sections->map(function (SiteSection $section): array {", "        /** @var Collection<int, array{position:int,tie_breaker:int,label:string,url:?string,current:bool,active:bool,children:list<array{label:string,url:?string,current:bool}>}> $items */\n        $items = $sections->map(function (SiteSection $section): array {"),
        ("        })->values();\n    }\n\n    private function sectionUrl", "        })->values();\n\n        return $items;\n    }\n\n    private function sectionUrl"),
    ],
    'app/Domain/Content/RichTextMediaReference.php': [
        ("        $id = (int) ($matches[1] ?? 0);", "        $id = (int) $matches[1];"),
        ("            $id = (int) ($match[2] ?? 0);", "            $id = (int) $match[2];"),
        ("            $alt = self::unescapeAlt((string) ($match[1] ?? ''));", "            $alt = self::unescapeAlt($match[1]);"),
        ("            static fn (array $matches): string => (int) ($matches[2] ?? 0) === $mediaAssetId ? '' : (string) $matches[0],", "            static fn (array $matches): string => (int) $matches[2] === $mediaAssetId ? '' : (string) $matches[0],"),
        ("        foreach ($blocks as $block) {\n            if (! is_array($block)) {\n                continue;\n            }\n\n            if (($block['type'] ?? null) === 'text'", "        foreach ($blocks as $block) {\n            if (($block['type'] ?? null) === 'text'"),
    ],
    'app/Domain/Content/SiteSectionEditorialService.php': [
        ("            if ($source === SiteSectionType::Home || $target === SiteSectionType::Home) {", "            if ($source === SiteSectionType::Home) {"),
    ],
    'app/Domain/Media/MediaAssetEditorialService.php': [
        ("                foreach ($components as $index => $component) {\n                    if (! is_array($component)) {\n                        continue;\n                    }\n\n                    $type = $component['type'] ?? null;", "                foreach ($components as $index => $component) {\n                    $type = $component['type'] ?? null;"),
    ],
    'app/Domain/Media/MediaReferenceQuery.php': [
        ("        foreach ($sections as $section) {\n            if ($section instanceof SiteSection) {\n                $ids = array_merge($ids, $this->mediaIdsForJournalSection($section));\n            }\n        }", "        foreach ($sections as $section) {\n            $ids = array_merge($ids, $this->mediaIdsForJournalSection($section));\n        }"),
    ],
    'app/Domain/Media/MediaStorageBreakdown.php': [
        ("        $fileRows = [];", "        /** @var list<array<string, mixed>> $fileRows */\n        $fileRows = [];"),
        ("                $area = (string) ($reference['area'] ?? 'referenced');\n                $areaLabel = (string) ($reference['area_label'] ?? self::AREA_LABELS['referenced']);", "                $area = $reference['area'];\n                $areaLabel = $reference['area_label'];"),
        ("                $targetKey = (string) ($reference['target_key'] ?? '');", "                $targetKey = $reference['target_key'];"),
        ("                    'label' => (string) ($reference['target_label'] ?? $reference['label'] ?? 'Reference'),\n                    'area' => (string) ($reference['area'] ?? 'referenced'),\n                    'area_label' => (string) ($reference['area_label'] ?? self::AREA_LABELS['referenced']),", "                    'label' => $reference['target_label'],\n                    'area' => $reference['area'],\n                    'area_label' => $reference['area_label'],"),
        ("        foreach ($authoritativeFiles as $storageKey => $bytes) {\n            if (! is_string($storageKey) || $storageKey === '' || ! is_numeric($bytes) || (int) $bytes < 0) {\n                continue;\n            }\n\n            $normalized[$storageKey] = (int) $bytes;\n", "        foreach ($authoritativeFiles as $storageKey => $bytes) {\n            if ($storageKey === '' || $bytes < 0) {\n                continue;\n            }\n\n            $normalized[$storageKey] = $bytes;\n"),
    ],
    'app/Domain/Media/PublicMedia.php': [
        ("            foreach ($settings->components() as $block) {\n                if (! is_array($block) || ! CustomPageSetting::componentPublished($block)) {", "            foreach ($settings->components() as $block) {\n                if (! CustomPageSetting::componentPublished($block)) {"),
    ],
    'app/Domain/Publication/PublicationMediaCleanupService.php': [
        ("            ->filter(static fn (mixed $key): bool => is_string($key) && $key !== '')", "            ->filter(static fn (string $key): bool => $key !== '')"),
        ("        foreach (array_values(array_unique($storageKeys)) as $key) {\n            if (! is_string($key) || $key === '') {", "        foreach (array_values(array_unique($storageKeys)) as $key) {\n            if ($key === '') {"),
        ("            static fn (mixed $key): bool => is_string($key) && $key !== '',", "            static fn (string $key): bool => $key !== '',"),
    ],
    'app/Filament/Pages/Activity.php': [
        ("        $commitLatest = isset($commitSummary->latest_at) && $commitSummary->latest_at !== null\n", "        $commitLatest = isset($commitSummary->latest_at)\n"),
    ],
    'app/Filament/Pages/Concerns/CustomPageWorkspaceChildOrdering.php': [
        ("        $kind = $parts[0] ?? null;", "        $kind = $parts[0];"),
        ("            foreach ($entries as $entry) {\n                if ($entry instanceof CvEntry) {\n                    $changed = app(EditorialRecordService::class)->move($entry, $direction) || $changed;\n                }\n            }", "            foreach ($entries as $entry) {\n                $changed = app(EditorialRecordService::class)->move($entry, $direction) || $changed;\n            }"),
    ],
    'app/Filament/Pages/Concerns/CustomPageWorkspaceChildProjection.php': [
        ("            return array_values(array_map(function (CvEntry $entry, int $index) use ($count, $parentPublished, $reorderEnabled): array {", "            return array_map(function (CvEntry $entry, int $index) use ($count, $parentPublished, $reorderEnabled): array {"),
        ("            }, $cvRecords, array_keys($cvRecords)));", "            }, $cvRecords, array_keys($cvRecords));"),
        ("            foreach ($contactChildren as $childIndex => $child) {\n                if (! is_array($child)) {\n                    continue;\n                }\n                $childType = is_string($child['type'] ?? null) ? $child['type'] : '';", "            foreach ($contactChildren as $childIndex => $child) {\n                $childType = is_string($child['type'] ?? null) ? $child['type'] : '';"),
        ("                    'contact_form' => ($child['form_state'] ?? 'enabled') === 'under_construction'\n                        ? 'Under construction'\n                        : 'Enabled',\n                    default => '',", "                    'contact_form' => ($child['form_state'] ?? 'enabled') === 'under_construction'\n                        ? 'Under construction'\n                        : 'Enabled',"),
    ],
    'app/Filament/Pages/Concerns/CustomPageWorkspaceComponentActions.php': [
        ("            if (! is_string($target) || ! str_contains($target, ':')) {", "            if (! str_contains($target, ':')) {"),
    ],
    'app/Filament/Pages/Concerns/CustomPageWorkspaceTargetHelpers.php': [
        ("            if (! is_string($target) || ! str_contains($target, ':')) {", "            if (! str_contains($target, ':')) {"),
        ("            if (! is_string($target)) {\n                continue;\n            }\n            $parts = explode(':', $target);", "            $parts = explode(':', $target);"),
        ("            if (($parts[0] ?? null) === 'cv'", "            if ($parts[0] === 'cv'"),
        ("            } elseif (($parts[0] ?? null) === 'list'", "            } elseif ($parts[0] === 'list'"),
        ("            } elseif (($parts[0] ?? null) === 'contact'", "            } elseif ($parts[0] === 'contact'"),
        ("        foreach ($this->settings()->contactChildren($block) as $child) {\n            if (is_array($child) && ($child['type'] ?? null) === $childType) {", "        foreach ($this->settings()->contactChildren($block) as $child) {\n            if (($child['type'] ?? null) === $childType) {"),
    ],
    'app/Filament/Pages/Concerns/GalleryWorkspaceArtworkActions.php': [
        ("            static fn (mixed $candidate): bool => is_array($candidate)\n                && (int) ($candidate['id'] ?? 0) === (int) $artwork->getKey(),", "            static fn (array $candidate): bool => (int) ($candidate['id'] ?? 0) === (int) $artwork->getKey(),"),
        ("            $altOverride = trim((string) ($primary?->getAttribute('alt_text_override') ?? ''));", "            $altOverride = trim((string) ($primary->getAttribute('alt_text_override') ?? ''));"),
    ],
    'app/Filament/Pages/Concerns/GalleryWorkspaceDirectUpload.php': [
        ("        $uploads = array_values(array_filter(\n            $this->directPrimaryMedia,\n            static fn (mixed $upload): bool => $upload instanceof TemporaryUploadedFile,\n        ));", "        $uploads = $this->directPrimaryMedia;"),
    ],
    'app/Filament/Pages/Concerns/GalleryWorkspaceMoveActions.php': [
        ("            (! is_int($artworkId) && (! is_string($artworkId) || ! ctype_digit($artworkId)))\n            || (! is_int($position) && (! is_string($position) || ! ctype_digit($position)))", "            (! is_int($artworkId) && ! ctype_digit($artworkId))\n            || (! is_int($position) && ! ctype_digit($position))"),
    ],
    'app/Filament/Pages/Concerns/ManagesSitePageEditDialog.php': [
        ("                $section = $this->dialogSection($arguments);\n\n                return [\n                    'type' => $section->nodeType()->value,\n                    'template' => $section->journalTemplate()?->value ?? JournalTemplate::Blog->value,", "                $section = $this->dialogSection($arguments);\n                $template = $section->journalTemplate();\n\n                return [\n                    'type' => $section->nodeType()->value,\n                    'template' => $template === null ? JournalTemplate::Blog->value : $template->value,"),
        ("                ->action(fn (): mixed => $this->toggleSectionState($sectionId)),", "                ->action(function () use ($sectionId): void {\n                    $this->toggleSectionState($sectionId);\n                }),"),
    ],
    'app/Filament/Pages/Dashboard.php': [
        ("                ->action(fn (array $arguments): mixed => $this->deleteFeedEntry((string) ($arguments['key'] ?? ''))),", "                ->action(function (array $arguments): void {\n                    $this->deleteFeedEntry((string) ($arguments['key'] ?? ''));\n                }),"),
        ("                ->action(fn (): mixed => $this->bulkDelete()),", "                ->action(function (): void {\n                    $this->bulkDelete();\n                }),"),
        ("        return collect($this->selectedFeedKeys)\n            ->filter(static fn (mixed $key): bool => is_string($key) && $key !== '')", "        return collect($this->selectedFeedKeys)\n            ->filter(static fn (string $key): bool => $key !== '')"),
        ("            ->action(fn (): mixed => $isRead ? $this->markFeedUnread($key) : $this->markFeedRead($key));", "            ->action(function () use ($isRead, $key): void {\n                if ($isRead) {\n                    $this->markFeedUnread($key);\n\n                    return;\n                }\n\n                $this->markFeedRead($key);\n            });"),
        ("            $deleteAction->action(fn (): mixed => $this->deleteContactMessage($contactId));", "            $deleteAction->action(function () use ($contactId): void {\n                $this->deleteContactMessage($contactId);\n            });"),
        ("            $deleteAction->action(fn (): mixed => $this->deleteNotification($notificationId));", "            $deleteAction->action(function () use ($notificationId): void {\n                $this->deleteNotification($notificationId);\n            });"),
    ],
    'app/Filament/Pages/HomePresentation.php': [
        ("            if ($required && $index === 0) {\n                $field->required(fn (callable $get): bool => $get($stateField) === $stateValue);\n            }", "            if ($required && $index === 0 && $field instanceof \\Filament\\Forms\\Components\\MarkdownEditor) {\n                $field->required(fn (callable $get): bool => $get($stateField) === $stateValue);\n            }"),
        ("            if (! is_string($target) || ! $available->has($target)) {", "            if (! $available->has($target)) {"),
    ],
    'app/Filament/Pages/SitePages.php': [
        ("                    $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' && $data['parent_id'] !== null", "                    $parentId = isset($data['parent_id']) && $data['parent_id'] !== ''"),
    ],
    'app/Filament/Resources/MediaAssets/Pages/ListMediaAssets.php': [
        ("        $uploads = array_values(array_filter(\n            $this->directMedia,\n            static fn (mixed $upload): bool => $upload instanceof TemporaryUploadedFile,\n        ));", "        $uploads = $this->directMedia;"),
        ("\n    private function hasReferences(MediaAsset $asset): bool\n    {\n        return $this->assetReferences($asset) !== [];\n    }\n", ""),
    ],
    'app/Filament/Support/AdminPublicationHistory.php': [
        ("use Illuminate\\Pagination\\LengthAwarePaginator;", "use Illuminate\\Pagination\\LengthAwarePaginator;\nuse Illuminate\\Support\\Facades\\DB;"),
        ("        $activeDays = (clone $query)\n            ->toBase()\n            ->selectRaw($dateExpression.' AS bucket')\n            ->groupByRaw($dateExpression)\n            ->get()\n            ->count();", "        $activeDaysQuery = (clone $query)\n            ->toBase()\n            ->selectRaw($dateExpression.' AS bucket')\n            ->groupByRaw($dateExpression);\n        $activeDays = DB::query()->fromSub($activeDaysQuery, 'active_days')->count();"),
    ],
    'app/Filament/Support/DashboardOverview.php': [
        ("        $capacity = is_array($snapshot['capacity'] ?? null) ? $snapshot['capacity'] : [];\n        $breakdown = is_array($snapshot['breakdown'] ?? null) ? $snapshot['breakdown'] : [];\n        $attention = is_array($snapshot['attention'] ?? null) ? $snapshot['attention'] : [];", "        $capacity = $snapshot['capacity'];\n        $breakdown = $snapshot['breakdown'];\n        $attention = $snapshot['attention'];"),
        ("        $hourly = is_array($overview['hourly'] ?? null) ? $overview['hourly'] : [];", "        $hourly = $overview['hourly'];"),
        ("            'recent_changes' => (int) ($overview['total'] ?? 0),", "            'recent_changes' => $overview['total'],"),
    ],
    'app/Filament/Support/Dialogs/InteractsWithAdminEditDialogAutosave.php': [
        ("        $record = method_exists($action, 'getRecord') ? $action->getRecord() : null;", "        $record = $action->getRecord();"),
    ],
    'app/Filament/Support/MediaStorageReferenceCatalog.php': [
        ("            $type = trim((string) ($reference['type'] ?? ''));\n            $label = trim((string) ($reference['label'] ?? ''));\n            $url = isset($reference['url']) && is_string($reference['url']) ? $reference['url'] : null;", "            $type = trim($reference['type']);\n            $label = trim($reference['label']);\n            $url = $reference['url'];"),
    ],
    'app/Filament/Support/StorageWorkspaceOverview.php': [
        ("            ? (string) ($uncatalogued['display_bytes'] ?? '0 B')", "            ? (string) $uncatalogued['display_bytes']"),
        ("            $row['display_bytes'] = MediaStorageUnits::formatBytes((int) ($row['bytes'] ?? 0));", "            $row['display_bytes'] = MediaStorageUnits::formatBytes((int) $row['bytes']);"),
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
