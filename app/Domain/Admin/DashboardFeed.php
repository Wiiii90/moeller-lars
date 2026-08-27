<?php

namespace App\Domain\Admin;

use Carbon\CarbonImmutable;

final class DashboardFeed
{
    /** @var array<string, string> */
    private const VIEWS = [
        'all' => 'All',
        'changelog' => 'Changelog',
        'announcement' => 'Announcements',
        'tutorial' => 'Tutorials',
    ];

    /** @return array<string, string> */
    public static function views(): array
    {
        return self::VIEWS;
    }

    /** @return list<array{id:string,type:string,type_label:string,date:string,date_display:string,title:string,body:string,link:string|null,link_label:string|null}> */
    public function items(string $search = '', string $view = 'all'): array
    {
        $view = array_key_exists($view, self::VIEWS) ? $view : 'all';
        $search = strtolower(trim($search));
        $source = config('dashboard-feed.items', []);
        if (! is_array($source)) {
            return [];
        }

        $items = [];
        foreach ($source as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = is_string($row['id'] ?? null) ? trim($row['id']) : '';
            $type = is_string($row['type'] ?? null) ? trim($row['type']) : '';
            $date = is_string($row['date'] ?? null) ? trim($row['date']) : '';
            $title = is_string($row['title'] ?? null) ? trim($row['title']) : '';
            $body = is_string($row['body'] ?? null) ? trim($row['body']) : '';

            if ($id === '' || ! isset(self::VIEWS[$type]) || $type === 'all' || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) !== 1 || $title === '' || $body === '') {
                continue;
            }

            if ($view !== 'all' && $type !== $view) {
                continue;
            }

            if ($search !== '' && ! str_contains(strtolower($title.' '.$body.' '.self::VIEWS[$type]), $search)) {
                continue;
            }

            $link = is_string($row['link'] ?? null) && trim($row['link']) !== '' ? trim($row['link']) : null;
            $linkLabel = is_string($row['link_label'] ?? null) && trim($row['link_label']) !== '' ? trim($row['link_label']) : null;

            $items[] = [
                'id' => $id,
                'type' => $type,
                'type_label' => self::VIEWS[$type],
                'date' => $date,
                'date_display' => CarbonImmutable::createFromFormat('Y-m-d', $date)->format('M j, Y'),
                'title' => $title,
                'body' => $body,
                'link' => $link,
                'link_label' => $linkLabel,
            ];
        }

        usort($items, static function (array $left, array $right): int {
            $dateOrder = strcmp($right['date'], $left['date']);

            return $dateOrder !== 0 ? $dateOrder : strcmp($left['id'], $right['id']);
        });

        return $items;
    }
}
