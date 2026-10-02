<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\ArtworkProofingRepository;
use App\Repositories\ProductionControlRepository;

/**
 * Signage artwork, proofs, and production files stay separate.
 * Approval names one revision and one proof. A later revision does not inherit it.
 */
final class ArtworkProofingService
{
    /** @var list<string> */
    public const FILE_CATEGORIES = [
        'ARTWORK_SOURCE', 'CUSTOMER_SOURCE', 'PROOF', 'PRINT_FILE', 'CUT_FILE', 'CNC_FILE', 'ROUTER_FILE',
        'LASER_FILE', 'INSTALLATION_DRAWING', 'TECHNICAL_DRAWING', 'MOCKUP', 'REFERENCE', 'BRAND_ASSET',
        'FONT_REFERENCE', 'OTHER',
    ];

    /** @var list<string> */
    public const REASONS = [
        'INITIAL', 'CUSTOMER_CHANGE', 'INTERNAL_CORRECTION', 'SITE_MEASUREMENT',
        'PRODUCTION_CORRECTION', 'COMMERCIAL_VARIATION', 'REORDER_UPDATE', 'OTHER',
    ];

    /** @var list<string> */
    public const CHANGE_CLASSES = ['CUSTOMER_VISIBLE', 'PRODUCTION_ONLY', 'ADMINISTRATIVE'];

    /** @var list<string> */
    public const QUEUE = [
        'DRAFT', 'IN_DESIGN', 'INTERNAL_REVIEW', 'CUSTOMER_REVIEW', 'CHANGES_REQUESTED',
        'APPROVED', 'PRODUCTION_PREP', 'APPROVED_FOR_PRODUCTION',
    ];

    /** @var list<string> */
    public const CHECKLIST = [
        'DIMENSIONS', 'SPELLING', 'CONTACT_DETAILS', 'LOGO', 'COLOUR', 'BLEED', 'RESOLUTION', 'CUT_PATH', 'SPECIFICATION',
    ];

    /** @var list<string> */
    public const PREFLIGHT = [
        'DIMENSIONS', 'BLEED', 'RESOLUTION', 'FONTS', 'IMAGES', 'COLOUR_MODE', 'CUT_CONTOUR', 'WHITE_INK', 'NOTES',
    ];

    /** @var list<string> */
    private const LOCKED = ['SENT', 'APPROVED', 'SUPERSEDED'];

    /** @var list<string> */
    private const BLOCKED_EXT = ['php', 'phtml', 'phar', 'exe', 'sh', 'js', 'html', 'htm', 'svg'];

    public function __construct(
        private readonly ArtworkProofingRepository $repo = new ArtworkProofingRepository(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly AuditService $audit = new AuditService(),
        private readonly NotificationService $notifications = new NotificationService()
    ) {
    }

    public function effectiveDpi(int $pixels, string $millimetres): string
    {
        if ($pixels < 1 || !Decimal::isNumeric($millimetres) || Decimal::cmp($millimetres, '0') <= 0) {
            return '0.0';
        }

        return Decimal::div(Decimal::mul((string) $pixels, '25.4', 4), $millimetres, 1);
    }

    /**
     * @return array{colour_mode: string, verified: bool, message: string, width_px: int|null, height_px: int|null, page_count: int|null, dpi: string|null}
     */
    public function inspect(string $filename, string $bytes, ?string $widthMm, ?string $heightMm): array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $base = [
            'colour_mode' => 'UNKNOWN',
            'verified' => false,
            'message' => 'Not automatically verified.',
            'width_px' => null,
            'height_px' => null,
            'page_count' => null,
            'dpi' => null,
        ];
        if (in_array($ext, ['cdr', 'ai', 'eps', 'psd'], true) || $bytes === '') {
            return $base;
        }
        if ($ext === 'pdf' || str_starts_with($bytes, '%PDF')) {
            $base['page_count'] = $this->pdfPageCount($bytes);
            $base['message'] = $base['page_count'] === null
                ? 'Not automatically verified.'
                : 'Page count read from the PDF. Colour mode was not inspected.';

            return $base;
        }
        if (!function_exists('getimagesizefromstring')) {
            return $base;
        }
        $info = @getimagesizefromstring($bytes);
        if (!is_array($info)) {
            return $base;
        }
        $base['width_px'] = (int) $info[0];
        $base['height_px'] = (int) $info[1];
        $channels = (int) ($info['channels'] ?? 0);
        if ($channels === 4) {
            $base['colour_mode'] = 'CMYK';
        } elseif ($channels === 1) {
            $base['colour_mode'] = 'GRAYSCALE';
        } elseif ($channels === 3) {
            $base['colour_mode'] = 'RGB';
        }
        $base['verified'] = $base['colour_mode'] !== 'UNKNOWN';
        $base['message'] = $base['verified'] ? 'Raster size read from the file.' : 'Not automatically verified.';
        if ($widthMm !== null && $widthMm !== '' && $base['width_px'] > 0) {
            $base['dpi'] = $this->effectiveDpi($base['width_px'], $widthMm);
            $limit = $this->dpiLimit($widthMm, $heightMm);
            if (Decimal::cmp($base['dpi'], $limit) < 0) {
                $base['message'] = 'Effective DPI ' . $base['dpi'] . ' is under the ' . $limit . ' threshold for this size.';
            }
        }

        return $base;
    }

    public function rejectUpload(string $name, string $bytes): ?string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, self::BLOCKED_EXT, true)) {
            return 'That file type is not accepted.';
        }
        if ($ext === 'pdf' && !str_starts_with($bytes, '%PDF')) {
            return 'That PDF was rejected. The file is not a PDF.';
        }
        $max = (int) SettingsService::get('artwork_upload_max_mb', '32');
        if (strlen($bytes) > $max * 1024 * 1024) {
            return 'That file is larger than the ' . $max . ' MB limit.';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null, number: string|null}
     */
    public function create(int $jobId, array $input, int $userId): array
    {
        if (!can('artwork.create') && !can('artwork.upload')) {
            return ['errors' => ['_form' => 'You cannot create artwork.'], 'id' => null, 'number' => null];
        }
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return ['errors' => ['title' => 'Name the artwork.'], 'id' => null, 'number' => null];
        }
        $id = 0;
        $number = '';
        Database::transaction(function () use ($jobId, $input, $userId, $title, &$id, &$number): void {
            $number = $this->numbers->artwork();
            $id = $this->repo->insertArtwork([
                'artwork_number' => $number,
                'job_id' => $jobId,
                'job_item_id' => $this->nullableInt($input['job_item_id'] ?? null),
                'project_id' => $this->nullableInt($input['project_id'] ?? null),
                'site_id' => $this->nullableInt($input['site_id'] ?? null),
                'quote_id' => $this->nullableInt($input['quote_id'] ?? null),
                'catalogue_item_id' => $this->nullableInt($input['catalogue_item_id'] ?? null),
                'asset_id' => $this->nullableInt($input['asset_id'] ?? null),
                'customer_order_id' => $this->nullableInt($input['customer_order_id'] ?? null),
                'master_artwork_id' => $this->nullableInt($input['master_artwork_id'] ?? null),
                'title' => $title,
                'original_filename' => 'pending',
                'stored_filename' => 'pending',
                'mime_type' => 'application/octet-stream',
                'file_size' => 0,
                'status' => 'DRAFT',
                'priority' => $this->priority((string) ($input['priority'] ?? 'NORMAL')),
                'designer_id' => $this->nullableInt($input['designer_id'] ?? null),
                'reviewer_id' => $this->nullableInt($input['reviewer_id'] ?? null),
                'design_due' => $this->nullableDate($input['design_due'] ?? null),
                'customer_due' => $this->nullableDate($input['customer_due'] ?? null),
                'production_required_date' => $this->nullableDate($input['production_required_date'] ?? null),
                'finished_width_mm' => $this->nullableDecimal($input['finished_width_mm'] ?? null),
                'finished_height_mm' => $this->nullableDecimal($input['finished_height_mm'] ?? null),
                'scale_label' => $this->scale((string) ($input['scale_label'] ?? '')),
                'reuse_class' => $this->oneOf((string) ($input['reuse_class'] ?? 'ONE_TIME'), ['REUSABLE', 'ONE_TIME', 'SITE_SPECIFIC', 'VEHICLE_SPECIFIC', 'EXPIRED'], 'ONE_TIME'),
                'library_category' => $this->libraryCategory((string) ($input['library_category'] ?? '')),
                'vehicle_template_id' => $this->nullableInt($input['vehicle_template_id'] ?? null),
                'sign_kind' => $this->nullableText($input['sign_kind'] ?? null),
                'production_file_required' => !empty($input['production_file_required']) ? 1 : 0,
                'colour_ack_required' => !empty($input['colour_ack_required']) ? 1 : 0,
                'variables_json' => $this->variables($input['variables'] ?? null),
                'uploaded_by' => $userId,
            ]);
            $revisionId = $this->repo->insertRevision([
                'artwork_id' => $id,
                'revision_number' => 1,
                'revision_label' => 'R1',
                'source_revision_id' => null,
                'reason_code' => 'INITIAL',
                'change_class' => 'CUSTOMER_VISIBLE',
                'change_summary' => trim((string) ($input['change_summary'] ?? 'Initial artwork')),
                'internal_notes' => null,
                'customer_notes' => null,
                'status' => 'DRAFT',
                'created_by' => $userId,
            ]);
            $this->repo->pointCurrent($id, $revisionId, 1, 'DRAFT', 0);
            foreach (self::CHECKLIST as $code) {
                $this->repo->checklistSeed($revisionId, $code);
            }
            $this->audit->record('job_artwork', $id, 'ARTWORK_CREATED', null, ['number' => $number], $userId);
            BusinessEventDispatcher::emit('ARTWORK_CREATED', 'ARTWORK', $id, $userId, ['number' => $number]);
        });

        return ['errors' => [], 'id' => $id, 'number' => $number];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null, label: string|null, customer_reapproval: bool}
     */
    public function revise(int $artworkId, array $input, int $userId): array
    {
        if (!can('artwork.revise') && !can('artwork.upload')) {
            return ['errors' => ['_form' => 'You cannot revise artwork.'], 'id' => null, 'label' => null, 'customer_reapproval' => false];
        }
        $summary = trim((string) ($input['change_summary'] ?? ''));
        if ($summary === '') {
            return ['errors' => ['change_summary' => 'Record what changed.'], 'id' => null, 'label' => null, 'customer_reapproval' => false];
        }
        $class = $this->oneOf((string) ($input['change_class'] ?? 'CUSTOMER_VISIBLE'), self::CHANGE_CLASSES, 'CUSTOMER_VISIBLE');
        $reason = $this->oneOf((string) ($input['reason_code'] ?? 'CUSTOMER_CHANGE'), self::REASONS, 'OTHER');
        $reapproval = $this->customerReapprovalRequired($class);
        $revisionId = 0;
        $label = '';
        $error = null;
        Database::transaction(function () use ($artworkId, $input, $userId, $summary, $class, $reason, $reapproval, &$revisionId, &$label, &$error): void {
            $art = $this->repo->lockArtwork($artworkId);
            if ($art === null || $art['artwork_number'] === null) {
                $error = 'That artwork was not found.';

                return;
            }
            $number = $this->repo->nextRevisionNumber($artworkId);
            $label = 'R' . $number;
            $source = $this->nullableInt($input['source_revision_id'] ?? $art['current_revision_id']);
            $revisionId = $this->repo->insertRevision([
                'artwork_id' => $artworkId,
                'revision_number' => $number,
                'revision_label' => $label,
                'source_revision_id' => $source,
                'reason_code' => $reason,
                'change_class' => $class,
                'change_summary' => $summary,
                'internal_notes' => $this->nullableText($input['internal_notes'] ?? null),
                'customer_notes' => $this->nullableText($input['customer_notes'] ?? null),
                'status' => 'DRAFT',
                'created_by' => $userId,
            ]);
            $approved = $reapproval ? 0 : (int) $art['customer_approved'];
            $status = $reapproval ? 'IN_DESIGN' : 'PRODUCTION_PREP';
            $this->repo->pointCurrent($artworkId, $revisionId, $number, $status, $approved);
            foreach (self::CHECKLIST as $code) {
                $this->repo->checklistSeed($revisionId, $code);
            }
            $this->audit->record('artwork_revision', $revisionId, 'ARTWORK_REVISION_CREATED', null, ['label' => $label, 'class' => $class], $userId);
            BusinessEventDispatcher::emit('ARTWORK_REVISION_CREATED', 'ARTWORK_REVISION', $revisionId, $userId, ['label' => $label]);
        });
        if ($error !== null) {
            return ['errors' => ['_form' => $error], 'id' => null, 'label' => null, 'customer_reapproval' => false];
        }

        return ['errors' => [], 'id' => $revisionId, 'label' => $label, 'customer_reapproval' => $reapproval];
    }

    public function customerReapprovalRequired(string $changeClass): bool
    {
        if ($changeClass === 'PRODUCTION_ONLY') {
            return SettingsService::get('artwork_production_only_reapproval', 'NO') === 'YES';
        }
        if ($changeClass === 'ADMINISTRATIVE') {
            return false;
        }

        return true;
    }

    public function changeImpact(string $changeClass): string
    {
        if ($changeClass === 'CUSTOMER_VISIBLE') {
            return 'CUSTOMER_REAPPROVAL_REQUIRED';
        }
        if ($changeClass === 'PRODUCTION_ONLY') {
            return 'RE_RELEASE_REQUIRED';
        }

        return 'NO_CUSTOMER_IMPACT';
    }

    public function locked(string $status): bool
    {
        return in_array($status, self::LOCKED, true);
    }

    /**
     * @param array{name: string, bytes: string} $file
     * @return array{errors: array<string, string>, id: int|null, warning: string|null}
     */
    public function storeFile(int $artworkId, ?int $revisionId, string $category, string $visibility, array $file, int $userId): array
    {
        if (!in_array($category, self::FILE_CATEGORIES, true)) {
            return ['errors' => ['category' => 'Choose a file category.'], 'id' => null, 'warning' => null];
        }
        if ($category === 'FONT_REFERENCE') {
            $visibility = 'INTERNAL';
        }
        $rejected = $this->rejectUpload($file['name'], $file['bytes']);
        if ($rejected !== null) {
            return ['errors' => ['_form' => $rejected], 'id' => null, 'warning' => null];
        }
        if ($revisionId !== null) {
            $revision = $this->repo->revision($revisionId);
            if ($revision === null || (int) $revision['artwork_id'] !== $artworkId) {
                return ['errors' => ['_form' => 'That revision is not on this artwork.'], 'id' => null, 'warning' => null];
            }
            if ($this->locked((string) $revision['status']) && $category !== 'REFERENCE') {
                return ['errors' => ['_form' => 'That revision was sent. Upload the file on a new revision.'], 'id' => null, 'warning' => null];
            }
        }
        $hash = hash('sha256', $file['bytes']);
        $existing = $this->repo->duplicateHash($hash);
        $art = $this->repo->artwork($artworkId);
        if ($art === null) {
            return ['errors' => ['_form' => 'That artwork was not found.'], 'id' => null, 'warning' => null];
        }
        $stored = $this->writeBytes($file['name'], $file['bytes']);
        $id = $this->repo->insertFile([
            'artwork_id' => $artworkId,
            'revision_id' => $revisionId,
            'customer_id' => (int) $art['customer_id'],
            'job_id' => (int) $art['job_id'],
            'project_id' => $art['project_id'],
            'site_id' => $art['site_id'],
            'asset_id' => $art['asset_id'],
            'category' => $category,
            'visibility' => $this->oneOf($visibility, ['INTERNAL', 'CUSTOMER', 'SUPPLIER', 'CONTRACTOR', 'PRODUCTION'], 'INTERNAL'),
            'original_filename' => $file['name'],
            'stored_filename' => $stored,
            'mime_type' => $this->mime($file['name']),
            'extension' => strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)),
            'file_size' => strlen($file['bytes']),
            'sha256' => $hash,
            'uploaded_by' => $userId,
        ]);
        $this->audit->record('artwork_file', $id, 'ARTWORK_FILE_UPLOADED', null, ['category' => $category, 'hash' => $hash], $userId);
        $warning = $existing === null ? null : 'This exact file already exists.';

        return ['errors' => [], 'id' => $id, 'warning' => $warning];
    }

    /**
     * @param array{name: string, bytes: string} $file
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function sendProof(int $revisionId, array $file, int $userId): array
    {
        if (!can('artwork.send_proof') && !can('artwork.upload')) {
            return ['errors' => ['_form' => 'You cannot send a proof.'], 'id' => null];
        }
        $revision = $this->repo->revision($revisionId);
        if ($revision === null) {
            return ['errors' => ['_form' => 'That revision was not found.'], 'id' => null];
        }
        if ($this->locked((string) $revision['status'])) {
            return ['errors' => ['_form' => 'That revision was already sent. Create a new revision.'], 'id' => null];
        }
        $rejected = $this->rejectUpload($file['name'], $file['bytes']);
        if ($rejected !== null) {
            return ['errors' => ['_form' => $rejected], 'id' => null];
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            return ['errors' => ['_form' => 'A customer proof is a PDF, JPG, or PNG.'], 'id' => null];
        }
        $art = $this->repo->artwork((int) $revision['artwork_id']);
        $inspect = $this->inspect($file['name'], $file['bytes'], $art['finished_width_mm'] ?? null, $art['finished_height_mm'] ?? null);
        $stored = $this->writeBytes($file['name'], $file['bytes']);
        $proofId = 0;
        Database::transaction(function () use ($revision, $file, $userId, $inspect, $stored, &$proofId): void {
            $proofId = $this->repo->insertProof([
                'artwork_id' => (int) $revision['artwork_id'],
                'revision_id' => (int) $revision['id'],
                'original_filename' => $file['name'],
                'stored_filename' => $stored,
                'mime_type' => $this->mime($file['name']),
                'extension' => strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)),
                'file_size' => strlen($file['bytes']),
                'sha256' => hash('sha256', $file['bytes']),
                'page_count' => $inspect['page_count'],
                'width_px' => $inspect['width_px'],
                'height_px' => $inspect['height_px'],
                'colour_mode' => $inspect['colour_mode'],
                'inspection_note' => $inspect['message'],
                'visibility' => 'CUSTOMER',
                'created_by' => $userId,
            ]);
            $this->repo->setRevisionStatus((int) $revision['id'], 'SENT');
            $this->repo->stampFirstProof((int) $revision['artwork_id']);
            $this->repo->pointCurrent(
                (int) $revision['artwork_id'],
                (int) $revision['id'],
                (int) $revision['revision_number'],
                'CUSTOMER_REVIEW',
                0
            );
            $this->audit->record('artwork_proof', $proofId, 'ARTWORK_PROOF_SENT', null, ['revision' => $revision['revision_label']], $userId);
            BusinessEventDispatcher::emit('ARTWORK_PROOF_SENT', 'ARTWORK_PROOF', $proofId, $userId, ['revision' => (string) $revision['revision_label']]);
        });

        return ['errors' => [], 'id' => $proofId];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function annotate(int $proofId, array $input, string $authorKind, ?int $userId, ?int $portalUserId, ?string $name): array
    {
        $proof = $this->repo->proof($proofId);
        if ($proof === null) {
            return ['errors' => ['_form' => 'That proof was not found.'], 'id' => null];
        }
        $kind = strtoupper((string) ($input['kind'] ?? 'PIN'));
        if (!in_array($kind, ['PIN', 'AREA'], true)) {
            return ['errors' => ['kind' => 'Choose a pin or an area.'], 'id' => null];
        }
        $x = (string) ($input['x'] ?? '');
        $y = (string) ($input['y'] ?? '');
        if (!$this->unit($x) || !$this->unit($y)) {
            return ['errors' => ['x' => 'Place the note on the proof.'], 'id' => null];
        }
        $width = null;
        $height = null;
        if ($kind === 'AREA') {
            $width = (string) ($input['width'] ?? '');
            $height = (string) ($input['height'] ?? '');
            if (!$this->unit($width) || !$this->unit($height)) {
                return ['errors' => ['width' => 'Draw the area on the proof.'], 'id' => null];
            }
        }
        $visibility = strtoupper((string) ($input['visibility'] ?? 'CUSTOMER_SHARED'));
        if ($authorKind !== 'STAFF') {
            $visibility = 'CUSTOMER_SHARED';
        }
        if (!in_array($visibility, ['CUSTOMER_SHARED', 'INTERNAL_ONLY'], true)) {
            $visibility = 'CUSTOMER_SHARED';
        }
        $body = trim((string) ($input['body'] ?? ''));
        if ($body === '') {
            return ['errors' => ['body' => 'Write the comment.'], 'id' => null];
        }
        $type = $this->oneOf((string) ($input['annotation_type'] ?? 'CHANGE_REQUEST'), [
            'CHANGE_REQUEST', 'QUESTION', 'CORRECTION', 'INTERNAL_NOTE', 'APPROVAL_NOTE', 'PRODUCTION_NOTE',
        ], 'CHANGE_REQUEST');
        if ($visibility === 'INTERNAL_ONLY' && !can('artwork.comment_internal') && $authorKind === 'STAFF') {
            return ['errors' => ['_form' => 'You cannot add an internal note.'], 'id' => null];
        }
        $id = $this->repo->insertAnnotation([
            'proof_id' => $proofId,
            'artwork_id' => (int) $proof['artwork_id'],
            'page_number' => max(1, (int) ($input['page_number'] ?? 1)),
            'kind' => $kind,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'annotation_type' => $type,
            'visibility' => $visibility,
            'body' => $body,
            'author_kind' => $authorKind,
            'author_user_id' => $userId,
            'author_portal_user_id' => $portalUserId,
            'author_name' => $name,
        ]);
        BusinessEventDispatcher::emit('ARTWORK_COMMENT_ADDED', 'ARTWORK_ANNOTATION', $id, $userId, ['visibility' => strtolower($visibility)]);
        $this->audit->record('artwork_annotation', $id, 'ARTWORK_COMMENT_ADDED', null, ['page' => (int) ($input['page_number'] ?? 1)], $userId);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function annotationsFor(int $proofId, bool $customer, ?int $page): array
    {
        return $this->repo->annotations($proofId, $customer ? 'CUSTOMER_SHARED' : null, $page);
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function comment(int $annotationId, string $body, string $visibility, string $authorKind, ?int $userId, ?int $portalUserId, ?string $name, ?int $mentionUserId): array
    {
        $note = $this->repo->annotation($annotationId);
        if ($note === null) {
            return ['errors' => ['_form' => 'That comment was not found.'], 'id' => null];
        }
        $body = trim($body);
        if ($body === '') {
            return ['errors' => ['body' => 'Write a reply.'], 'id' => null];
        }
        if ($authorKind !== 'STAFF') {
            $visibility = 'CUSTOMER_SHARED';
        }
        if ((string) $note['visibility'] === 'INTERNAL_ONLY') {
            $visibility = 'INTERNAL_ONLY';
        }
        $id = $this->repo->insertComment([
            'annotation_id' => $annotationId,
            'visibility' => $visibility === 'INTERNAL_ONLY' ? 'INTERNAL_ONLY' : 'CUSTOMER_SHARED',
            'body' => $body,
            'author_kind' => $authorKind,
            'author_user_id' => $userId,
            'author_portal_user_id' => $portalUserId,
            'author_name' => $name,
            'mentioned_user_id' => $visibility === 'INTERNAL_ONLY' ? $mentionUserId : null,
        ]);
        if ($mentionUserId !== null && $visibility === 'INTERNAL_ONLY') {
            $this->notifications->send($mentionUserId, null, 'SYSTEM', 'Artwork mention', mb_substr($body, 0, 180), 'artwork', (int) $note['artwork_id'], 'NORMAL', 'art-mention-' . $id);
        }

        return ['errors' => [], 'id' => $id];
    }

    public function resolve(int $annotationId, int $revisionId, int $userId): array
    {
        $note = $this->repo->annotation($annotationId);
        $revision = $this->repo->revision($revisionId);
        if ($note === null || $revision === null || (int) $note['artwork_id'] !== (int) $revision['artwork_id']) {
            return ['errors' => ['_form' => 'That resolution does not match the artwork.']];
        }
        $this->repo->resolveAnnotation($annotationId, $revisionId, 'RESOLVED');
        $this->audit->record('artwork_annotation', $annotationId, 'ARTWORK_ANNOTATION_RESOLVED', null, ['revision' => $revision['revision_label']], $userId);

        return ['errors' => []];
    }

    public function taskFromAnnotation(int $annotationId, int $userId): array
    {
        $note = $this->repo->annotation($annotationId);
        if ($note === null) {
            return ['errors' => ['_form' => 'That comment was not found.'], 'task_id' => null];
        }
        if ($note['task_id'] !== null) {
            return ['errors' => [], 'task_id' => (int) $note['task_id']];
        }
        $art = $this->repo->artwork((int) $note['artwork_id']);
        $taskId = $this->repo->insertTask((int) $art['job_id'], mb_substr((string) $note['body'], 0, 180), $userId);
        $this->repo->linkTask($annotationId, $taskId);

        return ['errors' => [], 'task_id' => $taskId];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function approve(int $proofId, int $revisionId, string $name, string $statement, string $version, ?int $portalUserId, ?int $shareId, string $ip, string $agent): array
    {
        $proof = $this->repo->proof($proofId);
        $revision = $this->repo->revision($revisionId);
        if ($proof === null || $revision === null || (int) $proof['revision_id'] !== $revisionId) {
            return ['errors' => ['_form' => 'That proof is not this revision.'], 'id' => null];
        }
        if ($shareId !== null) {
            $share = $this->repo->share($shareId);
            if ($share === null || (int) $share['revision_id'] !== $revisionId || (int) $share['proof_id'] !== $proofId || (string) $share['permission'] !== 'APPROVE') {
                return ['errors' => ['_form' => 'This link cannot approve that revision.'], 'id' => null];
            }
        }
        if ((string) $revision['status'] === 'SUPERSEDED') {
            return ['errors' => ['_form' => 'This link cannot approve a later revision.'], 'id' => null];
        }
        $art = $this->repo->artwork((int) $proof['artwork_id']);
        if ($art !== null && (int) $art['current_revision_id'] !== $revisionId && (string) $revision['change_class'] === 'CUSTOMER_VISIBLE') {
            return ['errors' => ['_form' => 'Approve the current revision. This approval does not move forward.'], 'id' => null];
        }
        if ($this->repo->approvalForRevision($revisionId) !== null) {
            return ['errors' => ['_form' => 'This revision is already approved.'], 'id' => null];
        }
        $id = 0;
        Database::transaction(function () use ($proof, $revision, $name, $statement, $version, $portalUserId, $shareId, $ip, $agent, &$id): void {
            $id = $this->repo->insertApproval([
                'job_artwork_id' => (int) $proof['artwork_id'],
                'revision_id' => (int) $revision['id'],
                'proof_id' => (int) $proof['id'],
                'approval_status' => 'APPROVED',
                'customer_name' => $name,
                'approval_method' => $shareId !== null ? 'SHARE_LINK' : 'PORTAL',
                'reference' => (string) $revision['revision_label'],
                'notes' => $statement,
                'statement_version' => $version,
                'ip_address' => substr($ip, 0, 64),
                'user_agent' => substr($agent, 0, 255),
                'portal_user_id' => $portalUserId,
                'share_token_id' => $shareId,
                'approved_at' => date('Y-m-d H:i:s'),
                'recorded_by' => null,
            ]);
            $this->repo->setRevisionStatus((int) $revision['id'], 'APPROVED');
            $this->repo->markApproved((int) $proof['artwork_id'], $name);
            $this->repo->pointCurrent((int) $proof['artwork_id'], (int) $revision['id'], (int) $revision['revision_number'], 'APPROVED', 1);
            $this->audit->record('artwork_approval', $id, 'ARTWORK_APPROVED', null, ['revision' => $revision['revision_label']], null);
            BusinessEventDispatcher::emit('ARTWORK_APPROVED', 'ARTWORK_APPROVAL', $id, null, ['revision' => (string) $revision['revision_label']]);
        });

        return ['errors' => [], 'id' => $id];
    }

    public function requestChanges(int $artworkId, int $revisionId, string $message, int $userId): array
    {
        $revision = $this->repo->revision($revisionId);
        if ($revision === null || (int) $revision['artwork_id'] !== $artworkId) {
            return ['errors' => ['_form' => 'That revision was not found.']];
        }
        $this->repo->setRevisionStatus($revisionId, 'CHANGES_REQUESTED');
        $art = $this->repo->artwork($artworkId);
        $this->repo->pointCurrent($artworkId, $revisionId, (int) $revision['revision_number'], 'CHANGES_REQUESTED', 0);
        $this->repo->insertRevisionComment([
            'revision_id' => $revisionId,
            'visibility' => 'CUSTOMER_SHARED',
            'body' => $message,
            'author_kind' => 'PORTAL',
            'author_user_id' => null,
            'author_portal_user_id' => $userId,
            'author_name' => null,
        ]);
        $designer = (int) ($art['designer_id'] ?? $art['uploaded_by'] ?? 0);
        if ($designer > 0) {
            $this->notifications->send($designer, null, 'ARTWORK_APPROVAL_PENDING', 'Artwork changes requested', mb_substr($message, 0, 180), 'artwork', $artworkId, 'NORMAL', 'art-change-' . $revisionId);
        }
        BusinessEventDispatcher::emit('ARTWORK_CHANGES_REQUESTED', 'ARTWORK', $artworkId, null, ['revision' => (string) $revision['revision_label']]);

        return ['errors' => []];
    }

    /**
     * @param array{name: string, bytes: string} $file
     * @return array{errors: array<string, string>, id: int|null, label: string|null}
     */
    public function productionFile(int $artworkId, int $revisionId, string $category, array $file, int $userId): array
    {
        if (!can('artwork.production_file.create')) {
            return ['errors' => ['_form' => 'You cannot prepare a production file.'], 'id' => null, 'label' => null];
        }
        $revision = $this->repo->revision($revisionId);
        if ($revision === null || (int) $revision['artwork_id'] !== $artworkId) {
            return ['errors' => ['_form' => 'That revision was not found.'], 'id' => null, 'label' => null];
        }
        $rejected = $this->rejectUpload($file['name'], $file['bytes']);
        if ($rejected !== null) {
            return ['errors' => ['_form' => $rejected], 'id' => null, 'label' => null];
        }
        $art = $this->repo->artwork($artworkId);
        $id = 0;
        $label = '';
        Database::transaction(function () use ($art, $revision, $category, $file, $userId, &$id, &$label): void {
            $this->repo->lockArtwork((int) $art['id']);
            $n = $this->repo->nextPf((int) $art['id']);
            $label = 'PF' . $n;
            $proof = $this->repo->latestProof((int) $revision['id']);
            $stored = $this->writeBytes($file['name'], $file['bytes']);
            $id = $this->repo->insertProductionFile([
                'job_id' => (int) $art['job_id'],
                'job_item_id' => $art['job_item_id'],
                'artwork_id' => (int) $art['id'],
                'artwork_revision_id' => (int) $revision['id'],
                'proof_id' => $proof['id'] ?? null,
                'category' => $category,
                'status' => 'DRAFT',
                'version_label' => $label,
                'pf_number' => $n,
                'artwork_revision' => (int) $revision['revision_number'],
                'original_name' => $file['name'],
                'sha256' => hash('sha256', $file['bytes']),
                'stored_filename' => $stored,
                'mime_type' => $this->mime($file['name']),
                'file_size' => strlen($file['bytes']),
                'visibility' => 'PRODUCTION',
                'change_class' => (string) $revision['change_class'],
                'scale_label' => $art['scale_label'],
                'created_by' => $userId,
            ]);
            foreach (self::PREFLIGHT as $code) {
                $this->repo->insertPreflight([
                    'production_file_id' => $id,
                    'check_code' => $code,
                    'result' => 'NOT_AUTOMATICALLY_VERIFIED',
                    'source' => 'AUTOMATIC',
                    'detail' => 'Not automatically verified.',
                    'checked_by' => null,
                ]);
            }
            BusinessEventDispatcher::emit('PRODUCTION_FILE_CREATED', 'PRODUCTION_FILE', $id, $userId, ['label' => $label]);
        });

        return ['errors' => [], 'id' => $id, 'label' => $label];
    }

    public function confirmPreflight(int $fileId, string $code, string $result, int $userId): void
    {
        $allowed = ['HUMAN_CONFIRMED', 'FAIL', 'NOT_AUTOMATICALLY_VERIFIED'];
        if (!in_array($result, $allowed, true) || !in_array($code, self::PREFLIGHT, true)) {
            return;
        }
        $this->repo->insertPreflight([
            'production_file_id' => $fileId,
            'check_code' => $code,
            'result' => $result,
            'source' => 'HUMAN',
            'detail' => $result === 'FAIL' ? 'Failed by a person.' : 'Confirmed by a person.',
            'checked_by' => $userId,
        ]);
        if ($result === 'FAIL') {
            $this->repo->setProductionStatus($fileId, 'REVIEW_REQUIRED');
            BusinessEventDispatcher::emit('PRODUCTION_FILE_PRE_FLIGHT_FAILED', 'PRODUCTION_FILE', $fileId, $userId, ['check' => strtolower($code)]);
        } else {
            $this->repo->setProductionStatus($fileId, 'PRE_FLIGHT');
        }
    }

    public function approveProductionFile(int $fileId, int $userId): array
    {
        if (!can('artwork.production_file.approve') && !can('production.release.approve')) {
            return ['errors' => ['_form' => 'You cannot approve a production file.']];
        }
        $file = $this->repo->productionFile($fileId);
        if ($file === null) {
            return ['errors' => ['_form' => 'That production file was not found.']];
        }
        $this->repo->approveProductionFile($fileId, $userId);
        if ($file['artwork_id'] !== null) {
            $art = $this->repo->artwork((int) $file['artwork_id']);
            if ($art !== null && (int) $art['customer_approved'] === 1) {
                $this->repo->pointCurrent((int) $art['id'], (int) $art['current_revision_id'], (int) $art['revision_number'], 'APPROVED_FOR_PRODUCTION', 1);
            }
        }
        $this->audit->record('production_file', $fileId, 'PRODUCTION_FILE_APPROVED', null, ['label' => $file['version_label']], $userId);
        BusinessEventDispatcher::emit('PRODUCTION_FILE_APPROVED', 'PRODUCTION_FILE', $fileId, $userId, ['label' => (string) $file['version_label']]);

        return ['errors' => []];
    }

    public function supersedeProductionFile(int $oldId, int $newId, int $userId): array
    {
        if (!can('artwork.production_file.supersede') && !can('artwork.production_file.approve')) {
            return ['errors' => ['_form' => 'You cannot supersede a production file.']];
        }
        $old = $this->repo->productionFile($oldId);
        $new = $this->repo->productionFile($newId);
        if ($old === null || $new === null || (int) $old['job_id'] !== (int) $new['job_id']) {
            return ['errors' => ['_form' => 'Those production files are not on the same job.']];
        }
        $this->repo->supersedeProductionFile($oldId, $newId);
        $current = (new ProductionControlRepository())->currentRelease((int) $old['job_id']);
        if ($current !== null && (string) $current['status'] === 'RELEASED') {
            (new ProductionControlRepository())->setReleaseStatus((int) $current['id'], 'REVIEW_REQUIRED');
        }
        $this->audit->record('production_file', $oldId, 'PRODUCTION_FILE_SUPERSEDED', null, ['replaced_by' => $new['version_label']], $userId);
        BusinessEventDispatcher::emit('PRODUCTION_FILE_SUPERSEDED', 'PRODUCTION_FILE', $oldId, $userId, ['label' => (string) $old['version_label']]);

        return ['errors' => []];
    }

    /**
     * @return array{current: array<string, mixed>|null, files: list<array<string, mixed>>}
     */
    public function workshop(int $jobId): array
    {
        $current = $this->repo->currentProductionFile($jobId);
        $files = [];
        if ($current !== null && $current['artwork_id'] !== null) {
            $files = $this->repo->productionFiles((int) $current['artwork_id']);
        }

        return ['current' => $current, 'files' => $files];
    }

    public function recordProductionDownload(int $fileId, int $userId): void
    {
        $file = $this->repo->productionFile($fileId);
        if ($file === null || (string) $file['status'] !== 'APPROVED_FOR_PRODUCTION') {
            return;
        }
        $this->repo->logDownload($fileId, $userId, 'R' . (int) $file['artwork_revision'], (string) $file['version_label']);
        $this->audit->record('production_file', $fileId, 'PRODUCTION_FILE_DOWNLOADED', null, ['label' => $file['version_label']], $userId);
    }

    /**
     * @return array{token: string, id: int}|array{errors: array<string, string>}
     */
    public function share(int $proofId, string $permission, int $hours, int $userId): array
    {
        if (!can('artwork.send_proof')) {
            return ['errors' => ['_form' => 'You cannot share a proof.']];
        }
        $permission = $this->oneOf($permission, ['VIEW', 'COMMENT', 'APPROVE'], 'VIEW');
        $proof = $this->repo->proof($proofId);
        if ($proof === null) {
            return ['errors' => ['_form' => 'That proof was not found.']];
        }
        $raw = bin2hex(random_bytes(32));
        $id = $this->repo->insertShare([
            'token_hash' => hash('sha256', $raw),
            'artwork_id' => (int) $proof['artwork_id'],
            'revision_id' => (int) $proof['revision_id'],
            'proof_id' => $proofId,
            'permission' => $permission,
            'expires_at' => date('Y-m-d H:i:s', time() + max(1, $hours) * 3600),
            'created_by' => $userId,
        ]);

        return ['token' => $raw, 'id' => $id];
    }

    /**
     * @return array{errors: array<string, string>, token: array<string, mixed>|null}
     */
    public function openShare(string $token, ?int $artworkId = null): array
    {
        $row = $this->repo->shareByHash(hash('sha256', $token));
        if ($row === null || $row['revoked_at'] !== null) {
            return ['errors' => ['_form' => 'That proof link is not valid.'], 'token' => null];
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            return ['errors' => ['_form' => 'That proof link has expired.'], 'token' => null];
        }
        if ($artworkId !== null && $artworkId > 0 && (int) $row['artwork_id'] !== $artworkId) {
            return ['errors' => ['_form' => 'That proof link does not open this artwork.'], 'token' => null];
        }

        return ['errors' => [], 'token' => $row];
    }

    public function revokeShare(int $id): void
    {
        $this->repo->revokeShare($id);
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function requirePhysical(int $artworkId, string $reference, string $kind, int $userId): array
    {
        if (!can('artwork.physical_proof.manage')) {
            return ['errors' => ['_form' => 'You cannot manage a physical sample.'], 'id' => null];
        }
        $this->repo->setPhysicalStatus($artworkId, 'REQUIRED');
        $id = $this->repo->insertPhysical([
            'artwork_id' => $artworkId,
            'sample_reference' => $reference,
            'sample_kind' => $kind,
            'status' => 'REQUIRED',
            'notes' => null,
            'created_by' => $userId,
        ]);
        BusinessEventDispatcher::emit('PHYSICAL_PROOF_REQUIRED', 'ARTWORK', $artworkId, $userId, ['sample' => $reference]);

        return ['errors' => [], 'id' => $id];
    }

    public function approvePhysical(int $artworkId, string $name, int $userId): array
    {
        $row = $this->repo->openPhysical($artworkId);
        if ($row === null) {
            return ['errors' => ['_form' => 'No physical sample is waiting.']];
        }
        $this->repo->approvePhysical((int) $row['id'], $name);
        $this->repo->setPhysicalStatus($artworkId, 'APPROVED');
        $this->audit->record('artwork_physical_proof', (int) $row['id'], 'PHYSICAL_PROOF_APPROVED', null, ['name' => $name], $userId);
        BusinessEventDispatcher::emit('PHYSICAL_PROOF_APPROVED', 'ARTWORK', $artworkId, $userId, ['sample' => (string) $row['sample_reference']]);

        return ['errors' => []];
    }

    /**
     * @param array{name: string, bytes: string} $file
     * @return array{id: int, warning: string|null}
     */
    public function brandVersion(int $customerId, string $title, array $file, int $userId): array
    {
        $current = $this->repo->primaryBrand($customerId, 'LOGO');
        $version = $current === null ? 1 : ((int) $current['version_number'] + 1);
        if ($current !== null) {
            $this->repo->clearPrimary($customerId, 'LOGO');
        }
        $stored = $this->writeBytes($file['name'], $file['bytes']);
        $id = $this->repo->insertBrand([
            'customer_id' => $customerId,
            'category' => 'LOGO',
            'title' => $title,
            'version_number' => $version,
            'status' => 'CURRENT',
            'is_primary' => 1,
            'sha256' => hash('sha256', $file['bytes']),
            'original_filename' => $file['name'],
            'stored_filename' => $stored,
            'mime_type' => $this->mime($file['name']),
            'file_size' => strlen($file['bytes']),
            'supersedes_id' => $current['id'] ?? null,
            'created_by' => $userId,
        ]);

        return ['id' => $id, 'warning' => null];
    }

    public function brandWarning(int $artworkId): ?string
    {
        $row = $this->repo->brandChanged($artworkId);
        if ($row === null) {
            return null;
        }

        return 'Brand asset has changed since this artwork was approved.';
    }

    public function linkBrand(int $artworkId, int $assetId): void
    {
        $this->repo->linkBrand($artworkId, $assetId);
    }

    /**
     * @return array<string, int>
     */
    public function projectCounts(int $projectId): array
    {
        return $this->repo->projectCounts($projectId);
    }

    /**
     * @return array{errors: array<string, string>}
     */
    public function internalReview(int $revisionId, int $userId): array
    {
        if (!can('artwork.internal_review')) {
            return ['errors' => ['_form' => 'You cannot request internal review.']];
        }
        $revision = $this->repo->revision($revisionId);
        if ($revision === null) {
            return ['errors' => ['_form' => 'That revision was not found.']];
        }
        $this->repo->setInternalReview($revisionId, 'PENDING');
        $this->repo->pointCurrent((int) $revision['artwork_id'], $revisionId, (int) $revision['revision_number'], 'INTERNAL_REVIEW', 0);
        BusinessEventDispatcher::emit('ARTWORK_INTERNAL_REVIEW_REQUESTED', 'ARTWORK_REVISION', $revisionId, $userId, ['label' => (string) $revision['revision_label']]);

        return ['errors' => []];
    }

    public function internalApprove(int $revisionId, int $userId): array
    {
        if (!can('artwork.approve_internal')) {
            return ['errors' => ['_form' => 'You cannot complete internal review.']];
        }
        $this->repo->setInternalReview($revisionId, 'APPROVED');
        $this->audit->record('artwork_revision', $revisionId, 'ARTWORK_INTERNAL_APPROVED', null, null, $userId);

        return ['errors' => []];
    }

    public function confirmChecklist(int $revisionId, string $code, int $userId): void
    {
        if (in_array($code, self::CHECKLIST, true)) {
            $this->repo->confirmChecklist($revisionId, $code, $userId);
        }
    }

    public function checkout(int $artworkId, int $userId): void
    {
        $this->repo->checkout($artworkId, $userId);
    }

    public function clearCheckout(int $artworkId): void
    {
        $this->repo->clearCheckout($artworkId);
    }

    public function setPriority(int $artworkId, string $priority, int $userId): void
    {
        $priority = $this->priority($priority);
        $before = $this->repo->artwork($artworkId);
        $this->repo->setPriority($artworkId, $priority);
        $this->audit->record('job_artwork', $artworkId, 'ARTWORK_PRIORITY', ['priority' => $before['priority'] ?? null], ['priority' => $priority], $userId);
    }

    public function block(int $artworkId, string $reason, string $message, int $userId): void
    {
        $allowed = ['MISSING_LOGO', 'LOW_RESOLUTION', 'MISSING_INFORMATION', 'WAITING_CUSTOMER', 'WAITING_MEASUREMENTS', 'WAITING_BRAND_GUIDE', 'TECHNICAL_QUERY', 'OTHER'];
        $this->repo->setBlock($artworkId, $this->oneOf($reason, $allowed, 'OTHER'), $message, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(int $artworkId): array
    {
        $art = $this->repo->artwork($artworkId);
        if ($art === null) {
            return [];
        }
        $revision = $art['current_revision_id'] !== null ? $this->repo->revision((int) $art['current_revision_id']) : null;

        return [
            'customer' => (string) $art['company_name'],
            'job_number' => (string) $art['job_number'],
            'artwork_number' => (string) $art['artwork_number'],
            'revision' => $revision['revision_label'] ?? null,
            'width_mm' => $art['finished_width_mm'],
            'height_mm' => $art['finished_height_mm'],
            'scale' => $art['scale_label'],
            'sign_kind' => $art['sign_kind'],
            'note' => 'Working files open in CorelDRAW. This manifest does not parse a CDR file.',
        ];
    }

    /**
     * @return array{bytes: int, files: int, orphans: int, duplicates: int, largest: list<array<string, mixed>>}
     */
    public function storage(): array
    {
        $facts = $this->repo->storageFacts();
        $facts['largest'] = $this->repo->largest(10);

        return $facts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        return $this->repo->search($term, 30);
    }

    public function dpiLimit(?string $widthMm, ?string $heightMm): string
    {
        $edge = '0';
        if ($widthMm !== null && Decimal::isNumeric($widthMm)) {
            $edge = $widthMm;
        }
        if ($heightMm !== null && Decimal::isNumeric($heightMm) && Decimal::cmp($heightMm, $edge) > 0) {
            $edge = $heightMm;
        }
        $large = SettingsService::get('large_format_min_mm', '1000');
        if (Decimal::cmp($edge, $large) >= 0) {
            return SettingsService::get('dpi_large_format', '75');
        }

        return SettingsService::get('dpi_small_print', '150');
    }

    public function proofMessage(string $jobNumber, string $revision): string
    {
        return 'Your artwork proof ' . $revision . ' for Job ' . $jobNumber . ' is ready for review.';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reminders(): array
    {
        $days = (int) SettingsService::get('artwork_reminder_days', '3');
        $rows = $this->repo->reminders($days);
        foreach ($rows as $row) {
            $this->repo->stampReminder((int) $row['id']);
            $this->notifications->send(null, null, 'ARTWORK_APPROVAL_PENDING', 'Artwork awaiting approval', (string) $row['artwork_number'] . ' is still with the customer.', 'artwork', (int) $row['id'], 'NORMAL', 'art-remind-' . $row['id'] . '-' . date('Ymd'));
        }

        return $rows;
    }

    public function customerLibrary(int $customerId, string $title, string $category, string $reuse, int $artworkId, ?int $revisionId): int
    {
        return $this->repo->libraryAdd($customerId, $title, $this->libraryCategory($category) ?? 'OTHER', $reuse, $artworkId, $revisionId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function library(int $customerId): array
    {
        return $this->repo->library($customerId);
    }

    public function canDownloadSource(array $file, bool $portal): bool
    {
        if ($portal) {
            return in_array((string) $file['visibility'], ['CUSTOMER'], true) && (string) $file['category'] !== 'ARTWORK_SOURCE' && (string) $file['category'] !== 'FONT_REFERENCE';
        }
        if ((string) $file['category'] === 'ARTWORK_SOURCE' || (string) $file['category'] === 'FONT_REFERENCE') {
            return can('artwork.files.download_source') || can('artwork.admin');
        }

        return can('artwork.view') || can('jobs.view');
    }

    private function pdfPageCount(string $bytes): ?int
    {
        if (preg_match_all('/\/Type\s*\/Pages\b/', $bytes) !== 1) {
            return null;
        }
        if (preg_match('/\/Count\s+(\d+)/', $bytes, $match) !== 1) {
            return null;
        }
        $count = (int) $match[1];

        return $count > 0 ? $count : null;
    }

    private function writeBytes(string $name, string $bytes): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?? 'bin';
        $relative = 'artwork/' . bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
        $dir = base_path('storage/uploads/artwork');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(base_path('storage/uploads/' . $relative), $bytes);

        return $relative;
    }

    private function mime(string $name): string
    {
        return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }

    private function unit(string $value): bool
    {
        if (!Decimal::isNumeric($value)) {
            return false;
        }

        return Decimal::cmp($value, '0') >= 0 && Decimal::cmp($value, '1') <= 0;
    }

    private function priority(string $value): string
    {
        return $this->oneOf(strtoupper($value), ['NORMAL', 'HIGH', 'URGENT'], 'NORMAL');
    }

    private function scale(string $value): ?string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return null;
        }

        return in_array($value, ['1:1', '1:2', '1:5', '1:10', 'CUSTOM'], true) ? $value : 'CUSTOM';
    }

    private function libraryCategory(string $value): ?string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return null;
        }
        $allowed = ['LOGO', 'BRAND_ASSET', 'APPROVED_SIGN', 'VEHICLE_BRANDING', 'WINDOW_GRAPHICS', 'SAFETY_SIGN', 'PROMOTION', 'TEMPLATE', 'OTHER'];

        return in_array($value, $allowed, true) ? $value : 'OTHER';
    }

    /**
     * @param list<string> $allowed
     */
    private function oneOf(string $value, array $allowed, string $fallback): string
    {
        $value = strtoupper(trim($value));

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function nullableDate(mixed $value): ?string
    {
        $text = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? $text : null;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || !Decimal::isNumeric($text)) {
            return null;
        }

        return $text;
    }

    private function variables(mixed $value): ?string
    {
        if (!is_array($value) || $value === []) {
            return null;
        }
        $clean = [];
        foreach (['branch_name', 'phone', 'address', 'hours', 'manager', 'site_code'] as $key) {
            if (isset($value[$key]) && trim((string) $value[$key]) !== '') {
                $clean[$key] = trim((string) $value[$key]);
            }
        }

        return $clean === [] ? null : json_encode($clean, JSON_THROW_ON_ERROR);
    }
}
