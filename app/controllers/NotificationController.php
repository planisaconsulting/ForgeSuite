<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\NotificationRepository;
use App\Services\NotificationService;

final class NotificationController
{
    public function index(): void
    {
        $user = auth_user();
        $service = new NotificationService();
        View::render('notifications/index', [
            'title' => 'Notifications',
            'activeNav' => 'notifications',
            'rows' => $service->inbox((int) $user['id'], (int) $user['role_id']),
        ]);
    }

    public function read(string $id): void
    {
        $user = auth_user();
        $service = new NotificationService();
        $noticeId = route_id($id);
        $service->markRead($noticeId, (int) $user['id'], (int) $user['role_id']);
        $target = $this->target($noticeId, (int) $user['id'], (int) $user['role_id']);
        redirect($target);
    }

    public function readAll(): void
    {
        $user = auth_user();
        (new NotificationService())->markAll((int) $user['id'], (int) $user['role_id']);
        flash('success', 'Notifications marked read.');
        redirect('/notifications');
    }

    public function preferences(): void
    {
        $user = auth_user();
        View::render('notifications/preferences', [
            'title' => 'Notification preferences',
            'activeNav' => 'notifications',
            'preferences' => (new NotificationService())->preferences((int) $user['id']),
            'locked' => NotificationService::LOCKED,
        ]);
    }

    public function savePreferences(): void
    {
        $user = auth_user();
        (new NotificationService())->savePreferences((int) $user['id'], $_POST);
        flash('success', 'Notification preferences saved. System notices stay on.');
        redirect('/notifications/preferences');
    }

    public function reminders(): void
    {
        $user = auth_user();
        View::render('reminders/index', [
            'title' => 'Reminders',
            'activeNav' => 'notifications',
            'rows' => (new NotificationRepository())->reminders((int) $user['id']),
        ]);
    }

    public function completeReminder(string $id): void
    {
        $status = strtoupper((string) ($_POST['status'] ?? 'COMPLETED'));
        if (!in_array($status, ['COMPLETED', 'DISMISSED'], true)) {
            $status = 'COMPLETED';
        }
        (new NotificationRepository())->completeReminder(route_id($id), (int) auth_user()['id'], $status);
        flash('success', 'Reminder updated.');
        redirect('/reminders');
    }

    private function target(int $id, int $userId, int $roleId): string
    {
        foreach ((new NotificationService())->inbox($userId, $roleId) as $row) {
            if ((int) $row['id'] !== $id) {
                continue;
            }
            $entity = (string) ($row['entity_type'] ?? '');
            $entityId = (int) ($row['entity_id'] ?? 0);
            if ($entityId < 1) {
                return '/notifications';
            }

            return match ($entity) {
                'quote' => '/quotes/' . $entityId,
                'job' => '/jobs/' . $entityId,
                'invoice' => '/invoices/' . $entityId,
                'customer' => '/customers/' . $entityId,
                'product' => '/products/' . $entityId,
                'purchase_order' => '/purchasing/orders/' . $entityId,
                default => '/notifications',
            };
        }

        return '/notifications';
    }
}
