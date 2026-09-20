<?php

namespace App\Services\QuickProgram;

use App\Models\ProgramConnection;

class TrackingOrigins
{
    public function normalize(string $input): string
    {
        return app(WebsiteAnalyzer::class)->normalize(trim($input));
    }

    public function forConnection(ProgramConnection $connection): array
    {
        if ($connection->tracking_origins) return $connection->tracking_origins;

        $origin = $this->normalize($connection->website);
        $host = parse_url($origin, PHP_URL_HOST);
        $other = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host;
        return array_values(array_unique([$origin, 'https://'.$other]));
    }

    public function accepts(ProgramConnection $connection, string $origin): bool
    {
        // Compare exact origins. Never allow arbitrary subdomains or suffix matches.
        return in_array($origin, $this->forConnection($connection), true);
    }
}
