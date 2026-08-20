<?php

namespace App\Domain\Analytics;

final class AnalyticsReportAvailability
{
    /** @var array<string, string> */
    private const WARNING_BY_REPORT = [
        'content' => 'Content report is unavailable.',
        'entry_pages' => 'Entry pages report is unavailable.',
        'exit_pages' => 'Exit pages report is unavailable.',
        'downloads' => 'Downloads report is unavailable.',
        'outlinks' => 'Outlinks report is unavailable.',
        'site_searches' => 'Site searches report is unavailable.',
        'site_search_no_results' => 'Site search no results report is unavailable.',
        'events' => 'Events report is unavailable.',
        'event_categories' => 'Event categories report is unavailable.',
        'event_names' => 'Event names report is unavailable.',
        'referrers' => 'Referrers report is unavailable.',
        'referrer_websites' => 'Referrer websites report is unavailable.',
        'socials' => 'Socials report is unavailable.',
        'search_engines' => 'Search engines report is unavailable.',
        'campaigns' => 'Campaigns report is unavailable.',
        'ai_assistants' => 'Ai assistants report is unavailable.',
        'continents' => 'Continents report is unavailable.',
        'countries' => 'Countries report is unavailable.',
        'devices' => 'Devices report is unavailable.',
        'browsers' => 'Browsers report is unavailable.',
        'operating_systems' => 'Operating systems report is unavailable.',
        'visit_duration' => 'Visit duration report is unavailable.',
        'pages_per_visit' => 'Pages per visit report is unavailable.',
        'local_time' => 'Local time report is unavailable.',
        'day_of_week' => 'Day of week report is unavailable.',
        'artwork_events' => 'Per-artwork interaction report is unavailable.',
        'artwork_event_series' => 'Per-artwork interaction trend is unavailable.',
        'returning' => 'Returning-visitor report is unavailable.',
        'series' => 'Traffic time-series data is unavailable.',
    ];

    /** @param array<string, bool> $available */
    private function __construct(private readonly array $available) {}

    /** @param array<string, mixed> $report */
    public static function fromReport(array $report): self
    {
        $warnings = array_values(array_filter(
            $report['warnings'] ?? [],
            static fn (mixed $warning): bool => is_string($warning),
        ));

        $available = [];
        foreach (self::WARNING_BY_REPORT as $name => $warning) {
            $available[$name] = ! in_array($warning, $warnings, true);
        }

        return new self($available);
    }

    public function isAvailable(string $report): bool
    {
        return $this->available[$report] ?? true;
    }

    /** @param list<string> $reports */
    public function anyAvailable(array $reports): bool
    {
        foreach ($reports as $report) {
            if ($this->isAvailable($report)) {
                return true;
            }
        }

        return false;
    }
}
