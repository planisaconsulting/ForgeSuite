<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\ActivityRepository;
use App\Services\CustomerService;

final class ActivityController
{
    public function index(): void
    {
        $term = trim((string) ($_GET['q'] ?? ''));
        View::render('activities/index', [
            'title' => 'Activities',
            'activeNav' => 'activities',
            'rows' => (new ActivityRepository())->recent(80, $term),
            'term' => $term,
        ]);
    }

    public function complete(string $id): void
    {
        $activityId = route_id($id);
        $completed = posted_flag($_POST, 'completed', 1) === 1;
        $row = (new ActivityRepository())->find($activityId);
        if ($row === null || !(new CustomerService())->completeActivity($activityId, $completed)) {
            abort_not_found('That activity was not found.');
        }
        flash('success', $completed ? 'Activity marked complete.' : 'Activity reopened.');
        $back = (string) ($_POST['return_to'] ?? '');
        if ($back === 'customer') {
            redirect('/customers/' . (int) $row['customer_id']);
        }
        redirect('/activities');
    }
}
