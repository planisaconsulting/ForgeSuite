<?php

declare(strict_types=1);

namespace App\Repositories;

final class SiteSurveyRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, string $status = ''): array
    {
        $sql = 'SELECT s.*, c.company_name, c.first_name, c.last_name, c.customer_type, u.name AS surveyor_name
                FROM site_surveys s
                INNER JOIN customers c ON c.id = s.customer_id
                LEFT JOIN users u ON u.id = s.surveyed_by
                WHERE 1 = 1';
        $params = [];
        if ($term !== '') {
            $sql .= ' AND (s.survey_number LIKE ? OR s.site_name LIKE ? OR c.company_name LIKE ?)';
            $like = '%' . $term . '%';
            $params = [$like, $like, $like];
        }
        if ($status !== '' && $status !== 'all') {
            $sql .= ' AND s.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY s.survey_date DESC, s.id DESC LIMIT 200';

        return $this->rows($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one(
            'SELECT s.*, c.company_name, c.first_name, c.last_name, c.customer_type, u.name AS surveyor_name
             FROM site_surveys s
             INNER JOIN customers c ON c.id = s.customer_id
             LEFT JOIN users u ON u.id = s.surveyed_by
             WHERE s.id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO site_surveys (
                survey_number, customer_id, contact_id, opportunity_id, quote_id, job_id, site_name,
                address_line_1, address_line_2, city, province, postal_code, latitude, longitude,
                site_contact_name, site_contact_phone, survey_date, surveyed_by, status, environment,
                surface_type, mounting_height_mm, access_difficulty, ladder_required, scaffolding_required,
                cherry_picker_required, electrical_supply, power_location, height_notes, traffic_notes,
                special_access, access_notes, installation_notes, electrical_notes, general_notes, created_by
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
             )',
            [
                $data['survey_number'], $data['customer_id'], $data['contact_id'], $data['opportunity_id'],
                $data['quote_id'], $data['job_id'], $data['site_name'], $data['address_line_1'], $data['address_line_2'],
                $data['city'], $data['province'], $data['postal_code'], $data['latitude'], $data['longitude'],
                $data['site_contact_name'], $data['site_contact_phone'], $data['survey_date'], $data['surveyed_by'],
                $data['status'], $data['environment'], $data['surface_type'], $data['mounting_height_mm'],
                $data['access_difficulty'], $data['ladder_required'], $data['scaffolding_required'],
                $data['cherry_picker_required'], $data['electrical_supply'], $data['power_location'],
                $data['height_notes'], $data['traffic_notes'], $data['special_access'], $data['access_notes'],
                $data['installation_notes'], $data['electrical_notes'], $data['general_notes'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->run(
            'UPDATE site_surveys SET contact_id = ?, opportunity_id = ?, quote_id = ?, job_id = ?, site_name = ?,
                address_line_1 = ?, address_line_2 = ?, city = ?, province = ?, postal_code = ?, latitude = ?, longitude = ?,
                site_contact_name = ?, site_contact_phone = ?, survey_date = ?, surveyed_by = ?, status = ?, environment = ?,
                surface_type = ?, mounting_height_mm = ?, access_difficulty = ?, ladder_required = ?, scaffolding_required = ?,
                cherry_picker_required = ?, electrical_supply = ?, power_location = ?, height_notes = ?, traffic_notes = ?,
                special_access = ?, access_notes = ?, installation_notes = ?, electrical_notes = ?, general_notes = ?
             WHERE id = ?',
            [
                $data['contact_id'], $data['opportunity_id'], $data['quote_id'], $data['job_id'], $data['site_name'],
                $data['address_line_1'], $data['address_line_2'], $data['city'], $data['province'], $data['postal_code'],
                $data['latitude'], $data['longitude'], $data['site_contact_name'], $data['site_contact_phone'],
                $data['survey_date'], $data['surveyed_by'], $data['status'], $data['environment'], $data['surface_type'],
                $data['mounting_height_mm'], $data['access_difficulty'], $data['ladder_required'],
                $data['scaffolding_required'], $data['cherry_picker_required'], $data['electrical_supply'],
                $data['power_location'], $data['height_notes'], $data['traffic_notes'], $data['special_access'],
                $data['access_notes'], $data['installation_notes'], $data['electrical_notes'], $data['general_notes'], $id,
            ]
        );
    }

    public function linkQuote(int $id, int $quoteId): void
    {
        $this->run('UPDATE site_surveys SET quote_id = ? WHERE id = ?', [$quoteId, $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function measurements(int $surveyId): array
    {
        return $this->rows('SELECT * FROM site_survey_measurements WHERE site_survey_id = ? ORDER BY id', [$surveyId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertMeasurement(array $data): int
    {
        $this->run(
            'INSERT INTO site_survey_measurements (
                site_survey_id, reference, measurement_type, width_mm, height_mm, depth_mm, length_mm, quantity, description, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['site_survey_id'], $data['reference'], $data['measurement_type'], $data['width_mm'],
                $data['height_mm'], $data['depth_mm'], $data['length_mm'], $data['quantity'], $data['description'], $data['notes'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function measurement(int $id): ?array
    {
        return $this->one('SELECT * FROM site_survey_measurements WHERE id = ? LIMIT 1', [$id]);
    }
}
