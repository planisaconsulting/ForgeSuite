<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SalesIntakeRepository;

/**
 * Accepts only the intake schema. Unknown actions and money fields are dropped.
 */
final class IntakeSchema
{
    /** @var list<string> */
    private const ROOT = ['customer', 'request', 'items', 'missing_information', 'attachments', 'intent', 'language'];

    /** @var list<string> */
    private const ITEM = [
        'description', 'quantity', 'width_mm', 'height_mm', 'depth_mm', 'material', 'finish',
        'print_sides', 'installation', 'notes', 'is_approximate', 'original_text', 'thickness_mm', 'product_id',
    ];

    /** @var list<string> */
    private const MONEY = ['unit_cost', 'sell_price', 'margin', 'vat', 'discount', 'markup', 'cost', 'price'];

    public function __construct(private readonly SalesIntakeRepository $repo = new SalesIntakeRepository())
    {
    }

    /**
     * @return array{ok: bool, status: string, data: array<string, mixed>, errors: list<string>, ignored_money: list<string>}
     */
    public function accept(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['ok' => false, 'status' => 'FAILED', 'data' => [], 'errors' => ['The analysis was not valid JSON.'], 'ignored_money' => []];
        }
        if (!is_array($decoded)) {
            return ['ok' => false, 'status' => 'FAILED', 'data' => [], 'errors' => ['The analysis was not an object.'], 'ignored_money' => []];
        }
        $errors = [];
        $ignored = [];
        $clean = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key) || !in_array($key, self::ROOT, true)) {
                continue;
            }
            $clean[$key] = $value;
        }
        $items = [];
        foreach (is_array($clean['items'] ?? null) ? $clean['items'] : [] as $item) {
            if (!is_array($item)) {
                $errors[] = 'An item was not an object.';
                continue;
            }
            $row = [];
            foreach ($item as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                if (in_array($key, self::MONEY, true)) {
                    $ignored[] = $key;
                    continue;
                }
                if (!in_array($key, self::ITEM, true)) {
                    continue;
                }
                $row[$key] = $value;
            }
            if (isset($row['product_id'])) {
                $productId = (int) $row['product_id'];
                if ($productId < 1 || $this->repo->product($productId) === null) {
                    $errors[] = 'The analysis named a product that does not exist.';
                    unset($row['product_id']);
                }
            }
            if (isset($row['quantity']) && !$this->number($row['quantity'])) {
                $errors[] = 'A quantity was not a number.';
                unset($row['quantity']);
            }
            foreach (['width_mm', 'height_mm', 'depth_mm', 'thickness_mm'] as $dimension) {
                if (!isset($row[$dimension]) || $row[$dimension] === '' || $row[$dimension] === null) {
                    continue;
                }
                if (!$this->number($row[$dimension]) || (float) $row[$dimension] < 1 || (float) $row[$dimension] > 50000) {
                    $errors[] = 'A dimension was out of range.';
                    unset($row[$dimension]);
                }
            }
            $items[] = $row;
        }
        $clean['items'] = $items;
        $status = $errors === [] ? 'PROPOSED' : 'REVIEW_REQUIRED';

        return ['ok' => $errors === [], 'status' => $status, 'data' => $clean, 'errors' => $errors, 'ignored_money' => $ignored];
    }

    private function number(mixed $value): bool
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
    }
}
