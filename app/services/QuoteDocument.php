<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\View;
use App\Repositories\QuoteRepository;

/**
 * Customer-facing quotation HTML and a stored PDF for email.
 */
final class QuoteDocument
{
    public function html(array $quote, ?int $revision = null, bool $print = false): string
    {
        $revision = $revision ?? (int) $quote['revision_number'];
        $items = (new QuoteRepository())->items((int) $quote['id']);
        $sections = (new QuoteRepository())->sections((int) $quote['id']);
        $shown = $quote;
        if ($revision !== (int) $quote['revision_number']) {
            $snapshot = (new QuoteService())->revisionSnapshot((int) $quote['id'], $revision);
            if ($snapshot === null) {
                $revision = (int) $quote['revision_number'];
            } else {
                $shown = $snapshot['quote'];
                $items = $snapshot['items'];
                $sections = $snapshot['sections'] ?? [];
                $shown['quote_number'] = $quote['quote_number'];
            }
        }

        return View::capture('quotes/pdf_shell', [
            'quote' => $shown,
            'items' => $items,
            'sections' => $sections,
            'print' => $print,
            'company' => [
                'name' => SettingsService::get('company_name', 'Sign-Forge Signs'),
                'trading_name' => SettingsService::get('trading_name', ''),
                'registration' => SettingsService::get('company_registration', ''),
                'vat' => SettingsService::get('vat_number', ''),
                'address' => SettingsService::get('address', ''),
                'telephone' => SettingsService::get('telephone', ''),
                'email' => SettingsService::get('email', ''),
                'website' => SettingsService::get('website', ''),
                'symbol' => SettingsService::symbol(),
            ],
        ]);
    }

    public function pdfFile(array $quote): string
    {
        $pdf = new QuotePdf();
        $binary = $pdf->render($this->html($quote));
        $dir = base_path('storage/quotes');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . '/' . $pdf->filename((string) $quote['quote_number'], (int) $quote['revision_number']);
        file_put_contents($path, $binary);

        return $path;
    }
}
