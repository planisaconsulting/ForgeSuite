<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\CustomerRepository;
use App\Repositories\SiteSurveyRepository;

final class SiteSurveyService
{
    /** @var list<string> */
    public const STATUSES = ['DRAFT', 'SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'];

    /** @var list<string> */
    public const MEASUREMENTS = ['SIGN_FACE', 'WALL', 'WINDOW', 'DOOR', 'PANEL', 'VEHICLE_AREA', 'POLE', 'FRAME', 'FASCIA', 'OTHER'];

    /** @var list<string> */
    public const PHOTO_TAGS = ['SITE_OVERVIEW', 'MEASUREMENT', 'INSTALLATION_AREA', 'ELECTRICAL', 'ACCESS', 'REFERENCE', 'OTHER'];

    public function __construct(
        private readonly SiteSurveyRepository $surveys = new SiteSurveyRepository(),
        private readonly CustomerRepository $customers = new CustomerRepository(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly AuditService $audit = new AuditService(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function create(array $input, int $userId): array
    {
        $data = $this->data($input, $userId, true);
        if (isset($data['errors'])) {
            return ['errors' => $data['errors'], 'id' => null];
        }
        $id = 0;
        Database::transaction(function () use ($data, $userId, &$id): void {
            $data['survey_number'] = $this->numbers->survey();
            $data['created_by'] = $userId;
            $id = $this->surveys->insert($data);
            $this->audit->record('site_survey', $id, 'created', null, ['survey_number' => $data['survey_number']], $userId);
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function update(int $id, array $input, int $userId): array
    {
        $existing = $this->surveys->find($id);
        if ($existing === null) {
            return ['_form' => 'That survey was not found.'];
        }
        $data = $this->data($input, $userId, false, $existing);
        if (isset($data['errors'])) {
            return $data['errors'];
        }
        if ((string) $existing['status'] === 'COMPLETED' && !can('site_surveys.complete') && (string) $data['status'] !== 'COMPLETED') {
            return ['_form' => 'A completed survey stays completed.'];
        }
        Database::transaction(function () use ($id, $data, $userId): void {
            $this->surveys->update($id, $data);
            $this->audit->record('site_survey', $id, 'updated', null, ['status' => $data['status']], $userId);
        });

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function addMeasurement(int $surveyId, array $input): array
    {
        $survey = $this->surveys->find($surveyId);
        if ($survey === null) {
            return ['_form' => 'That survey was not found.'];
        }
        $reference = trim((string) ($input['reference'] ?? ''));
        if ($reference === '') {
            return ['reference' => 'Name this measurement.'];
        }
        $type = strtoupper(trim((string) ($input['measurement_type'] ?? 'OTHER')));
        if (!in_array($type, self::MEASUREMENTS, true)) {
            $type = 'OTHER';
        }
        $this->surveys->insertMeasurement([
            'site_survey_id' => $surveyId,
            'reference' => $reference,
            'measurement_type' => $type,
            'width_mm' => $this->optional($input['width_mm'] ?? null),
            'height_mm' => $this->optional($input['height_mm'] ?? null),
            'depth_mm' => $this->optional($input['depth_mm'] ?? null),
            'length_mm' => $this->optional($input['length_mm'] ?? null),
            'quantity' => $this->optional($input['quantity'] ?? '1') ?? '1',
            'description' => blank_to_null($input['description'] ?? null),
            'notes' => blank_to_null($input['notes'] ?? null),
        ]);

        return [];
    }

    public function linkQuote(int $surveyId, int $quoteId): void
    {
        if ($surveyId > 0 && $quoteId > 0 && $this->surveys->find($surveyId) !== null) {
            $this->surveys->linkQuote($surveyId, $quoteId);
        }
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function data(array $input, int $userId, bool $creating, ?array $existing = null): array
    {
        $customerId = $creating ? (int) ($input['customer_id'] ?? 0) : (int) ($existing['customer_id'] ?? 0);
        if ($this->customers->find($customerId) === null) {
            return ['errors' => ['customer_id' => 'Choose a customer.']];
        }
        $site = trim((string) ($input['site_name'] ?? ''));
        if ($site === '') {
            return ['errors' => ['site_name' => 'Site name is required.']];
        }
        $status = strtoupper(trim((string) ($input['status'] ?? 'DRAFT')));
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'DRAFT';
        }
        if ($status === 'COMPLETED' && !can('site_surveys.complete') && !can('site_surveys.edit')) {
            $status = (string) ($existing['status'] ?? 'DRAFT');
        }
        $date = trim((string) ($input['survey_date'] ?? ''));
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['errors' => ['survey_date' => 'Survey date must be a date.']];
        }

        $kept = static function (string $key) use ($input, $existing): ?int {
            if (!array_key_exists($key, $input)) {
                $current = is_array($existing) ? (int) ($existing[$key] ?? 0) : 0;

                return $current > 0 ? $current : null;
            }
            $value = (int) $input[$key];

            return $value > 0 ? $value : null;
        };

        return [
            'customer_id' => $customerId,
            'contact_id' => $kept('contact_id'),
            'opportunity_id' => $kept('opportunity_id'),
            'quote_id' => $kept('quote_id'),
            'job_id' => $kept('job_id'),
            'site_name' => $site,
            'address_line_1' => blank_to_null($input['address_line_1'] ?? null),
            'address_line_2' => blank_to_null($input['address_line_2'] ?? null),
            'city' => blank_to_null($input['city'] ?? null),
            'province' => blank_to_null($input['province'] ?? null),
            'postal_code' => blank_to_null($input['postal_code'] ?? null),
            'latitude' => $this->optional($input['latitude'] ?? null),
            'longitude' => $this->optional($input['longitude'] ?? null),
            'site_contact_name' => blank_to_null($input['site_contact_name'] ?? null),
            'site_contact_phone' => blank_to_null($input['site_contact_phone'] ?? null),
            'survey_date' => $date !== '' ? $date : null,
            'surveyed_by' => ((int) ($input['surveyed_by'] ?? 0)) > 0 ? (int) $input['surveyed_by'] : $userId,
            'status' => $status,
            'environment' => blank_to_null($input['environment'] ?? null),
            'surface_type' => blank_to_null($input['surface_type'] ?? null),
            'mounting_height_mm' => $this->optional($input['mounting_height_mm'] ?? null),
            'access_difficulty' => blank_to_null($input['access_difficulty'] ?? null),
            'ladder_required' => !empty($input['ladder_required']) ? 1 : 0,
            'scaffolding_required' => !empty($input['scaffolding_required']) ? 1 : 0,
            'cherry_picker_required' => !empty($input['cherry_picker_required']) ? 1 : 0,
            'electrical_supply' => !empty($input['electrical_supply']) ? 1 : 0,
            'power_location' => blank_to_null($input['power_location'] ?? null),
            'height_notes' => blank_to_null($input['height_notes'] ?? null),
            'traffic_notes' => blank_to_null($input['traffic_notes'] ?? null),
            'special_access' => blank_to_null($input['special_access'] ?? null),
            'access_notes' => blank_to_null($input['access_notes'] ?? null),
            'installation_notes' => blank_to_null($input['installation_notes'] ?? null),
            'electrical_notes' => blank_to_null($input['electrical_notes'] ?? null),
            'general_notes' => blank_to_null($input['general_notes'] ?? null),
        ];
    }

    private function optional(mixed $value): ?string
    {
        $text = str_replace(',', '.', trim((string) $value));
        if ($text === '' || !Decimal::isNumeric($text)) {
            return null;
        }

        return $text;
    }
}
