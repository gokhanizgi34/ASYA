<?php

namespace App\Services;

use InvalidArgumentException;

class ExternalUrlGuard
{
    public function assertSafe(string $url, bool $resolveDns = true): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('Yalnızca geçerli HTTP veya HTTPS adresleri kullanılabilir.');
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new InvalidArgumentException('Yerel ağ adreslerine bağlantı kurulamaz.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPublicIp($host);

            return;
        }

        if (! $resolveDns) {
            return;
        }

        $addresses = $this->resolveAddresses($host);

        if ($addresses === []) {
            throw new InvalidArgumentException('Entegrasyon adresinin DNS kaydı iki denemede çözümlenemedi.');
        }

        foreach ($addresses as $address) {
            $this->assertPublicIp($address);
        }
    }

    /** @return array<int, string> */
    private function resolveAddresses(string $host): array
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $addresses = gethostbynamel($host);

            if (is_array($addresses) && $addresses !== []) {
                return array_values(array_unique($addresses));
            }

            $records = dns_get_record($host, DNS_A | DNS_AAAA);
            $recordAddresses = collect(is_array($records) ? $records : [])
                ->map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null)
                ->filter(fn (mixed $address): bool => is_string($address) && $address !== '')
                ->unique()
                ->values()
                ->all();

            if ($recordAddresses !== []) {
                return $recordAddresses;
            }

            usleep(100_000);
        }

        return [];
    }

    private function assertPublicIp(string $ip): void
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new InvalidArgumentException('Özel veya ayrılmış ağ adreslerine bağlantı kurulamaz.');
        }
    }
}
