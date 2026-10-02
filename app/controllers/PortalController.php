<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\FinanceRepository;
use App\Repositories\PortalRepository;
use App\Services\AttachmentService;
use App\Services\CommunicationService;
use App\Services\PortalAuthService;
use App\Services\PortalService;
use App\Services\QuotePdf;
use App\Services\SettingsService;
use App\Services\StatementService;

final class PortalController
{
    public function loginForm(): void
    {
        if ($this->optionalUser() !== null) {
            redirect('/portal');
        }
        View::render('portal/login', [
            'title' => 'Customer portal',
            'errors' => [],
        ], 'layouts/portal');
    }

    public function login(): void
    {
        $errors = (new PortalAuthService())->login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($errors !== []) {
            View::render('portal/login', ['title' => 'Customer portal', 'errors' => $errors, 'old' => $_POST], 'layouts/portal');

            return;
        }
        redirect('/portal');
    }

    public function access(string $token): void
    {
        $errors = (new PortalAuthService())->consumeLink($token);
        if ($errors !== []) {
            View::render('portal/login', ['title' => 'Customer portal', 'errors' => $errors], 'layouts/portal');

            return;
        }
        redirect('/portal');
    }

    public function logout(): void
    {
        (new PortalAuthService())->logout();
        redirect('/portal/login');
    }

    public function home(): void
    {
        $user = $this->user();
        View::render('portal/home', [
            'title' => 'Your account',
            'data' => (new PortalService())->dashboard($user),
            'user' => $user,
        ], 'layouts/portal');
    }

    public function assets(): void
    {
        $user = $this->user();
        View::render('portal/assets', [
            'title' => 'Your assets',
            'rows' => (new \App\Repositories\AssetRepository())->forCustomer((int) $user['customer_id']),
            'user' => $user,
        ], 'layouts/portal');
    }

    public function asset(string $id): void
    {
        $user = $this->user();
        $asset = (new \App\Repositories\AssetRepository())->find(route_id($id));
        if ($asset === null || !\App\Services\AssetAccess::portalOwns((int) $user['customer_id'], $asset)) {
            $this->denied();
        }
        View::render('portal/asset', [
            'title' => (string) $asset['asset_number'],
            'asset' => $asset,
            'warranties' => (new \App\Repositories\AssetRepository())->warranties((int) $asset['id']),
            'events' => (new \App\Repositories\AssetRepository())->events((int) $asset['id'], 40),
            'user' => $user,
        ], 'layouts/portal');
    }

    public function reportAsset(string $id): void
    {
        $user = $this->user();
        $asset = (new \App\Repositories\AssetRepository())->find(route_id($id));
        if ($asset === null || !\App\Services\AssetAccess::portalOwns((int) $user['customer_id'], $asset)) {
            $this->denied();
        }
        $result = (new \App\Services\ServiceRequestService())->create([
            'asset_id' => (int) $asset['id'],
            'customer_id' => (int) $user['customer_id'],
            'description' => (string) ($_POST['description'] ?? ''),
            'source' => 'CUSTOMER_PORTAL',
            'reported_by' => (string) ($user['name'] ?? 'Customer'),
            'priority' => 'NORMAL',
        ], 0, true);
        if ($result['id'] === null) {
            flash('error', implode(' ', $result['errors']));
        } else {
            flash('success', 'Problem reported. Warranty is not approved until Sign-Forge reviews it.');
        }
        redirect('/portal/assets/' . $asset['id']);
    }

    public function quote(string $id): void
    {
        $user = $this->user();
        $quote = (new PortalService())->quote($user, route_id($id));
        if ($quote === null) {
            $this->denied();
        }
        View::render('portal/quote', [
            'title' => (string) $quote['quote_number'],
            'quote' => $quote,
            'items' => (new \App\Repositories\QuoteRepository())->items((int) $quote['id']),
            'statement' => SettingsService::get('quote_acceptance_statement', 'I accept this quotation.'),
            'user' => $user,
        ], 'layouts/portal');
    }

    public function quotePdf(string $id): void
    {
        $binary = (new PortalService())->quotePdf($this->user(), route_id($id));
        if ($binary === null) {
            $this->denied();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="quotation.pdf"');
        echo $binary;
        exit;
    }

    public function acceptQuote(string $id): void
    {
        $errors = (new PortalService())->acceptQuote($this->user(), route_id($id), $_POST);
        $this->back($errors, '/portal/quotes/' . route_id($id), 'Quotation accepted. Thank you.');
    }

    public function declineQuote(string $id): void
    {
        $errors = (new PortalService())->declineQuote($this->user(), route_id($id), $_POST);
        $this->back($errors, '/portal/quotes/' . route_id($id), 'Quotation declined.');
    }

    public function quoteChange(string $id): void
    {
        $errors = (new PortalService())->requestQuoteChange($this->user(), route_id($id), $_POST);
        $this->back($errors, '/portal/quotes/' . route_id($id), 'Your message was sent. The quotation itself is unchanged.');
    }

    public function artwork(string $id): void
    {
        $user = $this->user();
        $art = (new PortalService())->artwork($user, route_id($id));
        if ($art === null) {
            $this->denied();
        }
        View::render('portal/artwork', [
            'title' => (string) $art['title'],
            'artwork' => $art,
            'statement' => SettingsService::get('artwork_approval_statement', ''),
            'user' => $user,
        ], 'layouts/portal');
    }

    public function artworkFile(string $id): void
    {
        $art = (new PortalService())->artwork($this->user(), route_id($id));
        if ($art === null) {
            $this->denied();
        }
        $relative = (string) $art['stored_filename'];
        if (str_contains($relative, '..')) {
            $this->denied();
        }
        $path = base_path('storage/uploads/' . $relative);
        if (!is_file($path)) {
            abort_not_found('That file is missing.');
        }
        header('Content-Type: ' . (string) $art['mime_type']);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string) $art['original_filename']) . '"');
        readfile($path);
        exit;
    }

    public function approveArtwork(string $id): void
    {
        $errors = (new PortalService())->approveArtwork($this->user(), route_id($id), $_POST);
        $this->back($errors, '/portal/artwork/' . route_id($id), 'Artwork approved.');
    }

    public function artworkChange(string $id): void
    {
        $errors = (new PortalService())->requestArtworkChange($this->user(), route_id($id), $_POST);
        $this->back($errors, '/portal/artwork/' . route_id($id), 'Change request sent. The current artwork file was not replaced.');
    }

    public function job(string $id): void
    {
        $job = (new PortalService())->job($this->user(), route_id($id));
        if ($job === null) {
            $this->denied();
        }
        View::render('portal/job', ['title' => (string) $job['job_number'], 'job' => $job], 'layouts/portal');
    }

    public function invoice(string $id): void
    {
        $user = $this->user();
        $invoice = (new PortalRepository())->invoice((int) $user['customer_id'], route_id($id));
        if ($invoice === null) {
            $this->denied();
        }
        (new PortalRepository())->audit((int) $user['customer_id'], (int) $user['id'], 'INVOICE_VIEWED', 'invoice', (int) $invoice['id'], (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        View::render('portal/invoice', ['title' => (string) $invoice['invoice_number'], 'invoice' => $invoice], 'layouts/portal');
    }

    public function invoicePdf(string $id): void
    {
        $user = $this->user();
        $invoice = (new PortalRepository())->invoice((int) $user['customer_id'], route_id($id));
        if ($invoice === null) {
            $this->denied();
        }
        $full = (new FinanceRepository())->invoice((int) $invoice['id']);
        $html = View::capture('invoices/pdf', [
            'invoice' => $full,
            'items' => (new FinanceRepository())->items((int) $invoice['id']),
            'company' => (string) ($full['company_name_snapshot'] ?: SettingsService::get('company_name', '')),
        ]);
        $binary = (new QuotePdf())->render($html);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="invoice.pdf"');
        echo $binary;
        exit;
    }

    public function statement(): void
    {
        $user = $this->user();
        $from = date('Y-m-01', strtotime('-11 months'));
        $to = date('Y-m-d');
        $built = (new StatementService())->build((int) $user['customer_id'], $from, $to);
        $html = View::capture('finance/statement_pdf', [
            'statement' => $built,
            'company' => (string) SettingsService::get('company_name', 'Sign-Forge'),
            'bank' => [
                'name' => SettingsService::get('bank_name', ''),
                'account' => SettingsService::get('account_name', ''),
                'number' => SettingsService::get('account_number', ''),
                'branch' => SettingsService::get('branch_code', ''),
            ],
        ]);
        $binary = (new QuotePdf())->render($html);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="statement.pdf"');
        echo $binary;
        exit;
    }

    public function upload(): void
    {
        $user = $this->user();
        $file = $_FILES['file'] ?? [];
        $name = (string) ($file['name'] ?? '');
        $tmp = (string) ($file['tmp_name'] ?? '');
        $bytes = is_file($tmp) ? (string) file_get_contents($tmp) : '';
        $rejected = (new \App\Services\CustomerHubService())->rejectFile($name, $bytes);
        if ($rejected !== []) {
            $this->back($rejected, '/portal', '');
        }
        $errors = (new AttachmentService())->store(
            'customer',
            (int) $user['customer_id'],
            $_FILES['file'] ?? [],
            0,
            strtoupper(trim((string) ($_POST['purpose'] ?? 'REFERENCE'))),
            blank_to_null($_POST['notes'] ?? null),
            true,
            [
                'visibility' => 'CUSTOMER_UPLOADED',
                'portal_user_id' => (int) $user['id'],
            ]
        );
        if ($errors === []) {
            (new PortalRepository())->audit((int) $user['customer_id'], (int) $user['id'], 'FILE_UPLOADED', 'customer', (int) $user['customer_id'], (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
            (new \App\Services\NotificationService())->send(null, null, 'SYSTEM', 'Customer file uploaded', 'A file was uploaded in the portal.', 'customer', (int) $user['customer_id'], 'NORMAL', 'portal-file-' . (int) $user['id'] . '-' . date('YmdHis'));
        }
        $this->back($errors, '/portal', 'File uploaded.');
    }

    public function file(string $id): void
    {
        $user = $this->user();
        $row = (new PortalRepository())->attachment((int) $user['customer_id'], route_id($id));
        if ($row === null) {
            $this->denied();
        }
        $file = (new AttachmentService())->download((int) $row['id']);
        if ($file === null) {
            abort_not_found('That file is missing.');
        }
        header('Content-Type: ' . $file['mime']);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $file['name']) . '"');
        readfile($file['path']);
        exit;
    }

    public function message(): void
    {
        $user = $this->user();
        $type = strtolower(trim((string) ($_POST['entity_type'] ?? 'job')));
        $entityId = (int) ($_POST['entity_id'] ?? 0);
        if (!$this->owns($user, $type, $entityId)) {
            $this->denied();
        }
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body === '') {
            $this->back(['body' => 'Write a message.'], '/portal', '');
        }
        (new PortalRepository())->insertMessage((int) $user['customer_id'], (int) $user['id'], $type, $entityId, $body);
        (new PortalRepository())->audit((int) $user['customer_id'], (int) $user['id'], 'MESSAGE', $type, $entityId, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        (new \App\Services\NotificationService())->send(null, null, 'SYSTEM', 'Portal message', mb_substr($body, 0, 180), $type, $entityId, 'NORMAL', 'portal-msg-' . $entityId . '-' . date('YmdHis'));
        flash('success', 'Message sent.');
        redirect('/portal');
    }

    public function whatsapp(string $id): void
    {
        $user = $this->user();
        $quote = (new PortalRepository())->quote((int) $user['customer_id'], route_id($id));
        if ($quote === null) {
            $this->denied();
        }
        $text = 'Quotation ' . $quote['quote_number'] . ' revision ' . $quote['revision_number'] . ' total ' . SettingsService::symbol() . $quote['total'] . '.';
        $link = (new CommunicationService())->whatsappLink((string) SettingsService::get('telephone', ''), $text);
        View::render('portal/whatsapp', ['title' => 'WhatsApp', 'text' => $text, 'link' => $link], 'layouts/portal');
    }

    /**
     * @return array<string, mixed>
     */
    public function signedDocument(string $id): void
    {
        $user = $this->user();
        $doc = (new \App\Repositories\WorkshopRepository())->document((int) $id);
        $allowed = ['DELIVERY_NOTE', 'COLLECTION_NOTE', 'COMPLETION_CERTIFICATE', 'PROOF_OF_DELIVERY'];
        if ($doc === null || !in_array((string) $doc['document_type'], $allowed, true)) {
            $this->denied();
        }
        $owned = false;
        foreach ((new \App\Repositories\WorkshopRepository())->customerDocuments((int) $user['customer_id']) as $row) {
            if ((int) $row['id'] === (int) $doc['id']) {
                $owned = true;
            }
        }
        if (!$owned) {
            $this->denied();
        }
        $path = base_path((string) $doc['file_path']);
        if (!is_file($path)) {
            $this->denied();
        }
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function user(): array
    {
        $user = (new PortalAuthService())->user();
        if ($user === null) {
            redirect('/portal/login');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function optionalUser(): ?array
    {
        return (new PortalAuthService())->user();
    }

    /**
     * @param array<string, string> $errors
     */
    private function back(array $errors, string $path, string $ok): void
    {
        if ($errors !== []) {
            flash('error', implode(' ', $errors));
        } else {
            flash('success', $ok);
        }
        redirect($path);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function owns(array $user, string $type, int $id): bool
    {
        $customerId = (int) $user['customer_id'];
        $portal = new PortalRepository();

        return match ($type) {
            'quote' => $portal->quote($customerId, $id) !== null,
            'job' => $portal->job($customerId, $id) !== null,
            'invoice' => $portal->invoice($customerId, $id) !== null,
            'artwork' => $portal->artwork($customerId, $id) !== null,
            default => false,
        };
    }

    private function denied(): never
    {
        http_response_code(403);
        View::render('portal/denied', ['title' => 'Not available'], 'layouts/portal');
        exit;
    }
}
