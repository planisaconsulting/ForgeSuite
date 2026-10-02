<?php

declare(strict_types=1);

/**
 * v1.1 Phase 7: artwork proofs, revisions, and production files.
 *
 *   php tests/v11_phase7.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\ArtworkProofingRepository;
use App\Services\ArtworkProofingService;
use App\Services\CustomerHubService;
use App\Services\ReleaseReadinessService;

$failures = 0;
$eq = static function (string $label, mixed $expected, mixed $actual) use (&$failures): void {
    if ($expected === $actual) {
        fwrite(STDOUT, "ok   {$label}\n");

        return;
    }
    fwrite(STDERR, "FAIL {$label}\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n");
    $failures++;
};
$contains = static function (string $label, string $needle, string $haystack) use (&$failures): void {
    if (str_contains($haystack, $needle)) {
        fwrite(STDOUT, "ok   {$label}\n");

        return;
    }
    fwrite(STDERR, "FAIL {$label}\n  missing: {$needle}\n  in: {$haystack}\n");
    $failures++;
};

$pdo = Database::connection();
$userId = (int) $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'ADMIN' ORDER BY u.id LIMIT 1")->fetchColumn();
$_SESSION['user_id'] = $userId;
forget_auth_user();
$stamp = date('His');
$art = new ArtworkProofingService();
$repo = new ArtworkProofingRepository();

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$pdf = "%PDF-1.4\n1 0 obj << /Type /Pages /Count 2 /Kids [] >> endobj\ntrailer <<>>\n%%EOF";

$eq('effective dpi', 0, Decimal::cmp($art->effectiveDpi(3000, '1000'), '76.2'));
$cdr = $art->inspect('logo.cdr', 'RIFF....CDR', '1000', '600');
$eq('cdr colour unknown', 'UNKNOWN', $cdr['colour_mode']);
$eq('cdr not verified', false, $cdr['verified']);
$contains('cdr message', 'Not automatically verified.', $cdr['message']);
$pages = $art->inspect('proof.pdf', $pdf, null, null);
$eq('pdf colour unknown', 'UNKNOWN', $pages['colour_mode']);
$eq('pdf pages', 2, $pages['page_count']);
$eq('malicious pdf', 'That PDF was rejected. The file is not a PDF.', $art->rejectUpload('quote.pdf', 'MZ fake'));
$eq('production only skips customer', false, $art->customerReapprovalRequired('PRODUCTION_ONLY'));
$eq('visible needs customer', true, $art->customerReapprovalRequired('CUSTOMER_VISIBLE'));
$eq('bleed impact', 'RE_RELEASE_REQUIRED', $art->changeImpact('PRODUCTION_ONLY'));
$eq('phone impact', 'CUSTOMER_REAPPROVAL_REQUIRED', $art->changeImpact('CUSTOMER_VISIBLE'));

$pdo->prepare('INSERT INTO customers (customer_type, company_name, active, created_by) VALUES (\'BUSINESS\', ?, 1, ?)')->execute(['Phase7 ' . $stamp, $userId]);
$customerId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO customers (customer_type, company_name, active, created_by) VALUES (\'BUSINESS\', ?, 1, ?)')->execute(['Other7 ' . $stamp, $userId]);
$otherId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO quotes (quote_number, customer_id, quote_date, status, created_by) VALUES (?,?,?,?,?)')->execute(['Q7-' . $stamp, $customerId, date('Y-m-d'), 'ACCEPTED', $userId]);
$quoteId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO jobs (job_number, customer_id, quote_id, quote_revision_number, title, status, created_by) VALUES (?,?,?,?,?,?,?)')->execute(['SFJ-P7-' . $stamp, $customerId, $quoteId, 1, 'ACM 600 x 400', 'NEW', $userId]);
$jobId = (int) $pdo->lastInsertId();

$made = $art->create($jobId, [
    'title' => 'Store Entrance Sign',
    'finished_width_mm' => '600',
    'finished_height_mm' => '400',
    'scale_label' => '1:1',
    'production_file_required' => 1,
    'change_summary' => 'Initial concept',
], $userId);
$eq('artwork created', [], $made['errors']);
$contains('artwork number', 'SFAW-', (string) $made['number']);
$artworkId = (int) $made['id'];
$current = $repo->revision((int) $repo->artwork($artworkId)['current_revision_id']);
$eq('starts at R1', 'R1', $current['revision_label']);

$sent = $art->sendProof((int) $current['id'], ['name' => 'r1.png', 'bytes' => $png], $userId);
$eq('r1 sent', [], $sent['errors']);
$r1Proof = (string) $repo->proof((int) $sent['id'])['sha256'];
$locked = $art->storeFile($artworkId, (int) $current['id'], 'ARTWORK_SOURCE', 'INTERNAL', ['name' => 'r1.cdr', 'bytes' => 'CDR-R1'], $userId);
$contains('r1 locked', 'new revision', (string) ($locked['errors']['_form'] ?? ''));
$eq('r1 hash unchanged', $r1Proof, (string) $repo->proof((int) $sent['id'])['sha256']);

$pin = $art->annotate((int) $sent['id'], [
    'kind' => 'PIN', 'x' => '0.62', 'y' => '0.31', 'page_number' => 1,
    'body' => 'Make phone number larger.', 'visibility' => 'CUSTOMER_SHARED', 'annotation_type' => 'CHANGE_REQUEST',
], 'PORTAL', null, 1, 'Customer');
$eq('pin stored', [], $pin['errors']);
$storedPin = $repo->annotation((int) $pin['id']);
$eq('pin x', 0, Decimal::cmp((string) $storedPin['x'], '0.62'));
$eq('pin y', 0, Decimal::cmp((string) $storedPin['y'], '0.31'));
$contains('pin escaped', '&lt;script&gt;', e('<script>alert(1)</script>'));
$script = $art->annotate((int) $sent['id'], [
    'kind' => 'PIN', 'x' => '0.10', 'y' => '0.10', 'body' => '<script>alert(1)</script>',
], 'PORTAL', null, 1, 'Customer');
$contains('script stored raw', '<script>', (string) $repo->annotation((int) $script['id'])['body']);
$contains('script rendered', '&lt;script&gt;', e((string) $repo->annotation((int) $script['id'])['body']));

$internal = $art->annotate((int) $sent['id'], [
    'kind' => 'PIN', 'x' => '0.20', 'y' => '0.20', 'body' => 'Internal bleed note', 'visibility' => 'INTERNAL_ONLY', 'annotation_type' => 'INTERNAL_NOTE',
], 'STAFF', $userId, null, 'Designer');
$eq('customer hides internal', 0, count(array_filter(
    $art->annotationsFor((int) $sent['id'], true, 1),
    static fn (array $row): bool => (int) $row['id'] === (int) $internal['id']
)));

$page2 = $art->annotate((int) $sent['id'], [
    'kind' => 'PIN', 'x' => '0.40', 'y' => '0.40', 'page_number' => 2, 'body' => 'Page two only',
], 'PORTAL', null, 1, 'Customer');
$eq('page two hidden on page one', 0, count(array_filter(
    $art->annotationsFor((int) $sent['id'], true, 1),
    static fn (array $row): bool => (int) $row['id'] === (int) $page2['id']
)));
$eq('page two visible on page two', 1, count(array_filter(
    $art->annotationsFor((int) $sent['id'], true, 2),
    static fn (array $row): bool => (int) $row['id'] === (int) $page2['id']
)));

$r2 = $art->revise($artworkId, ['change_summary' => 'Phone number larger', 'change_class' => 'CUSTOMER_VISIBLE', 'reason_code' => 'CUSTOMER_CHANGE'], $userId);
$eq('r2 label', 'R2', $r2['label']);
$eq('r2 needs approval', true, $r2['customer_reapproval']);
$r2again = $art->revise($artworkId, ['change_summary' => 'Second designer pass', 'change_class' => 'CUSTOMER_VISIBLE', 'reason_code' => 'INTERNAL_CORRECTION', 'source_revision_id' => $r2['id']], $userId);
$eq('unique r3', 'R3', $r2again['label']);
$eq('r1 still sent', 'SENT', (string) $repo->revision((int) $current['id'])['status']);
$resolved = $art->resolve((int) $pin['id'], (int) $r2['id'], $userId);
$eq('resolved', [], $resolved['errors']);
$eq('resolved in r2', (int) $r2['id'], (int) $repo->annotation((int) $pin['id'])['resolved_revision_id']);

$sent2 = $art->sendProof((int) $r2again['id'], ['name' => 'r3.png', 'bytes' => $png . 'R3'], $userId);
$eq('r3 proof', [], $sent2['errors']);
$approved = $art->approve((int) $sent2['id'], (int) $r2again['id'], 'A. Customer', 'I confirm that I have checked and approve this artwork for production, including spelling, contact details, layout and content.', '1', null, null, '127.0.0.1', 'test');
$eq('approved', [], $approved['errors']);
$approval = $repo->approvalForRevision((int) $r2again['id']);
$eq('approval is r3', (int) $r2again['id'], (int) $approval['revision_id']);
$eq('approval proof', (int) $sent2['id'], (int) $approval['proof_id']);

$r4 = $art->revise($artworkId, ['change_summary' => 'Phone number changed', 'change_class' => 'CUSTOMER_VISIBLE', 'reason_code' => 'CUSTOMER_CHANGE'], $userId);
$eq('r4', 'R4', $r4['label']);
$eq('r3 approval remains', (int) $r2again['id'], (int) $repo->approvalForRevision((int) $r2again['id'])['revision_id']);
$eq('r4 not approved', null, $repo->approvalForRevision((int) $r4['id']));
$eq('parent approval cleared', 0, (int) $repo->artwork($artworkId)['customer_approved']);
$sent4 = $art->sendProof((int) $r4['id'], ['name' => 'r4.png', 'bytes' => $png . 'R4'], $userId);
$share = $art->share((int) $sent2['id'], 'APPROVE', 2, $userId);
$deniedNew = $art->approve((int) $sent4['id'], (int) $r4['id'], 'A. Customer', 'statement', '1', null, (int) $share['id'], '127.0.0.1', 'test');
$contains('old link cannot approve r4', 'cannot approve', (string) ($deniedNew['errors']['_form'] ?? ''));
$opened = $art->openShare((string) $share['token'], $artworkId + 9);
$contains('share idor', 'does not open', (string) ($opened['errors']['_form'] ?? ''));
$pdo->prepare('UPDATE artwork_share_tokens SET expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = ?')->execute([(int) $share['id']]);
$expired = $art->openShare((string) $share['token'], $artworkId);
$contains('share expired', 'expired', (string) ($expired['errors']['_form'] ?? ''));

$art->approve((int) $sent4['id'], (int) $r4['id'], 'A. Customer', 'statement', '1', null, null, '127.0.0.1', 'test');
$before = (int) $repo->artwork($artworkId)['customer_approved'];
$bleed = $art->revise($artworkId, ['change_summary' => 'Bleed only', 'change_class' => 'PRODUCTION_ONLY', 'reason_code' => 'PRODUCTION_CORRECTION'], $userId);
$eq('bleed keeps approval', 1, (int) $repo->artwork($artworkId)['customer_approved']);
$eq('bleed no customer', false, $bleed['customer_reapproval']);
$eq('approval still historical', 1, $before);

$pf1 = $art->productionFile($artworkId, (int) $bleed['id'], 'PRINT', ['name' => 'print.pdf', 'bytes' => $pdf], $userId);
$eq('pf1', 'PF1', $pf1['label']);
$eq('pf1 not approved yet', 'DRAFT', (string) $repo->productionFile((int) $pf1['id'])['status']);
$ready = (new ReleaseReadinessService())->evaluate($jobId);
$fileBlocked = false;
foreach ($ready['checks'] as $check) {
    if ($check['check_code'] === 'PRODUCTION_FILE_APPROVED' && $check['result'] === 'BLOCK') {
        $fileBlocked = true;
    }
}
$eq('release blocked without production approval', true, $fileBlocked);
$art->confirmPreflight((int) $pf1['id'], 'BLEED', 'HUMAN_CONFIRMED', $userId);
$art->approveProductionFile((int) $pf1['id'], $userId);
$eq('pf1 approved', 'APPROVED_FOR_PRODUCTION', (string) $repo->productionFile((int) $pf1['id'])['status']);
$pf2 = $art->productionFile($artworkId, (int) $bleed['id'], 'PRINT', ['name' => 'print2.pdf', 'bytes' => $pdf . 'PF2'], $userId);
$eq('pf2', 'PF2', $pf2['label']);
$art->approveProductionFile((int) $pf2['id'], $userId);
$pdo->prepare('INSERT INTO production_releases (release_number, job_id, release_version, status) VALUES (?,?,1,\'RELEASED\')')->execute(['SFR-P7-' . $stamp, $jobId]);
$releaseId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO artwork_release_snapshots (release_id, artwork_id, revision_id, production_file_id, file_hash) VALUES (?,?,?,?,?)')->execute([
    $releaseId, $artworkId, (int) $bleed['id'], (int) $pf1['id'], (string) $repo->productionFile((int) $pf1['id'])['sha256'],
]);
$art->supersedeProductionFile((int) $pf1['id'], (int) $pf2['id'], $userId);
$eq('release review', 'REVIEW_REQUIRED', (string) $pdo->query('SELECT status FROM production_releases WHERE id = ' . $releaseId)->fetchColumn());
$eq('pf1 superseded', 'SUPERSEDED', (string) $repo->productionFile((int) $pf1['id'])['status']);
$shop = $art->workshop($jobId);
$eq('workshop current', (int) $pf2['id'], (int) ($shop['current']['id'] ?? 0));
$labels = array_map(static fn (array $row): string => (string) $row['status'], $shop['files']);
$eq('old file still listed', true, in_array('SUPERSEDED', $labels, true));

$first = $art->storeFile($artworkId, null, 'CUSTOMER_SOURCE', 'CUSTOMER', ['name' => 'logo.png', 'bytes' => $png], $userId);
$second = $art->storeFile($artworkId, null, 'CUSTOMER_SOURCE', 'CUSTOMER', ['name' => 'logo-copy.png', 'bytes' => $png], $userId);
$eq('duplicate kept', true, $second['id'] !== null && $second['id'] !== $first['id']);
$eq('duplicate warning', 'This exact file already exists.', $second['warning']);
$source = $art->storeFile($artworkId, null, 'ARTWORK_SOURCE', 'INTERNAL', ['name' => 'working.cdr', 'bytes' => 'CDR-WORKING'], $userId);
$eq('portal source denied', false, $art->canDownloadSource($repo->file((int) $source['id']), true));

$designId = (int) $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'DESIGN' ORDER BY u.id LIMIT 1")->fetchColumn();
$_SESSION['user_id'] = $designId;
forget_auth_user();
$designApprove = $art->approveProductionFile((int) $pf2['id'], $designId);
$contains('designer cannot approve production file', 'cannot approve', (string) ($designApprove['errors']['_form'] ?? ''));
$_SESSION['user_id'] = $userId;
forget_auth_user();

$eq('other customer', null, $repo->artworkForCustomer($otherId, $artworkId));

$art->requirePhysical($artworkId, 'VINYL-1', 'VINYL_SWATCH', $userId);
$physical = (new ReleaseReadinessService())->evaluate($jobId);
$sampleBlocked = false;
foreach ($physical['checks'] as $check) {
    if ($check['check_code'] === 'PHYSICAL_SAMPLE' && $check['result'] === 'BLOCK') {
        $sampleBlocked = true;
    }
}
$eq('physical sample blocks', true, $sampleBlocked);
$art->approvePhysical($artworkId, 'A. Customer', $userId);
$cleared = (new ReleaseReadinessService())->evaluate($jobId);
$sampleStill = false;
foreach ($cleared['checks'] as $check) {
    if ($check['check_code'] === 'PHYSICAL_SAMPLE' && $check['result'] === 'BLOCK') {
        $sampleStill = true;
    }
}
$eq('sample approval clears gate', false, $sampleStill);

$brand = $art->brandVersion($customerId, 'Primary logo', ['name' => 'logo.png', 'bytes' => $png], $userId);
$art->linkBrand($artworkId, (int) $brand['id']);
$art->brandVersion($customerId, 'Primary logo', ['name' => 'logo-new.png', 'bytes' => $png . 'NEW'], $userId);
$eq('brand warning', 'Brand asset has changed since this artwork was approved.', $art->brandWarning($artworkId));
$eq('artwork number unchanged', (string) $made['number'], (string) $repo->artwork($artworkId)['artwork_number']);

$reorder = (new CustomerHubService())->assessReorder([
    'product_active' => true,
    'customer_active' => true,
    'material_current' => true,
    'specification_current' => true,
    'artwork_status' => 'APPROVED',
    'artwork_change' => false,
    'price_valid' => true,
    'artwork_id' => $artworkId,
]);
$eq('reorder reuses artwork', $artworkId, $reorder['artwork_id']);

$pdo->prepare('INSERT INTO projects (project_number, customer_id, name, status) VALUES (?,?,?,?)')->execute(['P7-' . $stamp, $customerId, 'Branches', 'ACTIVE']);
$projectId = (int) $pdo->lastInsertId();
$siteArt = [];
for ($n = 1; $n <= 25; $n++) {
    $pdo->prepare('INSERT INTO project_sites (project_id, site_code, site_name, status) VALUES (?,?,?,?)')->execute([$projectId, 'S' . $n . $stamp, 'Branch ' . $n, 'NOT_STARTED']);
    $siteId = (int) $pdo->lastInsertId();
    $row = $art->create($jobId, [
        'title' => 'Fascia ' . $n,
        'project_id' => $projectId,
        'site_id' => $siteId,
        'master_artwork_id' => $artworkId,
        'reuse_class' => 'SITE_SPECIFIC',
        'variables' => ['branch_name' => 'Branch ' . $n, 'site_code' => 'S' . $n],
    ], $userId);
    $siteArt[] = (int) $row['id'];
}
$art->sendProof((int) $repo->artwork($siteArt[0])['current_revision_id'], ['name' => 'site.png', 'bytes' => $png . 'SITE'], $userId);
$siteProof = $repo->latestProof((int) $repo->artwork($siteArt[0])['current_revision_id']);
$art->approve((int) $siteProof['id'], (int) $repo->artwork($siteArt[0])['current_revision_id'], 'Buyer', 'statement', '1', null, null, '127.0.0.1', 'test');
$counts = $art->projectCounts($projectId);
$eq('one site approved', 1, (int) ($counts['APPROVED'] ?? 0));
$eq('other sites not approved', 0, (int) $repo->artwork($siteArt[1])['customer_approved']);

$started = microtime(true);
for ($n = 0; $n < 200; $n++) {
    $repo->insertFile([
        'artwork_id' => $artworkId,
        'revision_id' => null,
        'customer_id' => $customerId,
        'job_id' => $jobId,
        'project_id' => null,
        'site_id' => null,
        'asset_id' => null,
        'category' => 'REFERENCE',
        'visibility' => 'INTERNAL',
        'original_filename' => 'pack-' . $n . '.pdf',
        'stored_filename' => 'artwork/missing-' . $n . '.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'file_size' => 100 + $n,
        'sha256' => hash('sha256', 'pack-' . $stamp . '-' . $n),
        'uploaded_by' => $userId,
    ]);
}
$found = $art->search('pack-3.pdf');
$elapsed = microtime(true) - $started;
$eq('search finds a page', true, $found !== []);
$eq('search under five seconds', true, $elapsed < 5);

$chain = $pdo->query(
    'SELECT a.id AS artwork_id, r.id AS revision_id, p.id AS proof_id, ap.id AS approval_id, f.id AS file_id
     FROM job_artworks a
     JOIN artwork_revisions r ON r.artwork_id = a.id
     JOIN artwork_proofs p ON p.revision_id = r.id
     JOIN artwork_approvals ap ON ap.revision_id = r.id
     JOIN production_files f ON f.artwork_id = a.id
     WHERE a.id = ' . $artworkId . ' LIMIT 1'
)->fetch();
$eq('restore chain', true, is_array($chain) && (int) $chain['artwork_id'] === $artworkId);

$manifest = $art->manifest($artworkId);
$eq('manifest job', 'SFJ-P7-' . $stamp, $manifest['job_number']);
$eq('proof message', 'Your artwork proof R3 for Job SFJ-P7-' . $stamp . ' is ready for review.', $art->proofMessage('SFJ-P7-' . $stamp, 'R3'));

fwrite(STDOUT, $failures === 0 ? "PHASE7_OK\n" : "PHASE7_FAIL {$failures}\n");
exit($failures === 0 ? 0 : 1);
