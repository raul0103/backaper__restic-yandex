<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PasstoreClient
{
    /**
     * @return list<array{name: string, host: string, user: string, port: int, password: string}>
     */
    public function fetchSshAccesses(): array
    {
        $token = (string) config('services.passtore_token');
        if ($token === '') {
            throw new RuntimeException('PASSTORE_TOKEN не задан в .env');
        }

        $base = rtrim((string) config('services.passtore_url'), '/');
        $page = 1;
        $items = [];

        do {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(60)
                ->get($base.'/api/access', [
                    'type' => 'ssh',
                    'page' => $page,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException(
                    "Passtore HTTP {$response->status()} на page={$page}: ".mb_substr($response->body(), 0, 300)
                );
            }

            $json = $response->json();
            if (! is_array($json)) {
                throw new RuntimeException("Passtore: некорректный JSON на page={$page}");
            }

            if (! empty($json['message']) && empty($json['data'])) {
                throw new RuntimeException('Passtore API: '.$json['message']);
            }

            $rows = $json['data'] ?? [];
            if (! is_array($rows) || $rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $data = $row['data'] ?? [];
                $host = trim((string) ($data['host'] ?? ''));
                if ($host === '') {
                    continue;
                }

                $items[] = [
                    'name' => (string) ($row['name'] ?? $host),
                    'host' => $host,
                    'user' => (string) ($data['login'] ?? 'root'),
                    'port' => (int) ($data['port'] ?? 22),
                    'password' => (string) ($data['password'] ?? ''),
                ];
            }

            $page++;
            $lastPage = (int) ($json['meta']['last_page'] ?? $page);
        } while ($page <= $lastPage && $rows !== []);

        return $items;
    }
}
