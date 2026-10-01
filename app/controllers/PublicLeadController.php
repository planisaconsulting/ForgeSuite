<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\LeadService;

final class PublicLeadController
{
    public function store(): void
    {
        $raw = file_get_contents('php://input');
        $raw = $raw === false ? '' : $raw;
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        if ($ip === '') {
            $ip = '0.0.0.0';
        }
        try {
            $result = (new LeadService())->acceptPublic($raw, $ip);
        } catch (\Throwable) {
            json_response(['ok' => false, 'received' => false, 'message' => 'We could not accept that enquiry.'], 500);
        }
        json_response($result['body'], $result['http']);
    }
}
