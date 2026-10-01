<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CustomerRepository;
use App\Repositories\OpportunityRepository;
use App\Repositories\QuoteRepository;
use App\Services\AttachmentService;

final class AttachmentController
{
    public function store(string $type, string $id): void
    {
        $entityId = route_id($id);
        $type = strtolower($type);
        if (!$this->exists($type, $entityId)) {
            abort_not_found('That record was not found.');
        }
        $errors = (new AttachmentService())->store($type, $entityId, $_FILES['file'] ?? [], (int) auth_user()['id']);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'File stored.' : (string) reset($errors));
        redirect($this->back($type, $entityId));
    }

    public function download(string $id): void
    {
        $file = (new AttachmentService())->download(route_id($id));
        if ($file === null) {
            abort_not_found('That file was not found.');
        }
        header('Content-Type: ' . $file['mime']);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $file['name']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($file['path']);
        exit;
    }

    private function exists(string $type, int $id): bool
    {
        return match ($type) {
            'customer' => (new CustomerRepository())->find($id) !== null,
            'quote' => (new QuoteRepository())->find($id) !== null,
            'opportunity' => (new OpportunityRepository())->find($id) !== null,
            default => false,
        };
    }

    private function back(string $type, int $id): string
    {
        return match ($type) {
            'customer' => '/customers/' . $id,
            'quote' => '/quotes/' . $id,
            'opportunity' => '/opportunities/' . $id,
            default => '/',
        };
    }
}
