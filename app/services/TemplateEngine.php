<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Replaces a fixed list of {{placeholders}}.
 * Anything else, including PHP, is left as text and is never executed.
 */
final class TemplateEngine
{
    /** @var list<string> */
    public const ALLOWED = [
        'customer_name',
        'contact_name',
        'quote_number',
        'quote_total',
        'quote_expiry',
        'job_number',
        'invoice_number',
        'invoice_balance',
        'invoice_due_date',
        'portal_link',
        'company_name',
    ];

    /**
     * @param array<string, scalar|null> $vars
     */
    public function render(string $template, array $vars): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/',
            static function (array $match) use ($vars): string {
                $key = $match[1];
                if (!in_array($key, self::ALLOWED, true)) {
                    return '';
                }

                return (string) ($vars[$key] ?? '');
            },
            $template
        );
    }
}
