<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\AcceptanceMethod;
use App\Domain\DiscountType;
use App\Domain\QuoteStatus;
use App\Domain\VatMode;
use App\Helpers\View;
use App\Repositories\AttachmentRepository;
use App\Repositories\AuditRepository;
use App\Repositories\ContactRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\JobRepository;
use App\Repositories\PricingLevelRepository;
use App\Repositories\ProductRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\SiteSurveyRepository;
use App\Repositories\UserRepository;
use App\Services\QuoteConflictException;
use App\Services\QuotePdf;
use App\Services\QuoteService;
use App\Services\SiteSurveyService;
use App\Services\QuoteTotals;
use App\Services\SettingsService;

/**
 * Quotation screens. Money is calculated in QuoteService before anything is stored.
 */
final class QuoteController
{
    public function index(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => strtoupper(trim((string) ($_GET['status'] ?? ''))),
            'assigned_to' => (int) ($_GET['assigned_to'] ?? 0),
            'customer_id' => (int) ($_GET['customer_id'] ?? 0),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
            'expired' => (string) ($_GET['expired'] ?? '') === '1',
            'sort' => (string) ($_GET['sort'] ?? 'newest'),
        ];
        View::render('quotes/index', [
            'title' => 'Quotations',
            'activeNav' => 'quotes',
            'rows' => (new QuoteRepository())->search($filters),
            'filters' => $filters,
            'users' => (new UserRepository())->listAll(),
            'canManage' => can('quotes.manage'),
        ]);
    }

    public function create(): void
    {
        $customerId = (int) ($_GET['customer_id'] ?? 0);
        $opportunityId = (int) ($_GET['opportunity_id'] ?? 0);
        $surveyId = (int) ($_GET['survey_id'] ?? 0);
        $survey = $surveyId > 0 ? (new SiteSurveyRepository())->find($surveyId) : null;
        if ($survey !== null) {
            $customerId = (int) $survey['customer_id'];
            if ($opportunityId < 1) {
                $opportunityId = (int) ($survey['opportunity_id'] ?? 0);
            }
        }
        $this->form([
            'customer_id' => $customerId,
            'opportunity_id' => $opportunityId,
            'survey_id' => $survey !== null ? (int) $survey['id'] : 0,
            'quote_date' => date('Y-m-d'),
            'vat_mode' => 'EXCLUSIVE',
            'assigned_to' => (int) (auth_user()['id'] ?? 0),
        ], []);
    }

    public function store(): void
    {
        $result = (new QuoteService())->create($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            $this->form($_POST, $result['errors']);

            return;
        }
        $surveyId = (int) ($_POST['survey_id'] ?? 0);
        if ($surveyId > 0) {
            $survey = (new SiteSurveyRepository())->find($surveyId);
            $quote = (new QuoteRepository())->find((int) $result['id']);
            if ($survey !== null && $quote !== null && (int) $survey['customer_id'] === (int) $quote['customer_id']) {
                (new SiteSurveyService())->linkQuote($surveyId, (int) $result['id']);
            }
        }
        flash('success', 'Quotation created. Add the lines next.');
        redirect('/quotes/' . $result['id'] . '/edit');
    }

    public function show(string $id): void
    {
        $quote = $this->quote($id);
        $this->renderShow($quote, false);
    }

    public function edit(string $id): void
    {
        $quote = $this->quote($id);
        if ((string) $quote['status'] !== 'DRAFT') {
            flash('warning', 'This quotation is locked. Create a revision to change the prices.');
            redirect('/quotes/' . $quote['id']);
        }
        $repo = new QuoteRepository();
        View::render('quotes/builder', $this->page($quote, $repo, [
            'products' => (new ProductRepository())->calculatorCatalogue(),
            'errors' => [],
            'scripts' => ['assets/js/quote-builder.js'],
        ]));
    }

    public function followUp(string $id): void
    {
        $quote = $this->quote($id);
        if (!can('quotes.manage') || (string) $quote['status'] !== 'SENT') {
            deny_access('A follow-up date can be set on a sent quotation.');
        }
        $date = trim((string) ($_POST['next_follow_up_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            flash('error', 'Enter a follow-up date.');
            redirect('/quotes/' . $quote['id']);
        }
        (new QuoteRepository())->setFollowUp((int) $quote['id'], $date);
        flash('success', 'Follow-up date saved.');
        redirect('/quotes/' . $quote['id']);
    }

    public function save(string $id): void
    {
        $this->mutate((int) $id, function (QuoteService $service, int $quoteId, int $version, int $userId): array {
            return $service->save($quoteId, $_POST, $version, $userId, true);
        }, '/quotes/' . (int) $id . '/edit', 'Quotation saved.');
    }

    public function autosave(string $id): void
    {
        $quoteId = route_id($id);
        $version = (int) ($_POST['version_number'] ?? 0);
        try {
            $errors = (new QuoteService())->save($quoteId, $_POST, $version, (int) auth_user()['id'], false);
        } catch (QuoteConflictException $e) {
            json_response(['ok' => false, 'conflict' => $e->getMessage()], 409);
        }
        if ($errors !== []) {
            json_response(['ok' => false, 'errors' => array_values($errors)], 422);
        }
        $fresh = (new QuoteRepository())->find($quoteId);
        json_response([
            'ok' => true,
            'saved' => true,
            'version_number' => (int) ($fresh['version_number'] ?? 0),
            'total' => (string) ($fresh['total'] ?? '0'),
        ]);
    }

    public function addLine(string $id): void
    {
        $quoteId = route_id($id);
        $result = (new QuoteService())->addProductLine($quoteId, $_POST, (int) ($_POST['version_number'] ?? 0), (int) auth_user()['id']);
        $this->afterLine($quoteId, $result, 'Line added.');
    }

    public function addCustom(string $id): void
    {
        $quoteId = route_id($id);
        $result = (new QuoteService())->addCustomLine($quoteId, $_POST, (int) ($_POST['version_number'] ?? 0), (int) auth_user()['id']);
        $this->afterLine($quoteId, $result, 'Custom line added.');
    }

    public function updateLine(string $id, string $lineId): void
    {
        $quoteId = route_id($id);
        $result = (new QuoteService())->updateLine(
            $quoteId,
            route_id($lineId),
            $_POST,
            (int) ($_POST['version_number'] ?? 0),
            (int) auth_user()['id']
        );
        $this->afterLine($quoteId, $result, 'Line updated.');
    }

    public function duplicateLine(string $id, string $lineId): void
    {
        $quoteId = route_id($id);
        $errors = (new QuoteService())->duplicateLine($quoteId, route_id($lineId), (int) ($_POST['version_number'] ?? 0), (int) auth_user()['id']);
        $this->finish($quoteId, $errors, 'Line duplicated.');
    }

    public function removeLine(string $id, string $lineId): void
    {
        $quoteId = route_id($id);
        $errors = (new QuoteService())->removeLine($quoteId, route_id($lineId), (int) ($_POST['version_number'] ?? 0), (int) auth_user()['id']);
        $this->finish($quoteId, $errors, 'Line removed.');
    }

    public function reorder(string $id): void
    {
        $quoteId = route_id($id);
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['line_id'] ?? []))));
        try {
            $errors = (new QuoteService())->reorder($quoteId, $ids, (int) ($_POST['version_number'] ?? 0), (int) auth_user()['id']);
        } catch (QuoteConflictException $e) {
            $this->conflict($quoteId, $e);

            return;
        }
        if ($this->wantsJson()) {
            json_response(['ok' => $errors === [], 'errors' => array_values($errors)]);
        }
        $this->finish($quoteId, $errors, 'Line order saved.');
    }

    public function addSection(string $id): void
    {
        $quoteId = route_id($id);
        try {
            $result = (new QuoteService())->addSection($quoteId, (string) ($_POST['title'] ?? ''), (int) ($_POST['version_number'] ?? 0), (int) auth_user()['id']);
        } catch (QuoteConflictException $e) {
            $this->conflict($quoteId, $e);

            return;
        }
        $this->finish($quoteId, $result['errors'], 'Section added.');
    }

    public function refresh(string $id): void
    {
        $quote = $this->quote($id);
        View::render('quotes/refresh', [
            'title' => 'Refresh prices',
            'activeNav' => 'quotes',
            'quote' => $quote,
            'rows' => (new QuoteService())->previewRefresh((int) $quote['id']),
        ]);
    }

    public function applyRefresh(string $id): void
    {
        $quoteId = route_id($id);
        try {
            $errors = (new QuoteService())->applyRefresh($quoteId, (int) ($_POST['version_number'] ?? 0), (int) auth_user()['id']);
        } catch (QuoteConflictException $e) {
            $this->conflict($quoteId, $e);

            return;
        }
        $this->finish($quoteId, $errors, 'Prices refreshed onto a new revision. The previous revision is unchanged.');
    }

    public function revise(string $id): void
    {
        $quoteId = route_id($id);
        try {
            $errors = (new QuoteService())->createRevision(
                $quoteId,
                (int) ($_POST['version_number'] ?? 0),
                (int) auth_user()['id'],
                trim((string) ($_POST['change_summary'] ?? ''))
            );
        } catch (QuoteConflictException $e) {
            $this->conflict($quoteId, $e);

            return;
        }
        if ($errors !== []) {
            flash('error', (string) reset($errors));
            redirect('/quotes/' . $quoteId);
        }
        flash('success', 'New revision started. The previous revision is still available.');
        redirect('/quotes/' . $quoteId . '/edit');
    }

    public function revision(string $id, string $number): void
    {
        $quote = $this->quote($id);
        $snapshot = (new QuoteService())->revisionSnapshot((int) $quote['id'], (int) $number);
        if ($snapshot === null || !isset($snapshot['quote'], $snapshot['items'])) {
            abort_not_found('That revision was not found.');
        }
        $stored = $snapshot['quote'];
        $stored['id'] = $quote['id'];
        View::render('quotes/show', [
            'title' => $quote['quote_number'] . ' revision ' . (int) $number,
            'activeNav' => 'quotes',
            'quote' => $stored,
            'items' => $snapshot['items'],
            'sections' => $snapshot['sections'] ?? [],
            'history' => [],
            'revisions' => (new QuoteRepository())->revisions((int) $quote['id']),
            'job' => null,
            'audit' => [],
            'attachments' => [],
            'historical' => true,
            'revisionNumber' => (int) $number,
            'canCost' => can('costing.view'),
            'canManage' => false,
            'canAccept' => false,
            'canConvert' => false,
            'expiry' => null,
            'summary' => (new QuoteTotals())->summarise($snapshot['items'], $stored),
        ]);
    }

    public function duplicateForm(string $id): void
    {
        View::render('quotes/duplicate', [
            'title' => 'Duplicate quotation',
            'activeNav' => 'quotes',
            'quote' => $this->quote($id),
        ]);
    }

    public function duplicate(string $id): void
    {
        $mode = (string) ($_POST['price_mode'] ?? 'KEEP') === 'CURRENT' ? 'CURRENT' : 'KEEP';
        $result = (new QuoteService())->duplicate(route_id($id), $mode, (int) auth_user()['id']);
        if ($result['id'] === null) {
            flash('error', (string) reset($result['errors']));
            redirect('/quotes/' . route_id($id));
        }
        flash('success', 'A new draft quotation was created.');
        redirect('/quotes/' . $result['id'] . '/edit');
    }

    public function status(string $id): void
    {
        $quoteId = route_id($id);
        try {
            $errors = (new QuoteService())->changeStatus(
                $quoteId,
                (string) ($_POST['status'] ?? ''),
                (int) ($_POST['version_number'] ?? 0),
                (int) auth_user()['id'],
                trim((string) ($_POST['notes'] ?? ''))
            );
        } catch (QuoteConflictException $e) {
            $this->conflict($quoteId, $e);

            return;
        }
        $this->finish($quoteId, $errors, 'Status updated.', '/quotes/' . $quoteId);
    }

    public function acceptForm(string $id): void
    {
        View::render('quotes/accept', [
            'title' => 'Accept quotation',
            'activeNav' => 'quotes',
            'quote' => $this->quote($id),
            'methods' => AcceptanceMethod::cases(),
            'errors' => [],
        ]);
    }

    public function accept(string $id): void
    {
        $quoteId = route_id($id);
        try {
            $errors = (new QuoteService())->accept($quoteId, $_POST, (int) ($_POST['version_number'] ?? 0), (int) auth_user()['id']);
        } catch (QuoteConflictException $e) {
            $this->conflict($quoteId, $e);

            return;
        }
        if ($errors !== []) {
            View::render('quotes/accept', [
                'title' => 'Accept quotation',
                'activeNav' => 'quotes',
                'quote' => $this->quote($id),
                'methods' => AcceptanceMethod::cases(),
                'errors' => $errors,
            ]);

            return;
        }
        flash('success', 'Acceptance recorded. The prices are now locked.');
        redirect('/quotes/' . $quoteId);
    }

    public function convertForm(string $id): void
    {
        $quote = $this->quote($id);
        $job = (new JobRepository())->findByQuote((int) $quote['id']);
        if ($job !== null) {
            redirect('/jobs/' . $job['id']);
        }
        View::render('quotes/convert', [
            'title' => 'Convert to job',
            'activeNav' => 'quotes',
            'quote' => $quote,
            'errors' => [],
            'old' => ['title' => $quote['company_name'] ?: 'Signage job', 'priority' => 'NORMAL'],
        ]);
    }

    public function convert(string $id): void
    {
        $quoteId = route_id($id);
        try {
            $result = (new QuoteService())->convert($quoteId, $_POST, (int) ($_POST['version_number'] ?? 0), (int) auth_user()['id']);
        } catch (QuoteConflictException $e) {
            $this->conflict($quoteId, $e);

            return;
        }
        if ($result['id'] === null) {
            $quote = $this->quote($id);
            View::render('quotes/convert', [
                'title' => 'Convert to job',
                'activeNav' => 'quotes',
                'quote' => $quote,
                'errors' => $result['errors'],
                'old' => $_POST,
            ]);

            return;
        }
        flash('success', 'Job created from the accepted quotation.');
        redirect('/jobs/' . $result['id']);
    }

    public function pdf(string $id): void
    {
        $quote = $this->quote($id);
        $revision = isset($_GET['revision']) ? (int) $_GET['revision'] : (int) $quote['revision_number'];
        $html = $this->documentHtml($quote, $revision);
        $pdf = new QuotePdf();
        $binary = $pdf->render($html);
        $filename = $pdf->filename((string) $quote['quote_number'], $revision);
        $dir = base_path('storage/quotes');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $cache = $dir . '/' . $filename;
        if (!is_file($cache)) {
            file_put_contents($cache, $binary);
        }
        (new \App\Services\AuditService())->record('quote', (int) $quote['id'], 'PDF_GENERATED', null, [
            'filename' => $filename,
            'revision' => $revision,
        ]);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($binary));
        echo $binary;
        exit;
    }

    public function printView(string $id): void
    {
        $quote = $this->quote($id);
        $revision = isset($_GET['revision']) ? (int) $_GET['revision'] : (int) $quote['revision_number'];
        echo $this->documentHtml($quote, $revision, true);
        exit;
    }

    /**
     * @param array<string, mixed> $quote
     */
    private function renderShow(array $quote, bool $historical): void
    {
        $repo = new QuoteRepository();
        View::render('quotes/show', [
            'title' => (string) $quote['quote_number'],
            'activeNav' => 'quotes',
            'quote' => $quote,
            'items' => $repo->items((int) $quote['id']),
            'sections' => $repo->sections((int) $quote['id']),
            'history' => $repo->history((int) $quote['id']),
            'revisions' => $repo->revisions((int) $quote['id']),
            'job' => (new JobRepository())->findByQuote((int) $quote['id']),
            'audit' => (new AuditRepository())->forEntity('quote', (int) $quote['id']),
            'attachments' => (new AttachmentRepository())->forEntity('quote', (int) $quote['id']),
            'historical' => $historical,
            'revisionNumber' => (int) $quote['revision_number'],
            'canCost' => can('costing.view'),
            'canManage' => can('quotes.manage'),
            'canAccept' => can('quotes.accept'),
            'canConvert' => can('quotes.convert'),
            'expiry' => QuoteService::expiryState($quote['expiry_date'] ?? null, (string) $quote['status']),
            'summary' => (new QuoteTotals())->summarise($repo->items((int) $quote['id']), $quote),
        ]);
    }

    /**
     * @param array<string, mixed> $quote
     */
    private function documentHtml(array $quote, int $revision, bool $print = false): string
    {
        if ($revision !== (int) $quote['revision_number']) {
            $snapshot = (new QuoteService())->revisionSnapshot((int) $quote['id'], $revision);
            if ($snapshot === null) {
                abort_not_found('That revision was not found.');
            }
        }

        return (new \App\Services\QuoteDocument())->html($quote, $revision, $print);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function form(array $old, array $errors): void
    {
        $customerId = (int) ($old['customer_id'] ?? 0);
        View::render('quotes/form', [
            'title' => 'New quotation',
            'activeNav' => 'quotes',
            'old' => $old,
            'errors' => $errors,
            'customers' => (new CustomerRepository())->search('', 'active', 300),
            'contacts' => $customerId > 0 ? (new ContactRepository())->forCustomer($customerId) : [],
            'levels' => (new PricingLevelRepository())->active(),
            'users' => (new UserRepository())->listAll(),
            'modes' => VatMode::cases(),
        ]);
    }

    /**
     * @param array<string, mixed> $quote
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function page(array $quote, QuoteRepository $repo, array $extra): array
    {
        $items = $repo->items((int) $quote['id']);

        return array_merge([
            'title' => 'Edit ' . $quote['quote_number'],
            'activeNav' => 'quotes',
            'quote' => $quote,
            'items' => $items,
            'summary' => (new QuoteTotals())->summarise($items, $quote),
            'sections' => $repo->sections((int) $quote['id']),
            'contacts' => (new ContactRepository())->forCustomer((int) $quote['customer_id']),
            'levels' => (new PricingLevelRepository())->active(),
            'users' => (new UserRepository())->listAll(),
            'modes' => VatMode::cases(),
            'discounts' => DiscountType::cases(),
            'statuses' => QuoteStatus::cases(),
            'canCost' => can('costing.view'),
            'canDiscount' => can('quotes.discount'),
            'canOverride' => can('quotes.price_override'),
            'expiry' => QuoteService::expiryState($quote['expiry_date'] ?? null, (string) $quote['status']),
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function quote(string $id): array
    {
        $quote = (new QuoteRepository())->find(route_id($id));
        if ($quote === null) {
            abort_not_found('That quotation was not found.');
        }

        return $quote;
    }

    /**
     * @param callable(QuoteService, int, int, int): array<string, string> $action
     */
    private function mutate(int $quoteId, callable $action, string $back, string $success): void
    {
        try {
            $errors = $action(new QuoteService(), $quoteId, (int) ($_POST['version_number'] ?? 0), (int) auth_user()['id']);
        } catch (QuoteConflictException $e) {
            $this->conflict($quoteId, $e);

            return;
        }
        $this->finish($quoteId, $errors, $success, $back);
    }

    /**
     * @param array<string, string>|list<string> $errors
     */
    private function finish(int $quoteId, array $errors, string $success, ?string $back = null): void
    {
        if ($errors !== []) {
            flash('error', (string) reset($errors));
        } else {
            flash('success', $success);
        }
        redirect($back ?? ('/quotes/' . $quoteId . '/edit'));
    }

    /**
     * @param array{errors: list<string>, id: int|null} $result
     */
    private function afterLine(int $quoteId, array $result, string $success): void
    {
        if ($this->wantsJson()) {
            $fresh = (new QuoteRepository())->find($quoteId);
            json_response([
                'ok' => $result['errors'] === [],
                'errors' => $result['errors'],
                'version_number' => (int) ($fresh['version_number'] ?? 0),
            ], $result['errors'] === [] ? 200 : 422);
        }
        $this->finish($quoteId, $result['errors'], $success);
    }

    private function conflict(int $quoteId, QuoteConflictException $e): void
    {
        if ($this->wantsJson()) {
            json_response(['ok' => false, 'conflict' => $e->getMessage()], 409);
        }
        flash('warning', $e->getMessage());
        redirect('/quotes/' . $quoteId);
    }

    private function wantsJson(): bool
    {
        return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch')
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }
}
