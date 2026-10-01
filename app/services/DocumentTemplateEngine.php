<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Replaces approved {{dotted.placeholders}} and escapes the values.
 * Templates cannot run PHP. Unknown placeholders are removed.
 */
final class DocumentTemplateEngine
{
    /** @var list<string> */
    public const ALLOWED = [
        'job.number',
        'job.title',
        'job.target_date',
        'job.priority',
        'job.status',
        'job.description',
        'job.po',
        'job.site',
        'customer.name',
        'customer.phone',
        'item.description',
        'item.quantity',
        'item.dimensions',
        'item.status',
        'company.name',
        'company.phone',
        'company.email',
        'company.address',
        'document.number',
        'document.date',
        'document.type',
        'dispatch.number',
        'dispatch.type',
        'signer.name',
    ];

    /**
     * @param array<string, scalar|null> $vars keys are dotted paths
     */
    public function render(string $template, array $vars): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/',
            static function (array $match) use ($vars): string {
                $key = $match[1];
                if (!in_array($key, self::ALLOWED, true)) {
                    return '';
                }
                $value = $vars[$key] ?? '';

                return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            },
            $template
        );
    }
}
