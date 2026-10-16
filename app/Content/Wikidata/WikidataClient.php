<?php

namespace App\Content\Wikidata;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Runs SPARQL queries against the Wikidata Query Service.
 */
class WikidataClient
{
    public const string ENDPOINT = 'https://query.wikidata.org/sparql';

    /**
     * @return list<array<string, string>> One row per result: variable name => value.
     */
    public function select(string $sparql): array
    {
        $response = Http::withHeaders([
            // Wikidata asks every client to identify itself.
            'User-Agent' => config('quizly.wikidata.user_agent'),
            'Accept' => 'application/sparql-results+json',
        ])
            ->timeout(120)
            ->retry(3, fn (int $attempt) => $attempt * 10_000, fn (Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && in_array($e->response->status(), [429, 500, 502, 503, 504], true)))
            ->asForm()
            ->post(self::ENDPOINT, ['query' => $sparql])
            ->throw();

        // A query that times out part-way can come back as a cut-off body:
        // treat anything but a complete result as an error, never as "no rows".
        $bindings = $response->json('results.bindings');

        if (! is_array($bindings)) {
            throw new RuntimeException('Wikidata returned an incomplete or unexpected response.');
        }

        $rows = [];

        foreach ($bindings as $binding) {
            $row = [];

            foreach ((array) $binding as $variable => $value) {
                $row[(string) $variable] = (string) ($value['value'] ?? '');
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
