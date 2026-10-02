<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\SignageRepository;
use App\Services\SignEstimateService;
use App\Services\SpecificationService;
use App\Services\SvgGeometry;
use App\Services\VehicleWrapEstimator;

/**
 * Sign specifications and the manufacturing estimators.
 */
final class SignageController
{
    public function index(): void
    {
        View::render('signage/index', [
            'title' => 'Advanced estimating',
            'activeNav' => 'signage',
            'dashboard' => (new SignageRepository())->dashboard(),
            'recent' => (new SignageRepository())->calculations([]),
        ]);
    }

    public function specifications(): void
    {
        View::render('signage/specifications', [
            'title' => 'Specifications',
            'activeNav' => 'specifications',
            'rows' => (new SignageRepository())->specifications([
                'q' => trim((string) ($_GET['q'] ?? '')),
                'status' => strtoupper(trim((string) ($_GET['status'] ?? ''))),
                'estimator_type' => strtoupper(trim((string) ($_GET['estimator_type'] ?? ''))),
                'approved_only' => !empty($_GET['approved']),
                'engineering' => !empty($_GET['engineering']),
            ]),
        ]);
    }

    public function specification(string $id): void
    {
        $repo = new SignageRepository();
        $row = $repo->specification((int) $id);
        if ($row === null) {
            abort_not_found('That specification was not found.');
        }
        View::render('signage/specification', [
            'title' => (string) $row['code'],
            'activeNav' => 'specifications',
            'spec' => $row,
            'materials' => $repo->materials((int) $row['id']),
            'components' => $repo->components((int) $row['id']),
            'labour' => $repo->labour((int) $row['id']),
            'operations' => $repo->operations((int) $row['id']),
            'rules' => $repo->rules((int) $row['id']),
            'notes' => $repo->notes((int) $row['id']),
        ]);
    }

    public function saveSpecification(): void
    {
        $result = (new SpecificationService())->create($_POST, (int) auth_user()['id']);
        if ($result['id'] === null) {
            flash('error', (string) ($result['errors']['_form'] ?? reset($result['errors']) ?: 'The specification was not saved.'));
            redirect('/specifications');
        }
        redirect('/specifications/' . $result['id']);
    }

    public function approveSpecification(string $id): void
    {
        $errors = (new SpecificationService())->approve((int) $id, (int) auth_user()['id']);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Specification approved.' : (string) reset($errors));
        redirect('/specifications/' . $id);
    }

    public function reviseSpecification(string $id): void
    {
        $result = (new SpecificationService())->revise((int) $id, (int) auth_user()['id']);
        if ($result['id'] === null) {
            flash('error', (string) ($result['errors']['_form'] ?? 'A new version was not created.'));
            redirect('/specifications/' . $id);
        }
        redirect('/specifications/' . $result['id']);
    }

    public function form(string $type): void
    {
        $repo = new SignageRepository();
        View::render('signage/form', [
            'title' => str_replace('_', ' ', $type),
            'activeNav' => 'signage',
            'type' => strtoupper($type),
            'specs' => $repo->specifications(['estimator_type' => strtoupper($type), 'approved_only' => true]),
            'profiles' => $repo->profiles(),
            'templates' => $repo->templates(),
            'panels' => VehicleWrapEstimator::PANELS,
            'coverage' => VehicleWrapEstimator::COVERAGE,
        ]);
    }

    public function calculate(string $type): void
    {
        $input = $_POST;
        if (!empty($_FILES['geometry']['tmp_name']) && is_uploaded_file((string) $_FILES['geometry']['tmp_name'])) {
            $svg = (string) file_get_contents((string) $_FILES['geometry']['tmp_name']);
            $input['svg'] = $svg;
        }
        $ran = (new SignEstimateService())->run(strtoupper($type), $input, (int) auth_user()['id'], empty($_POST['what_if']));
        if ($ran['errors'] !== []) {
            flash('error', (string) reset($ran['errors']));
            redirect('/estimating/signage/' . strtolower($type));
        }
        if ($ran['id'] === null) {
            $_SESSION['sign_what_if'] = $ran['result'];
            redirect('/estimating/signage/preview');
        }
        redirect('/estimating/signage/calculations/' . $ran['id']);
    }

    public function preview(): void
    {
        $result = $_SESSION['sign_what_if'] ?? null;
        unset($_SESSION['sign_what_if']);
        if (!is_array($result)) {
            redirect('/estimating/signage');
        }
        View::render('signage/result', [
            'title' => 'What-if',
            'activeNav' => 'signage',
            'row' => null,
            'result' => $result,
            'showCosts' => can('estimators.view_costs'),
        ]);
    }

    public function show(string $id): void
    {
        $row = (new SignageRepository())->calculation((int) $id);
        if ($row === null) {
            abort_not_found('That calculation was not found.');
        }
        $result = json_decode((string) $row['output_json'], true);
        View::render('signage/result', [
            'title' => 'Calculation ' . $id,
            'activeNav' => 'signage',
            'row' => $row,
            'result' => is_array($result) ? $result : [],
            'showCosts' => can('estimators.view_costs'),
        ]);
    }

    public function report(string $slug): void
    {
        View::render('signage/report', [
            'title' => 'Estimator report',
            'activeNav' => 'signage',
            'report' => (new SignEstimateService())->report($slug),
        ]);
    }
}
