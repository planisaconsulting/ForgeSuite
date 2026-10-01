<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Turns the saved customer-facing HTML into a PDF.
 * The HTML is rendered from the database first. Nothing is taken from an unsaved form.
 */
final class QuotePdf
{
    public function render(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    public function filename(string $quoteNumber, int $revision): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '-', $quoteNumber) ?? 'quote';
        $clean = trim($clean, '-');

        return ($clean === '' ? 'quote' : $clean) . '-R' . $revision . '.pdf';
    }
}
