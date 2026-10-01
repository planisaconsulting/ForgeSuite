<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InventoryRepository;
use App\Repositories\JobRepository;
use App\Repositories\WorkshopRepository;

/**
 * Configurable label layouts. A reprint records who printed it and does not
 * create another inventory item.
 */
final class LabelService
{
    public function __construct(
        private readonly WorkshopRepository $workshop = new WorkshopRepository(),
        private readonly TrackingCodeService $tracking = new TrackingCodeService(),
        private readonly Code128 $barcode = new Code128(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @return array{html: string, template: array<string, mixed>, vars: array<string, string>}
     */
    public function preview(string $entityType, int $entityId, ?int $templateId, int $copies, int $userId, bool $reprint, string $reason = ''): array
    {
        if ($reprint && !can('labels.reprint')) {
            return ['html' => '', 'template' => [], 'vars' => [], 'error' => 'You cannot reprint a label.'];
        }
        if (!$reprint && !can('labels.print') && !can('labels.reprint')) {
            return ['html' => '', 'template' => [], 'vars' => [], 'error' => 'You cannot print labels.'];
        }
        $entityType = strtoupper($entityType);
        $template = $templateId !== null && $templateId > 0
            ? $this->workshop->labelTemplate($templateId)
            : ($this->workshop->labelTemplates($entityType)[0] ?? null);
        if ($template === null && $entityType === 'PACKAGE') {
            $template = $this->workshop->labelTemplates('JOB')[0] ?? null;
        }
        if ($template === null) {
            return ['html' => '', 'template' => [], 'vars' => [], 'error' => 'Choose a label template.'];
        }
        $vars = $this->variables($entityType, $entityId, $userId);
        if ($vars === null) {
            return ['html' => '', 'template' => [], 'vars' => [], 'error' => 'That record was not found.'];
        }
        $layout = json_decode((string) $template['layout_definition'], true);
        if (!is_array($layout)) {
            $layout = ['title' => (string) $template['name'], 'lines' => ['{{code}}']];
        }
        $lines = [];
        foreach ($layout['lines'] ?? [] as $line) {
            $lines[] = $this->fill((string) $line, $vars);
        }
        $company = (string) SettingsService::get('company_name', '');
        $html = $this->sheet($company, (string) ($layout['title'] ?? $template['name']), $lines, $vars, $template, max(1, $copies));
        if ($reprint) {
            $this->workshop->insertReprintLog([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'template_id' => (int) $template['id'],
                'quantity' => max(1, $copies),
                'reason' => $reason !== '' ? $reason : null,
                'printed_by' => $userId,
            ]);
            $this->audit->record($entityType, $entityId, 'LABEL_REPRINTED', null, [
                'template_id' => (int) $template['id'],
                'quantity' => max(1, $copies),
                'reason' => $reason,
            ], $userId);
        }

        return ['html' => $html, 'template' => $template, 'vars' => $vars, 'error' => ''];
    }

    /**
     * @param array<string, string> $vars
     * @param array<string, mixed> $template
     * @param list<string> $lines
     */
    private function sheet(string $company, string $title, array $lines, array $vars, array $template, int $copies): string
    {
        $width = (string) $template['width_mm'];
        $height = (string) $template['height_mm'];
        $blocks = '';
        for ($i = 0; $i < $copies; $i++) {
            $body = '';
            foreach ($lines as $line) {
                if ($line === '') {
                    continue;
                }
                $body .= '<p>' . $line . '</p>';
            }
            $blocks .= '<article class="label"><p class="brand">' . e($company) . '</p><h1>' . e($title) . '</h1>'
                . $body . '<div class="codes"><div class="qr">' . e((string) $vars['url']) . '</div>'
                . $vars['barcode'] . '</div></article>';
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Label</title><style>
            body{font-family:DejaVu Sans,sans-serif;color:#111;margin:8mm}
            .label{width:' . e($width) . 'mm;min-height:' . e($height) . 'mm;border:1px solid #111;padding:3mm;margin:0 4mm 4mm 0;display:inline-block;vertical-align:top}
            h1{font-size:12px;margin:0 0 2mm;letter-spacing:.08em}
            p{margin:0 0 1mm;font-size:11px}
            .brand{font-size:10px;letter-spacing:.12em}
            .qr{font-size:8px;word-break:break-all}
            svg{width:40mm;height:14mm}
            @media print{.no-print{display:none}}
            </style></head><body><p class="no-print"><button onclick="window.print()">Print</button></p>' . $blocks . '</body></html>';
    }

    /**
     * @return array<string, string>|null
     */
    private function variables(string $entityType, int $entityId, int $userId): ?array
    {
        if ($entityType === 'JOB') {
            $job = (new JobRepository())->find($entityId);
            if ($job === null) {
                return null;
            }
            $issued = $this->tracking->issue('JOB', $entityId, (string) $job['job_number'], $userId);

            return $this->pack($issued, [
                'product' => (string) $job['title'],
                'description' => (string) $job['title'],
                'customer' => customer_label($job),
                'quantity' => '',
                'dimensions' => '',
                'due' => (string) ($job['target_date'] ?? ''),
                'location' => '',
                'width' => '',
                'original' => '',
                'remaining' => '',
            ]);
        }
        if (in_array($entityType, ['ROLL', 'SHEET', 'OFFCUT', 'INVENTORY_ITEM'], true)) {
            $item = (new InventoryRepository())->item($entityId);
            if ($item === null) {
                return null;
            }
            $type = strtoupper((string) $item['inventory_type']);
            $issued = $this->tracking->issue($type, $entityId, (string) $item['inventory_code'], $userId);
            $dims = trim((string) ($item['width_mm'] ?? '') . ' × ' . (string) ($item['height_mm'] ?? ''), ' ×');

            return $this->pack($issued, [
                'product' => (string) ($item['product_name'] ?? ''),
                'description' => (string) ($item['product_name'] ?? ''),
                'customer' => '',
                'quantity' => (string) $item['remaining_quantity'],
                'dimensions' => $dims,
                'due' => '',
                'location' => (string) ($item['location_code'] ?? $item['location_name'] ?? ''),
                'width' => (string) ($item['width_mm'] ?? '') . ((string) ($item['width_mm'] ?? '') !== '' ? ' mm' : ''),
                'original' => (string) $item['original_quantity'] . ' ' . (string) $item['unit'],
                'remaining' => (string) $item['remaining_quantity'] . ' ' . (string) $item['unit'],
            ]);
        }
        if ($entityType === 'PACKAGE') {
            $package = $this->workshop->package($entityId);
            if ($package === null) {
                return null;
            }
            $job = (new JobRepository())->find((int) $package['job_id']);
            $issued = $this->tracking->issue('PACKAGE', $entityId, (string) $package['package_code'], $userId);

            return $this->pack($issued, [
                'product' => (string) $package['description'],
                'description' => (string) $package['description'],
                'customer' => $job === null ? '' : customer_label($job),
                'quantity' => (string) count($this->workshop->packageItems($entityId)),
                'dimensions' => '',
                'due' => $job === null ? '' : (string) ($job['target_date'] ?? ''),
                'location' => '',
                'width' => '',
                'original' => '',
                'remaining' => '',
            ]);
        }
        if ($entityType === 'PRODUCTION_ITEM') {
            $item = $this->workshop->productionItem($entityId);
            if ($item === null) {
                return null;
            }
            $job = (new JobRepository())->find((int) $item['job_id']);
            $issued = $this->tracking->issue('PRODUCTION_ITEM', $entityId, (string) $item['tracking_code'], $userId);

            return $this->pack($issued, [
                'product' => (string) $item['description'],
                'description' => (string) $item['description'],
                'customer' => $job === null ? '' : customer_label($job),
                'quantity' => (string) $item['quantity'],
                'dimensions' => '',
                'due' => $job === null ? '' : (string) ($job['target_date'] ?? ''),
                'location' => '',
                'width' => '',
                'original' => '',
                'remaining' => '',
            ]);
        }

        return null;
    }

    /**
     * @param array{tracking_code: string, token: string, url: string} $issued
     * @param array<string, string> $extra
     * @return array<string, string>
     */
    private function pack(array $issued, array $extra): array
    {
        return $extra + [
            'code' => $issued['tracking_code'],
            'url' => $issued['url'],
            'barcode' => $this->barcode->svg($issued['tracking_code']),
        ];
    }

    /**
     * @param array<string, string> $vars
     */
    private function fill(string $line, array $vars): string
    {
        $allowed = ['code', 'product', 'description', 'customer', 'quantity', 'dimensions', 'due', 'location', 'width', 'original', 'remaining'];

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/',
            static function (array $match) use ($vars, $allowed): string {
                if (!in_array($match[1], $allowed, true)) {
                    return '';
                }

                return htmlspecialchars((string) ($vars[$match[1]] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            },
            $line
        );
    }
}
