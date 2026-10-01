<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\NumberSequenceRepository;

/**
 * Document numbers such as SFQ-2026-0001.
 *
 * The prefix comes from settings. The sequence is a locked counter, not
 * COUNT(*) + 1, so two people saving at once cannot get the same number.
 * Invoice and purchase-order prefixes can use the same method later.
 */
final class NumberingService
{
    public function __construct(private readonly NumberSequenceRepository $sequences = new NumberSequenceRepository())
    {
    }

    public function opportunity(): string
    {
        return $this->next('opportunity', 'opportunity_prefix', 'SFO');
    }

    public function quote(): string
    {
        return $this->next('quote', 'quote_prefix', 'SFQ');
    }

    public function job(): string
    {
        return $this->next('job', 'job_prefix', 'SFJ');
    }

    public function invoice(): string
    {
        return $this->next('invoice', 'invoice_prefix', 'SFI');
    }

    public function payment(): string
    {
        return $this->next('payment', 'payment_prefix', 'SFPAY');
    }

    public function creditNote(): string
    {
        return $this->next('credit_note', 'credit_note_prefix', 'SFCN');
    }

    public function purchaseOrder(): string
    {
        return $this->next('purchase_order', 'po_prefix', 'SFPO');
    }

    public function goodsReceipt(): string
    {
        return $this->next('goods_receipt', 'grn_prefix', 'SFGRN');
    }

    public function roll(): string
    {
        return $this->next('roll', 'roll_prefix', 'ROL');
    }

    public function sheet(): string
    {
        return $this->next('sheet', 'sheet_prefix', 'SHT');
    }

    public function offcut(): string
    {
        return $this->next('offcut', 'offcut_prefix', 'OFC');
    }

    public function batch(): string
    {
        return $this->next('batch', 'batch_prefix', 'BAT');
    }

    public function survey(): string
    {
        return $this->next('site_survey', 'survey_prefix', 'SFS');
    }

    public function subcontract(): string
    {
        return $this->next('subcontract', 'subcontract_prefix', 'SFSUB');
    }

    public function estimate(): string
    {
        return $this->next('estimate', 'estimate_prefix', 'SFE');
    }

    public function lead(): string
    {
        return $this->next('lead', 'lead_prefix', 'SFL');
    }

    private function next(string $document, string $setting, string $fallback): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) SettingsService::get($setting, $fallback)) ?? '');
        if ($prefix === '') {
            $prefix = $fallback;
        }
        $year = date('Y');
        $key = $document . ':' . $year;

        $number = Database::transaction(fn (): int => $this->sequences->allocate($key));

        return $prefix . '-' . $year . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
