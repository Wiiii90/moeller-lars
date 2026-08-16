<?php

namespace App\Domain\Analytics;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use LogicException;
use Throwable;

final class MatomoReportingClient
{
    private const METRICS = [
        'nb_visits',
        'nb_uniq_visitors',
        'nb_actions',
        'nb_actions_per_visit',
        'avg_time_on_site',
        'bounce_rate',
    ];

    public function __construct(private readonly MatomoConfiguration $configuration) {}

    /** @return array{status:string,metrics?:array<string,int|float|string>,message?:string} */
    public function summary(): array
    {
        if (! (bool) config('analytics.matomo.enabled')) {
            return ['status' => 'disabled', 'message' => 'Matomo tracking is disabled.'];
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout($this->configuration->timeoutSeconds())
                ->post($this->configuration->baseUrl().'/index.php', [
                    'module' => 'API',
                    'method' => 'VisitsSummary.get',
                    'idSite' => $this->configuration->siteId(),
                    'period' => 'range',
                    'date' => now()->subDays(29)->toDateString().','.now()->toDateString(),
                    'format' => 'JSON',
                    'token_auth' => $this->configuration->apiToken(),
                ]);

            if (! $response->successful()) {
                return ['status' => 'unavailable', 'message' => 'Matomo Reporting API returned HTTP '.$response->status().'.'];
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return ['status' => 'unavailable', 'message' => 'Matomo Reporting API returned malformed JSON.'];
            }
            if (isset($payload['result']) && $payload['result'] === 'error') {
                return ['status' => 'unavailable', 'message' => 'Matomo Reporting API rejected the request.'];
            }

            $metrics = [];
            foreach (self::METRICS as $metric) {
                if (! array_key_exists($metric, $payload) || (! is_numeric($payload[$metric]) && ! is_string($payload[$metric]))) {
                    return ['status' => 'unavailable', 'message' => 'Matomo Reporting API omitted required aggregate metric '.$metric.'.'];
                }
                $metrics[$metric] = is_numeric($payload[$metric]) ? (float) $payload[$metric] : (string) $payload[$metric];
            }

            return ['status' => 'available', 'metrics' => $metrics];
        } catch (ConnectionException) {
            return ['status' => 'unavailable', 'message' => 'Matomo Reporting API is unreachable.'];
        } catch (LogicException $exception) {
            return ['status' => 'unavailable', 'message' => $exception->getMessage()];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'message' => 'Matomo Reporting API failed unexpectedly.'];
        }
    }
}
