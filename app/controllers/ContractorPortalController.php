<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Services\ContractorWorkService;

/**
 * External contractor portal. The session is not a staff user and not a customer.
 */
final class ContractorPortalController
{
    public function loginForm(): void
    {
        View::render('contractor/login', ['title' => 'Contractor sign in', 'errors' => []], 'layouts/contractor');
    }

    public function login(): void
    {
        $result = (new ContractorWorkService())->login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($result['errors'] !== []) {
            View::render('contractor/login', ['title' => 'Contractor sign in', 'errors' => $result['errors']], 'layouts/contractor');

            return;
        }
        redirect('/contractor');
    }

    public function logout(): void
    {
        (new ContractorWorkService())->logout();
        redirect('/contractor/login');
    }

    public function home(): void
    {
        $user = $this->user();
        $service = new ContractorWorkService();
        View::render('contractor/home', [
            'title' => 'Your work',
            'user' => $user,
            'rows' => $service->home((int) $user['id']),
        ], 'layouts/contractor');
    }

    public function work(string $id): void
    {
        $user = $this->user();
        $row = (new ContractorWorkService())->portalWork((int) $id, (int) $user['id']);
        if ($row === null) {
            http_response_code(403);
            View::render('contractor/denied', ['title' => 'Not available'], 'layouts/contractor');

            return;
        }
        View::render('contractor/work', [
            'title' => (string) $row['work_order_number'],
            'row' => $row,
            'map' => \App\Services\TrackingUrl::mapLink((string) ($row['site_address'] ?? '')),
        ], 'layouts/contractor');
    }

    public function respond(string $id): void
    {
        $user = $this->user();
        $errors = (new ContractorWorkService())->respond(
            (int) $id,
            (int) $user['id'],
            (string) ($_POST['action_name'] ?? ''),
            (string) ($_POST['message'] ?? ''),
            (string) ($_POST['change_kind'] ?? '')
        );
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Response recorded.' : (string) reset($errors));
        redirect('/contractor/work/' . $id);
    }

    public function start(string $id): void
    {
        $user = $this->user();
        (new ContractorWorkService())->start((int) $id, (int) $user['id'], $this->coord('latitude'), $this->coord('longitude'));
        redirect('/contractor/work/' . $id);
    }

    public function submit(string $id): void
    {
        $user = $this->user();
        $errors = (new ContractorWorkService())->submit((int) $id, (int) $user['id'], $_POST);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Submitted for review. Sign-Forge still has to approve it.' : (string) reset($errors));
        redirect('/contractor/work/' . $id);
    }

    /**
     * @return array<string, mixed>
     */
    private function user(): array
    {
        $user = (new ContractorWorkService())->currentUser();
        if ($user === null) {
            redirect('/contractor/login');
        }

        return $user;
    }

    private function coord(string $key): ?string
    {
        $value = trim((string) ($_POST[$key] ?? ''));

        return $value === '' ? null : $value;
    }
}
