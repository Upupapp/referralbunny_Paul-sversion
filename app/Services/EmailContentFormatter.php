<?php

namespace App\Services;

class EmailContentFormatter
{
    /**
     * Strip control characters (keep newlines/tabs).
     */
    public static function sanitizePlainText(string $body): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $body);
    }

    /**
     * Replace safe http/https URLs in already-escaped text with clickable anchors.
     * Only http and https are allowed. javascript:, data:, vbscript: etc. are blocked.
     */
    public static function linkifySafeUrls(string $escapedText): string
    {
        // Matches http(s) URLs up to but not including trailing punctuation (.,:;!?)
        return preg_replace_callback(
            '#(https?://[^\s\'"&lt;&gt;]+[^\s\'"&lt;&gt;.,;:!?\)])#i',
            static function (array $m): string {
                $url = $m[1];
                // Re-validate: only allow http/https after HTML-decoding
                $decoded = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
                if (!preg_match('#^https?://#i', $decoded)) {
                    return $m[0]; // leave as-is if protocol not safe
                }
                $safe = htmlspecialchars($decoded, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return '<a href="' . $safe . '" target="_blank" rel="noopener noreferrer"'
                    . ' style="color:#7B61FF;text-decoration:underline;word-break:break-all">'
                    . htmlspecialchars($decoded, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</a>';
            },
            $escapedText
        );
    }

    /**
     * Full pipeline: sanitize → escape → linkify → preserve line breaks.
     * Returns safe HTML ready for injection into an email template.
     */
    public static function renderTaskResponseEmailBody(string $body): string
    {
        $safe    = static::sanitizePlainText($body);
        $escaped = htmlspecialchars($safe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $linked  = static::linkifySafeUrls($escaped);

        // Convert newlines to <br> (nl2br does not double-escape already-safe content)
        return nl2br($linked);
    }
}
