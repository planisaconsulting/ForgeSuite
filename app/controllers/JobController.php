<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\JobRepository;

final class JobController
{
    public function index(): void
    {
        View::render('jobs/index', [
            'title' => 'Jobs',
            'activeNav' => 'jobs',
            'rows' => (new JobRepository())->recent(),
        ]);
    }

    public function show(string $id): void
    {
        $job = (new JobRepository())->find(route_id($id));
        if ($job === null) {
            abort_not_found('That job was not found.');
        }
        View::render('jobs/show', [
            'title' => (string) $job['job_number'],
            'activeNav' => 'jobs',
            'job' => $job,
        ]);
    }
}
