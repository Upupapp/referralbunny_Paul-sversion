<?php
namespace App\Services\Platform;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
class GetHiredConnector
{
    public function request(string $path, array $data): array
    {
        if (!config('services.gethired.enabled') || strlen(config('services.gethired.client_secret', '')) < 32) throw ValidationException::withMessages(['platform'=>'GetHired connection is not available yet. Please try again later.']);
        $url = rtrim(config('services.gethired.api_url'), '/');
        abort_unless(str_starts_with($url, 'https://'), 503);
        try {
            $response = Http::withToken(config('services.gethired.client_secret'))->acceptJson()->timeout(15)->withoutRedirecting()->post($url.'/integrations/referral-bunny/'.$path, $data);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw ValidationException::withMessages(['platform' => 'GetHired could not be reached. Please try connecting again.']);
        }
        if (!$response->successful()) {
            throw ValidationException::withMessages(['platform' => $response->status() === 409
                ? 'GetHired is already linked to another program. Disconnect that program before connecting this one.'
                : 'The connection could not be completed. Please return to this page and try again.']);
        }
        if (!is_array($response->json())) throw ValidationException::withMessages(['platform'=>'GetHired returned an unexpected response. Please try again.']);
        return $response->json();
    }
}
