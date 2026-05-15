<?php

if (!function_exists('rb_linkify')) {
    /**
     * Escape HTML then make http(s) URLs into clickable anchor tags.
     * Safe: HTML is escaped before the regex, so no injection via text content.
     */
    function rb_linkify(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return (string) preg_replace(
            '/(https?:\/\/[^\s<>&"\'()\[\]{}]+)/i',
            '<a href="$1" target="_blank" rel="noopener noreferrer" style="color:#7B61FF;text-decoration:underline;word-break:break-all">$1</a>',
            $escaped
        );
    }
}
