<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Reads an enquiry as data. It does not price, discount, or promise a date.
 */
final class IntakeTextParser
{
    /**
     * @param array<string, string> $terms customer term => category
     * @return array<string, mixed>
     */
    public function parse(string $text, string $receivedAt, array $terms = []): array
    {
        $original = $text;
        $language = $this->language($text);
        $intent = $this->intent($text);
        $discount = $this->discount($text);
        $budget = $this->budget($text);
        $when = $this->when($text, $receivedAt);
        $site = $this->site($text);
        $install = $this->mentionsInstall($text) ? 'YES' : null;
        $vehicle = $this->vehicle($text);
        $letters = $this->letters($text);
        $items = $vehicle !== null || $letters !== null || $intent === 'SERVICE' || $intent === 'COMPLAINT'
            ? []
            : $this->items($text, $terms);
        $type = 'SIGNAGE';
        if ($vehicle !== null) {
            $type = 'VEHICLE';
            $intent = $intent === 'SERVICE' || $intent === 'COMPLAINT' ? $intent : 'NEW_QUOTE';
        } elseif ($letters !== null) {
            $type = 'ILLUMINATED';
        } elseif ($intent === 'REORDER') {
            $type = 'SIGNAGE';
        }

        return [
            'language' => $language,
            'intent' => $intent,
            'requirement_type' => $type,
            'discount_requested_percent' => $discount,
            'customer_budget' => $budget,
            'requested_date' => $when['date'],
            'requested_date_text' => $when['text'],
            'requested_date_approximate' => $when['approximate'],
            'date_promised' => false,
            'site' => $site,
            'installation_proposed' => $install,
            'artwork_mentioned' => (bool) preg_match('/\b(logo|artwork|pics|photos|foto)\b/i', $text),
            'vehicle' => $vehicle,
            'letters' => $letters,
            'items' => $items,
            'original' => $original,
        ];
    }

    private function language(string $text): string
    {
        return preg_match('/\b(ek soek|asseblief|borde|kan jy|my logo op)\b/i', $text) === 1 ? 'AF' : 'EN';
    }

    private function intent(string $text): string
    {
        $lower = strtolower($text);
        if (preg_match('/\b(complaint|unacceptable|disgusted)\b/i', $lower) === 1) {
            return 'COMPLAINT';
        }
        if (preg_match('/\b(replace)\b/i', $lower) === 1 && preg_match('/\b(sign|board|lightbox)\b/i', $lower) === 1) {
            return 'ASSET_REPLACEMENT';
        }
        if (preg_match('/\b(stopped working|flickering|not working|repair|broken|won\'t turn)\b/i', $lower) === 1) {
            return 'SERVICE';
        }
        if (preg_match('/\b(\d+\s+more of|reorder|standard parking)\b/i', $lower) === 1) {
            return 'REORDER';
        }
        if (preg_match('/\b(quote|price|quote us|boards|signs|borde|branding)\b/i', $lower) === 1) {
            return 'NEW_QUOTE';
        }

        return 'GENERAL_ENQUIRY';
    }

    private function discount(string $text): ?string
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*%/', $text, $match) === 1 && preg_match('/discount|afslag/i', $text) === 1) {
            return $match[1];
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*%?\s*discount/i', $text, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    private function budget(string $text): ?string
    {
        if (preg_match('/(?:budget is|quote this at|price this at)\s+R?\s*([\d][\d\s,]*)/i', $text, $match) === 1) {
            $digits = preg_replace('/[^\d.]/', '', $match[1]) ?? '';

            return $digits === '' ? null : $digits;
        }

        return null;
    }

    /**
     * @return array{date: ?string, text: ?string, approximate: bool}
     */
    private function when(string $text, string $receivedAt): array
    {
        $base = new \DateTimeImmutable(substr($receivedAt, 0, 19) ?: 'now');
        if (preg_match('/\btomorrow\b/i', $text) === 1) {
            return ['date' => $base->modify('+1 day')->format('Y-m-d'), 'text' => 'tomorrow', 'approximate' => false];
        }
        if (preg_match('/next friday/i', $text) === 1) {
            return ['date' => $base->modify('next friday')->format('Y-m-d'), 'text' => 'next friday', 'approximate' => false];
        }
        if (preg_match('/next week/i', $text) === 1) {
            return ['date' => $base->modify('+7 day')->format('Y-m-d'), 'text' => 'next week', 'approximate' => true];
        }

        return ['date' => null, 'text' => null, 'approximate' => false];
    }

    private function site(string $text): ?string
    {
        if (preg_match('/\b([A-Z][a-zA-Z]+)\s+branch\b/', $text, $match) === 1) {
            return $match[1];
        }
        if (preg_match('/\b(?:in|at)\s+(?:our\s+)?(?:new\s+)?(?:branch\s+in\s+)?([A-Z][a-zA-Z]{3,})\b/', $text, $match) === 1) {
            $place = $match[1];
            if (!in_array(strtolower($place), ['sign', 'board', 'logo', 'please', 'morning'], true)) {
                return $place;
            }
        }

        return null;
    }

    private function mentionsInstall(string $text): bool
    {
        return preg_match('/\b(install|installed|installation|installeer)\b/i', $text) === 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function vehicle(string $text): ?array
    {
        if (preg_match('/\b(ranger|hilux|np200|polo)\b/i', $text) !== 1 && preg_match('/\bford\b/i', $text) !== 1) {
            return null;
        }
        $make = preg_match('/\bford\b/i', $text) === 1 || preg_match('/\branger\b/i', $text) === 1 ? 'Ford' : null;
        $model = preg_match('/\branger\b/i', $text) === 1 ? 'Ranger' : null;
        if (preg_match('/\bhilux\b/i', $text) === 1) {
            $make = 'Toyota';
            $model = 'Hilux';
        }
        $year = null;
        if (preg_match('/\b(19|20)\d{2}\b/', $text, $match) === 1) {
            $year = $match[0];
        }
        $body = null;
        if (preg_match('/double\s*cab|\bdc\b/i', $text) === 1) {
            $body = 'Double cab';
        }
        $panels = [];
        foreach (['doors' => 'doors', 'tailgate' => 'tailgate', 'canopy' => 'canopy', 'load bin' => 'load bin', 'bonnet' => 'bonnet'] as $needle => $label) {
            if (str_contains(strtolower($text), $needle)) {
                $panels[] = $label;
            }
        }

        return [
            'make' => $make,
            'model' => $model,
            'year' => $year,
            'body' => $body,
            'coverage' => $panels === [] ? null : 'Partial',
            'panels' => $panels,
            'estimator' => 'VEHICLE_WRAP',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function letters(string $text): ?array
    {
        if (preg_match('/illuminated letters|channel letters|lig letters/i', $text) !== 1) {
            return null;
        }
        $height = null;
        if (preg_match('/(\d+(?:\.\d+)?)\s*mm/i', $text, $match) === 1) {
            $height = $this->toMm($match[1], 'mm');
        }
        $copy = null;
        if (preg_match('/saying\s+([A-Za-z0-9]+)/i', $text, $match) === 1) {
            $copy = $match[1];
        }

        return [
            'text' => $copy,
            'height_mm' => $height,
            'illuminated' => true,
            'estimator' => 'CHANNEL_LETTER',
            'led_quantity' => null,
        ];
    }

    /**
     * @param array<string, string> $terms
     * @return list<array<string, mixed>>
     */
    private function items(string $text, array $terms): array
    {
        $parts = preg_split('/\b(?:we also need|also need)\b/i', $text) ?: [$text];
        $items = [];
        $line = 1;
        $carriedMaterial = null;
        $carriedCategory = null;
        foreach ($parts as $part) {
            $qty = $this->quantity($part);
            $dims = $this->dimensions($part);
            $material = $this->material($part, $terms);
            if ($material === null && $carriedMaterial !== null) {
                $material = ['text' => $carriedMaterial, 'category' => $carriedCategory];
            }
            if ($material !== null) {
                $carriedMaterial = $material['text'];
                $carriedCategory = $material['category'];
            }
            if ($qty === null && $dims === null && $material === null) {
                continue;
            }
            $print = null;
            if (preg_match('/\b(one side|single|front|een kant)\b/i', $part) === 1) {
                $print = 1;
            } elseif (preg_match('/\b(both sides|double sided|two sides)\b/i', $part) === 1) {
                $print = 2;
            }
            $items[] = [
                'line_no' => $line,
                'description' => trim(preg_replace('/\s+/', ' ', $part) ?? $part),
                'quantity' => $qty['value'] ?? null,
                'quantity_method' => $qty['method'] ?? null,
                'width_mm' => $dims['width'] ?? null,
                'height_mm' => $dims['height'] ?? null,
                'original_text' => $dims['original'] ?? null,
                'is_approximate' => ($dims['approximate'] ?? false) || ($qty['approximate'] ?? false),
                'assumption' => $dims['assumption'] ?? null,
                'material_text' => $material['text'] ?? null,
                'material_category' => $material['category'] ?? null,
                'print_sides' => $print,
                'thickness_mm' => $this->thickness($part),
                'finish' => null,
            ];
            $line++;
        }

        return $items;
    }

    /**
     * @return array{value: string, method: string, approximate: bool}|null
     */
    private function quantity(string $text): ?array
    {
        if (preg_match('/half\s+a\s+dozen/i', $text) === 1) {
            return ['value' => '6', 'method' => 'INFERRED', 'approximate' => false];
        }
        if (preg_match('/\b(\d+)\s+(?:more\s+of|acm|chromadek|boards|borde|signs|letters|parking)\b/i', $text, $match) === 1) {
            return ['value' => $match[1], 'method' => 'DIRECT', 'approximate' => false];
        }
        if (preg_match('/\b(\d+)\s*x\s+(?!\d)/i', $text, $match) === 1) {
            return ['value' => $match[1], 'method' => 'DIRECT', 'approximate' => false];
        }

        return null;
    }

    /**
     * @return array{width: string, height: string, original: string, approximate: bool, assumption: string}|null
     */
    private function dimensions(string $text): ?array
    {
        if (preg_match('/(?:about|roughly|approximately|approx\.?)\s*(\d+(?:\.\d+)?)\s*(mm|cm|m)(?![a-z])\s*wide/i', $text, $wide) === 1) {
            return [
                'width' => $this->toMm($wide[1], strtolower($wide[2])),
                'height' => '',
                'original' => $wide[0],
                'approximate' => true,
                'assumption' => 'width only',
            ];
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:(mm|cm|m)(?![a-z]))?\s*[x×]\s*(\d+(?:\.\d+)?)\s*(?:(mm|cm|m)(?![a-z]))?/iu', $text, $match) !== 1) {
            return null;
        }
        $unitA = strtolower($match[2] ?? '');
        $unitB = strtolower($match[4] ?? '');
        if ($unitA === '' && $unitB !== '') {
            $unitA = ((float) $match[1] < 30 && $unitB === 'm') ? 'm' : $unitB;
        }
        if ($unitB === '' && $unitA !== '') {
            $unitB = $unitA;
        }
        if ($unitA === '' && $unitB === '') {
            $unitA = 'mm';
            $unitB = 'mm';
        }
        $assumption = 'Width × height is an assumption until someone confirms it.';

        return [
            'width' => $this->toMm($match[1], $unitA),
            'height' => $this->toMm($match[3], $unitB),
            'original' => $match[0],
            'approximate' => preg_match('/\b(about|roughly|approximately)\b/i', $text) === 1,
            'assumption' => $assumption,
        ];
    }

    private function toMm(string $number, string $unit): string
    {
        $value = (float) $number;
        $mm = match ($unit) {
            'm' => $value * 1000,
            'cm' => $value * 10,
            default => $value,
        };

        return (string) (int) round($mm);
    }

    /**
     * @param array<string, string> $terms
     * @return array{text: string, category: string}|null
     */
    private function material(string $text, array $terms): ?array
    {
        $lower = strtolower($text);
        $found = null;
        foreach ($terms as $term => $category) {
            if ($term !== '' && str_contains($lower, strtolower($term)) && ($found === null || strlen($term) > strlen($found['text']))) {
                $found = ['text' => $term, 'category' => $category];
            }
        }
        if ($found !== null) {
            return $found;
        }
        foreach (['acm' => 'ACM', 'chromadek' => 'Chromadek signage', 'perspex' => 'Acrylic', 'vinyl' => 'Vinyl'] as $term => $category) {
            if (str_contains($lower, $term)) {
                return ['text' => $term, 'category' => $category];
            }
        }

        return null;
    }

    private function thickness(string $text): ?string
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*mm\s*thick/i', $text, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
