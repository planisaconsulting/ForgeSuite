<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Csrf;
use App\Repositories\FinanceRepository;
use App\Services\InvoiceService;

$installerId = (int) ($argv[1] ?? 0);
$invoiceId = (int) ($argv[2] ?? 0);
$_SESSION['user_id'] = $installerId;
$invoice = (new FinanceRepository())->invoice($invoiceId);
$errors = (new InvoiceService())->issue($invoiceId, (int) ($invoice['version_number'] ?? 0), $installerId);
if (!str_contains((string) ($errors['_form'] ?? ''), 'cannot issue')) {
    fwrite(STDERR, "FAIL issue " . json_encode($errors) . "\n");
    exit(1);
}
fwrite(STDOUT, "ok   installer cannot issue\n");

$_SESSION['user_id'] = (int) ((new \App\Repositories\UserRepository())->findByEmail('admin@signforge.local')['id'] ?? 0);
$before = (new FinanceRepository())->invoice($invoiceId);
$saved = (new InvoiceService())->save($invoiceId, [
    'total' => '1.00',
    'vat_amount' => '0',
    'invoice_date' => $before['invoice_date'],
], (int) $before['version_number'], (int) $_SESSION['user_id']);
$after = (new FinanceRepository())->invoice($invoiceId);
if ((string) $after['total'] !== (string) $before['total']) {
    fwrite(STDERR, "FAIL total changed\n");
    exit(1);
}
fwrite(STDOUT, "ok   tampered total ignored\n");

$_SESSION['csrf'] = 'expected-token';
$_POST['_token'] = 'wrong-token';
Csrf::verify();
fwrite(STDERR, "FAIL csrf\n");
exit(1);
