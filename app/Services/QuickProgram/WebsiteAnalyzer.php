<?php

namespace App\Services\QuickProgram;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class WebsiteAnalyzer
{
    public function normalize(string $input): string
    {
        $url = str_contains($input, '://') ? trim($input) : 'https://'.trim($input);
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user'], $parts['pass'])
            || isset($parts['user']) || isset($parts['port']) || isset($parts['query']) || isset($parts['fragment'])
            || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)
            || preg_match('/\.(local|localhost|internal|test|invalid)$/', $host)) {
            throw ValidationException::withMessages(['website' => 'Enter a public company domain, such as example.com.']);
        }
        return 'https://'.$host;
    }

    public function analyze(string $input): array
    {
        $origin = $this->normalize($input);
        return Cache::remember('quick-program.website.'.hash('sha256', $origin), now()->addHours(12), function () use ($origin) {
            $result = ['website' => $origin, 'company_name' => '', 'description' => '', 'pricing_model' => 'unknown', 'prices' => [], 'sources' => [], 'scanned_at' => now()->toIso8601String()];
            $texts = [];
            foreach (['', '/pricing', '/plans'] as $path) {
                try { $html = $this->fetch($origin.$path); } catch (\RuntimeException) { continue; }
                if (!$html) continue;
                $parsed = $this->extract($html, $origin.$path);
                $result['company_name'] = $result['company_name'] ?: $parsed['company_name'];
                $result['description'] = $result['description'] ?: $parsed['description'];
                $result['prices'] = array_slice(array_merge($result['prices'], $parsed['prices']), 0, 8);
                $result['sources'][] = $origin.$path;
                $texts[] = $parsed['text'];
            }
            $text = implode(' ', $texts);
            if (preg_match('/per month|\/month|monthly|annually|subscription|per year/i', $text)) $result['pricing_model'] = 'subscription';
            elseif (preg_match('/one.time|pay once|single purchase/i', $text)) $result['pricing_model'] = 'one_time';
            elseif (preg_match('/pay.as.you.go|per credit|usage.based/i', $text)) $result['pricing_model'] = 'usage';
            $result['message'] = $result['prices'] ? 'These are possible public prices. Confirm the plan and billing period before continuing.' : 'We could not confirm public pricing. Enter a price below, or continue without one.';
            return $result;
        });
    }

    // No redirects, proxy, cookies or JavaScript. Pin the validated public DNS address
    // to prevent DNS rebinding; bound response size and total request time.
    protected function fetch(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $ips = array_values(array_filter(array_map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null, $records ?: [])));
        if (!$ips) throw new \RuntimeException('No public address.');
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)) throw new \RuntimeException('Non-public destination.');
        }
        $ip = str_contains($ips[0], ':') ? '['.$ips[0].']' : $ips[0];
        $body = '';
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RESOLVE => ["$host:443:$ip"], CURLOPT_PROXY => '',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 6,
            CURLOPT_USERAGENT => 'ReferralBunny-PricingPreview/1.0',
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => function ($handle, $chunk) use (&$body) {
                if (strlen($body) + strlen($chunk) > 524288) return 0;
                $body .= $chunk; return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE) ?: '';
        curl_close($curl);
        if (!$ok || $status !== 200 || !str_contains($type, 'text/html')) throw new \RuntimeException('Page unavailable.');
        return $body;
    }

    public function extract(string $html, string $source): array
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($doc);
        $name = $xpath->evaluate('string(//meta[@property="og:site_name"]/@content)') ?: $xpath->evaluate('string(//title)');
        $description = $xpath->evaluate('string(//meta[@name="description"]/@content)') ?: $xpath->evaluate('string(//meta[@property="og:description"]/@content)');
        foreach ($xpath->query('//script|//style|//noscript') as $node) $node->parentNode->removeChild($node);
        $text = preg_replace('/\s+/u', ' ', $doc->textContent);
        preg_match_all('/(PHP|USD|EUR|GBP|₱|\$|€|£)\s*([0-9][0-9,]*(?:\.[0-9]{1,2})?)/u', $text, $matches, PREG_SET_ORDER);
        $prices = [];
        foreach (array_slice($matches, 0, 8) as $m) {
            $prices[] = ['amount' => (float) str_replace(',', '', $m[2]), 'currency' => ['₱'=>'PHP', '€'=>'EUR', '£'=>'GBP', '$'=>'USD'][$m[1]] ?? $m[1], 'source' => $source, 'needs_confirmation' => true];
        }
        return ['description' => mb_substr(trim($description), 0, 300), 'company_name' => mb_substr(trim($name), 0, 100), 'text' => mb_substr($text, 0, 80000), 'prices' => $prices];
    }
}
