<?php

namespace App\Services;

class BackupLogParser
{
    /**
     * @return array{
     *     cloud: array{total: ?string, used: ?string, free: ?string, trashed: ?string},
     *     artifacts: list<array{type: string, name: string, bytes: int, human: string, uploaded: bool}>,
     *     restic: array{added: ?string, stored: ?string, files: ?int}|null,
     *     insufficient_storage: bool
     * }
     */
    public function parse(?string $log): array
    {
        $log = $log ?? '';

        return [
            'cloud' => $this->parseCloud($log),
            'artifacts' => $this->parseArtifacts($log),
            'restic' => $this->parseRestic($log),
            'insufficient_storage' => str_contains($log, '507 Insufficient Storage')
                || str_contains($log, 'Insufficient Storage'),
        ];
    }

    /** @return array{total: ?string, used: ?string, free: ?string, trashed: ?string} */
    private function parseCloud(string $log): array
    {
        $cloud = [
            'total' => null,
            'used' => null,
            'free' => null,
            'trashed' => null,
        ];

        if (preg_match('/Total:\s*(.+)/i', $log, $m)) {
            $cloud['total'] = trim($m[1]);
        }
        if (preg_match('/Used:\s*(.+)/i', $log, $m)) {
            $cloud['used'] = trim($m[1]);
        }
        if (preg_match('/Free:\s*(.+)/i', $log, $m)) {
            $cloud['free'] = trim($m[1]);
        }
        if (preg_match('/Trashed:\s*(.+)/i', $log, $m)) {
            $cloud['trashed'] = trim($m[1]);
        }

        return $cloud;
    }

    /** @return list<array{type: string, name: string, bytes: int, human: string, uploaded: bool}> */
    private function parseArtifacts(string $log): array
    {
        /** @var array<string, array{type: string, name: string, bytes: int, human: string, uploaded: bool}> $byKey */
        $byKey = [];

        if (preg_match_all('/\[backup\].*SIZE type=(\w+) name=(\S+) bytes=(\d+)(?: uploaded=(\w+))?/', $log, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $bytes = (int) $m[3];
                $key = $m[1].':'.$m[2];
                $byKey[$key] = [
                    'type' => $m[1],
                    'name' => $m[2],
                    'bytes' => $bytes,
                    'human' => $this->formatBytes($bytes),
                    'uploaded' => ($m[4] ?? 'no') === 'yes',
                ];
            }
        }

        return array_values($byKey);
    }

    /** @return array{added: ?string, stored: ?string, files: ?int}|null */
    private function parseRestic(?string $log): ?array
    {
        if ($log === null || ! str_contains($log, 'Added to the repository')) {
            return null;
        }

        $restic = [
            'added' => null,
            'stored' => null,
            'files' => null,
        ];

        if (preg_match('/Added to the repository:\s*([^(]+)\(([^)]+)\s+stored\)/', $log, $m)) {
            $restic['added'] = trim($m[1]);
            $restic['stored'] = trim($m[2]);
        }

        if (preg_match('/Files:\s+(\d+)\s+new/', $log, $m)) {
            $restic['files'] = (int) $m[1];
        }

        return $restic;
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KiB', 'MiB', 'GiB', 'TiB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return sprintf($value >= 10 ? '%.1f %s' : '%.2f %s', $value, $units[$unit]);
    }
}
