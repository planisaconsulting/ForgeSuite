<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Services\CalendarFeedService;

/**
 * Calendar pages read operational dates. They do not reschedule the work.
 */
final class CalendarController
{
    public function index(): void
    {
        $from = trim((string) ($_GET['from'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-d');
        }
        $view = strtolower(trim((string) ($_GET['view'] ?? 'agenda')));
        if (!in_array($view, ['month', 'week', 'day', 'agenda'], true)) {
            $view = 'agenda';
        }
        $to = match ($view) {
            'day' => $from,
            'week' => date('Y-m-d', strtotime($from . ' +6 days')),
            'month' => date('Y-m-d', strtotime($from . ' +31 days')),
            default => date('Y-m-d', strtotime($from . ' +14 days')),
        };
        $scope = strtoupper(trim((string) ($_GET['scope'] ?? 'MY')));
        $userId = $scope === 'COMPANY' && can('calendar.view_company') ? null : (int) auth_user()['id'];
        $service = new CalendarFeedService();
        View::render('calendar/index', [
            'title' => $scope === 'COMPANY' ? 'Company calendar' : 'My calendar',
            'activeNav' => 'calendar',
            'view' => $view,
            'from' => $from,
            'scope' => $scope,
            'events' => $service->events($from, $to, $userId, 'STANDARD'),
        ]);
    }

    public function feed(string $token): void
    {
        $result = (new CalendarFeedService())->ics($token);
        if ($result['body'] === null) {
            http_response_code($result['status']);
            header('Content-Type: text/plain; charset=utf-8');
            echo (string) (reset($result['errors']) ?: 'Unavailable');

            return;
        }
        header('Content-Type: text/calendar; charset=utf-8');
        echo $result['body'];
    }
}
