<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\AttachmentRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\SiteSurveyRepository;
use App\Services\AttachmentService;
use App\Services\QuotePdf;
use App\Services\SettingsService;
use App\Services\SiteSurveyService;

final class SiteSurveyController
{
    public function index(): void
    {
        View::render('surveys/index', [
            'title' => 'Site surveys',
            'activeNav' => 'surveys',
            'rows' => (new SiteSurveyRepository())->search(trim((string) ($_GET['q'] ?? '')), (string) ($_GET['status'] ?? '')),
            'term' => trim((string) ($_GET['q'] ?? '')),
            'status' => (string) ($_GET['status'] ?? ''),
            'canCreate' => can('site_surveys.create'),
        ]);
    }

    public function create(): void
    {
        $this->form([
            'customer_id' => (int) ($_GET['customer_id'] ?? 0),
            'opportunity_id' => (int) ($_GET['opportunity_id'] ?? 0),
            'quote_id' => (int) ($_GET['quote_id'] ?? 0),
            'job_id' => (int) ($_GET['job_id'] ?? 0),
            'status' => 'DRAFT',
            'survey_date' => date('Y-m-d'),
            'surveyed_by' => (int) (auth_user()['id'] ?? 0),
        ], []);
    }

    public function store(): void
    {
        $result = (new SiteSurveyService())->create($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            $this->form($_POST, $result['errors']);

            return;
        }
        flash('success', 'Site survey created.');
        redirect('/surveys/' . $result['id']);
    }

    public function show(string $id): void
    {
        $row = $this->row($id);
        View::render('surveys/show', [
            'title' => (string) $row['survey_number'],
            'activeNav' => 'surveys',
            'survey' => $row,
            'measurements' => (new SiteSurveyRepository())->measurements((int) $row['id']),
            'photos' => (new AttachmentRepository())->forEntity('site_survey', (int) $row['id']),
            'canEdit' => can('site_surveys.edit'),
            'types' => SiteSurveyService::MEASUREMENTS,
            'tags' => SiteSurveyService::PHOTO_TAGS,
            'fieldNotes' => (new \App\Repositories\FieldRepository())->notesFor('SITE_SURVEY', (int) $row['id']),
            'fieldPhotos' => (new \App\Repositories\FieldRepository())->photosFor('SITE_SURVEY', (int) $row['id']),
        ]);
    }

    public function edit(string $id): void
    {
        $this->form($this->row($id), []);
    }

    public function update(string $id): void
    {
        $row = $this->row($id);
        $errors = (new SiteSurveyService())->update((int) $row['id'], $_POST, (int) auth_user()['id']);
        if ($errors !== []) {
            $this->form(array_merge($row, $_POST), $errors);

            return;
        }
        flash('success', 'Survey saved.');
        redirect('/surveys/' . $row['id']);
    }

    public function measure(string $id): void
    {
        $row = $this->row($id);
        $errors = (new SiteSurveyService())->addMeasurement((int) $row['id'], $_POST);
        if ($errors !== []) {
            flash('error', implode(' ', $errors));
        } else {
            flash('success', 'Measurement added.');
        }
        redirect('/surveys/' . $row['id']);
    }

    public function photo(string $id): void
    {
        $row = $this->row($id);
        $tag = strtoupper(trim((string) ($_POST['photo_tag'] ?? 'OTHER')));
        $meta = [
            'visibility' => 'INTERNAL',
            'photo_tag' => $tag,
            'measurement_id' => (int) ($_POST['measurement_id'] ?? 0),
            'caption' => blank_to_null($_POST['caption'] ?? null),
        ];
        $files = $_FILES['file'] ?? [];
        $errors = [];
        $names = $files['name'] ?? null;
        if (is_array($names)) {
            foreach ($names as $index => $name) {
                if ($name === '') {
                    continue;
                }
                $one = [
                    'name' => $name,
                    'type' => $files['type'][$index] ?? '',
                    'tmp_name' => $files['tmp_name'][$index] ?? '',
                    'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $files['size'][$index] ?? 0,
                ];
                $errors = (new AttachmentService())->store('site_survey', (int) $row['id'], $one, (int) auth_user()['id'], 'SURVEY_PHOTO', $meta['caption'], true, $meta);
                if ($errors !== []) {
                    break;
                }
            }
        } else {
            $errors = (new AttachmentService())->store('site_survey', (int) $row['id'], $files, (int) auth_user()['id'], 'SURVEY_PHOTO', $meta['caption'], true, $meta);
        }
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Photo added.' : implode(' ', $errors));
        redirect('/surveys/' . $row['id']);
    }

    public function pdf(string $id): void
    {
        $row = $this->row($id);
        $html = View::capture('surveys/pdf', [
            'survey' => $row,
            'measurements' => (new SiteSurveyRepository())->measurements((int) $row['id']),
            'photos' => (new AttachmentRepository())->forEntity('site_survey', (int) $row['id']),
            'company' => SettingsService::get('company_name', 'Sign-Forge'),
        ]);
        $binary = (new QuotePdf())->render($html);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $row['survey_number'] . '.pdf"');
        echo $binary;
        exit;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function form(array $old, array $errors): void
    {
        View::render('surveys/form', [
            'title' => empty($old['id']) ? 'New site survey' : 'Edit survey',
            'activeNav' => 'surveys',
            'old' => $old,
            'errors' => $errors,
            'customers' => (new CustomerRepository())->search('', 'active', 200),
            'statuses' => SiteSurveyService::STATUSES,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $id): array
    {
        $row = (new SiteSurveyRepository())->find(route_id($id));
        if ($row === null) {
            abort_not_found('That survey was not found.');
        }

        return $row;
    }
}
