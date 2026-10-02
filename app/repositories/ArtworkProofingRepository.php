<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Artwork revisions, proofs, annotations, and production-file versions.
 * SQL only. The service decides what a revision or an approval means.
 */
final class ArtworkProofingRepository extends Repository
{
    /**
     * @param array<string, mixed> $row
     */
    public function insertArtwork(array $row): int
    {
        $this->run(
            'INSERT INTO job_artworks (
                artwork_number, job_id, job_item_id, project_id, site_id, quote_id, catalogue_item_id,
                asset_id, customer_order_id, master_artwork_id, title, revision_number,
                original_filename, stored_filename, mime_type, file_size, status, priority,
                designer_id, reviewer_id, design_due, customer_due, production_required_date,
                finished_width_mm, finished_height_mm, scale_label, reuse_class, library_category,
                vehicle_template_id, sign_kind, production_file_required, colour_ack_required,
                variables_json, uploaded_by
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['artwork_number'], $row['job_id'], $row['job_item_id'], $row['project_id'], $row['site_id'],
                $row['quote_id'], $row['catalogue_item_id'], $row['asset_id'], $row['customer_order_id'],
                $row['master_artwork_id'], $row['title'], 1, $row['original_filename'], $row['stored_filename'],
                $row['mime_type'], $row['file_size'], $row['status'], $row['priority'], $row['designer_id'],
                $row['reviewer_id'], $row['design_due'], $row['customer_due'], $row['production_required_date'],
                $row['finished_width_mm'], $row['finished_height_mm'], $row['scale_label'], $row['reuse_class'],
                $row['library_category'], $row['vehicle_template_id'], $row['sign_kind'],
                $row['production_file_required'], $row['colour_ack_required'], $row['variables_json'], $row['uploaded_by'],
            ]
        );

        return $this->insertId();
    }

    public function artwork(int $id): ?array
    {
        return $this->one(
            'SELECT a.*, j.job_number, j.customer_id, j.title AS job_title, c.company_name
             FROM job_artworks a
             JOIN jobs j ON j.id = a.job_id
             JOIN customers c ON c.id = j.customer_id
             WHERE a.id = ?',
            [$id]
        );
    }

    public function lockArtwork(int $id): ?array
    {
        return $this->one('SELECT * FROM job_artworks WHERE id = ? FOR UPDATE', [$id]);
    }

    public function artworkForCustomer(int $customerId, int $artworkId): ?array
    {
        return $this->one(
            'SELECT a.* FROM job_artworks a JOIN jobs j ON j.id = a.job_id WHERE a.id = ? AND j.customer_id = ?',
            [$artworkId, $customerId]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertRevision(array $row): int
    {
        $this->run(
            'INSERT INTO artwork_revisions (
                artwork_id, revision_number, revision_label, source_revision_id, reason_code, change_class,
                change_summary, internal_notes, customer_notes, status, created_by
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['artwork_id'], $row['revision_number'], $row['revision_label'], $row['source_revision_id'],
                $row['reason_code'], $row['change_class'], $row['change_summary'], $row['internal_notes'],
                $row['customer_notes'], $row['status'], $row['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function nextRevisionNumber(int $artworkId): int
    {
        $row = $this->one(
            'SELECT revision_number FROM artwork_revisions WHERE artwork_id = ? ORDER BY revision_number DESC LIMIT 1 FOR UPDATE',
            [$artworkId]
        );

        return $row === null ? 1 : ((int) $row['revision_number'] + 1);
    }

    public function revision(int $id): ?array
    {
        return $this->one('SELECT * FROM artwork_revisions WHERE id = ?', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function revisions(int $artworkId): array
    {
        return $this->rows('SELECT * FROM artwork_revisions WHERE artwork_id = ? ORDER BY revision_number', [$artworkId]);
    }

    public function setRevisionStatus(int $id, string $status): void
    {
        $this->run('UPDATE artwork_revisions SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function setInternalReview(int $id, string $status): void
    {
        $this->run('UPDATE artwork_revisions SET internal_review_status = ? WHERE id = ?', [$status, $id]);
    }

    public function pointCurrent(int $artworkId, int $revisionId, int $number, string $status, int $approved): void
    {
        $this->run(
            'UPDATE job_artworks SET current_revision_id = ?, revision_number = ?, status = ?, customer_approved = ? WHERE id = ?',
            [$revisionId, $number, $status, $approved, $artworkId]
        );
    }

    public function markApproved(int $artworkId, string $name): void
    {
        $this->run(
            'UPDATE job_artworks SET customer_approved = 1, customer_approved_at = NOW(), customer_approved_by = ?, status = ? WHERE id = ?',
            [$name, 'APPROVED', $artworkId]
        );
    }

    public function stampFirstProof(int $artworkId): void
    {
        $this->run(
            'UPDATE job_artworks SET first_proof_sent_at = COALESCE(first_proof_sent_at, NOW()), status = ? WHERE id = ?',
            ['CUSTOMER_REVIEW', $artworkId]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertProof(array $row): int
    {
        $this->run(
            'INSERT INTO artwork_proofs (
                artwork_id, revision_id, original_filename, stored_filename, mime_type, extension, file_size,
                sha256, page_count, width_px, height_px, colour_mode, inspection_note, visibility, created_by
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['artwork_id'], $row['revision_id'], $row['original_filename'], $row['stored_filename'],
                $row['mime_type'], $row['extension'], $row['file_size'], $row['sha256'], $row['page_count'],
                $row['width_px'], $row['height_px'], $row['colour_mode'], $row['inspection_note'],
                $row['visibility'], $row['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function proof(int $id): ?array
    {
        return $this->one('SELECT * FROM artwork_proofs WHERE id = ?', [$id]);
    }

    public function latestProof(int $revisionId): ?array
    {
        return $this->one('SELECT * FROM artwork_proofs WHERE revision_id = ? ORDER BY id DESC LIMIT 1', [$revisionId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertFile(array $row): int
    {
        $this->run(
            'INSERT INTO artwork_files (
                artwork_id, revision_id, customer_id, job_id, project_id, site_id, asset_id, category, visibility,
                original_filename, stored_filename, mime_type, extension, file_size, sha256, uploaded_by
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['artwork_id'], $row['revision_id'], $row['customer_id'], $row['job_id'], $row['project_id'],
                $row['site_id'], $row['asset_id'], $row['category'], $row['visibility'], $row['original_filename'],
                $row['stored_filename'], $row['mime_type'], $row['extension'], $row['file_size'], $row['sha256'],
                $row['uploaded_by'],
            ]
        );

        return $this->insertId();
    }

    public function file(int $id): ?array
    {
        return $this->one('SELECT * FROM artwork_files WHERE id = ?', [$id]);
    }

    public function duplicateHash(string $hash): ?array
    {
        return $this->one('SELECT id, original_filename, artwork_id FROM artwork_files WHERE sha256 = ? ORDER BY id LIMIT 1', [$hash]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertAnnotation(array $row): int
    {
        $this->run(
            'INSERT INTO artwork_annotations (
                proof_id, artwork_id, page_number, kind, x, y, width, height, annotation_type, visibility, status,
                body, author_kind, author_user_id, author_portal_user_id, author_name
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['proof_id'], $row['artwork_id'], $row['page_number'], $row['kind'], $row['x'], $row['y'],
                $row['width'], $row['height'], $row['annotation_type'], $row['visibility'], 'OPEN', $row['body'],
                $row['author_kind'], $row['author_user_id'], $row['author_portal_user_id'], $row['author_name'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function annotations(int $proofId, ?string $visibility, ?int $page): array
    {
        $sql = 'SELECT * FROM artwork_annotations WHERE proof_id = ?';
        $params = [$proofId];
        if ($visibility !== null) {
            $sql .= ' AND visibility = ?';
            $params[] = $visibility;
        }
        if ($page !== null) {
            $sql .= ' AND page_number = ?';
            $params[] = $page;
        }
        $sql .= ' ORDER BY id';

        return $this->rows($sql, $params);
    }

    public function annotation(int $id): ?array
    {
        return $this->one('SELECT * FROM artwork_annotations WHERE id = ?', [$id]);
    }

    public function resolveAnnotation(int $id, int $revisionId, string $status): void
    {
        $this->run(
            'UPDATE artwork_annotations SET status = ?, resolved_revision_id = ? WHERE id = ?',
            [$status, $revisionId, $id]
        );
    }

    public function linkTask(int $annotationId, int $taskId): void
    {
        $this->run('UPDATE artwork_annotations SET task_id = ? WHERE id = ?', [$taskId, $annotationId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertComment(array $row): int
    {
        $this->run(
            'INSERT INTO artwork_annotation_comments (
                annotation_id, visibility, body, author_kind, author_user_id, author_portal_user_id, author_name, mentioned_user_id
             ) VALUES (?,?,?,?,?,?,?,?)',
            [
                $row['annotation_id'], $row['visibility'], $row['body'], $row['author_kind'],
                $row['author_user_id'], $row['author_portal_user_id'], $row['author_name'], $row['mentioned_user_id'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function comments(int $annotationId, ?string $visibility): array
    {
        if ($visibility === null) {
            return $this->rows('SELECT * FROM artwork_annotation_comments WHERE annotation_id = ? ORDER BY id', [$annotationId]);
        }

        return $this->rows(
            'SELECT * FROM artwork_annotation_comments WHERE annotation_id = ? AND visibility = ? ORDER BY id',
            [$annotationId, $visibility]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertRevisionComment(array $row): int
    {
        $this->run(
            'INSERT INTO artwork_revision_comments (revision_id, visibility, body, author_kind, author_user_id, author_portal_user_id, author_name)
             VALUES (?,?,?,?,?,?,?)',
            [
                $row['revision_id'], $row['visibility'], $row['body'], $row['author_kind'],
                $row['author_user_id'], $row['author_portal_user_id'], $row['author_name'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertApproval(array $row): int
    {
        $this->run(
            'INSERT INTO artwork_approvals (
                job_artwork_id, revision_id, proof_id, approval_status, customer_name, approval_method, reference,
                notes, statement_version, ip_address, user_agent, portal_user_id, share_token_id, approved_at, recorded_by
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['job_artwork_id'], $row['revision_id'], $row['proof_id'], $row['approval_status'],
                $row['customer_name'], $row['approval_method'], $row['reference'], $row['notes'],
                $row['statement_version'], $row['ip_address'], $row['user_agent'], $row['portal_user_id'],
                $row['share_token_id'], $row['approved_at'], $row['recorded_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function approvals(int $artworkId): array
    {
        return $this->rows('SELECT * FROM artwork_approvals WHERE job_artwork_id = ? ORDER BY id', [$artworkId]);
    }

    public function approvalForRevision(int $revisionId): ?array
    {
        return $this->one(
            "SELECT * FROM artwork_approvals WHERE revision_id = ? AND approval_status = 'APPROVED' ORDER BY id DESC LIMIT 1",
            [$revisionId]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertShare(array $row): int
    {
        $this->run(
            'INSERT INTO artwork_share_tokens (token_hash, artwork_id, revision_id, proof_id, permission, expires_at, created_by)
             VALUES (?,?,?,?,?,?,?)',
            [
                $row['token_hash'], $row['artwork_id'], $row['revision_id'], $row['proof_id'],
                $row['permission'], $row['expires_at'], $row['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function shareByHash(string $hash): ?array
    {
        return $this->one('SELECT * FROM artwork_share_tokens WHERE token_hash = ?', [$hash]);
    }

    public function share(int $id): ?array
    {
        return $this->one('SELECT * FROM artwork_share_tokens WHERE id = ?', [$id]);
    }

    public function revokeShare(int $id): void
    {
        $this->run('UPDATE artwork_share_tokens SET revoked_at = NOW() WHERE id = ?', [$id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertProductionFile(array $row): int
    {
        $this->run(
            'INSERT INTO production_files (
                job_id, job_item_id, artwork_id, artwork_revision_id, proof_id, category, status, version_label,
                pf_number, artwork_revision, original_name, sha256, stored_filename, mime_type, file_size,
                visibility, change_class, scale_label, created_by
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['job_id'], $row['job_item_id'], $row['artwork_id'], $row['artwork_revision_id'], $row['proof_id'],
                $row['category'], $row['status'], $row['version_label'], $row['pf_number'], $row['artwork_revision'],
                $row['original_name'], $row['sha256'], $row['stored_filename'], $row['mime_type'], $row['file_size'],
                $row['visibility'], $row['change_class'], $row['scale_label'], $row['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function nextPf(int $artworkId): int
    {
        $row = $this->one(
            'SELECT pf_number FROM production_files WHERE artwork_id = ? ORDER BY pf_number DESC LIMIT 1 FOR UPDATE',
            [$artworkId]
        );

        return $row === null || $row['pf_number'] === null ? 1 : ((int) $row['pf_number'] + 1);
    }

    public function productionFile(int $id): ?array
    {
        return $this->one('SELECT * FROM production_files WHERE id = ?', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function productionFiles(int $artworkId): array
    {
        return $this->rows('SELECT * FROM production_files WHERE artwork_id = ? ORDER BY pf_number, id', [$artworkId]);
    }

    public function currentProductionFile(int $jobId): ?array
    {
        return $this->one(
            "SELECT * FROM production_files
             WHERE job_id = ? AND status = 'APPROVED_FOR_PRODUCTION'
             ORDER BY pf_number DESC, id DESC LIMIT 1",
            [$jobId]
        );
    }

    public function approveProductionFile(int $id, int $userId): void
    {
        $this->run(
            "UPDATE production_files SET status = 'APPROVED_FOR_PRODUCTION', approved_by = ?, approved_at = NOW() WHERE id = ?",
            [$userId, $id]
        );
    }

    public function supersedeProductionFile(int $id, int $byId): void
    {
        $this->run("UPDATE production_files SET status = 'SUPERSEDED', superseded_by = ? WHERE id = ?", [$byId, $id]);
    }

    public function setProductionStatus(int $id, string $status): void
    {
        $this->run('UPDATE production_files SET status = ? WHERE id = ?', [$status, $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertPreflight(array $row): void
    {
        $this->run(
            'INSERT INTO artwork_preflight_checks (production_file_id, check_code, result, source, detail, checked_by)
             VALUES (?,?,?,?,?,?)',
            [$row['production_file_id'], $row['check_code'], $row['result'], $row['source'], $row['detail'], $row['checked_by']]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function preflight(int $fileId): array
    {
        return $this->rows('SELECT * FROM artwork_preflight_checks WHERE production_file_id = ? ORDER BY id', [$fileId]);
    }

    public function setPhysicalStatus(int $artworkId, string $status): void
    {
        $this->run('UPDATE job_artworks SET physical_sample_status = ? WHERE id = ?', [$status, $artworkId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertPhysical(array $row): int
    {
        $this->run(
            'INSERT INTO artwork_physical_proofs (artwork_id, sample_reference, sample_kind, status, notes, created_by)
             VALUES (?,?,?,?,?,?)',
            [$row['artwork_id'], $row['sample_reference'], $row['sample_kind'], $row['status'], $row['notes'], $row['created_by']]
        );

        return $this->insertId();
    }

    public function approvePhysical(int $id, string $name): void
    {
        $this->run(
            "UPDATE artwork_physical_proofs SET status = 'APPROVED', approver_name = ?, approved_at = NOW() WHERE id = ?",
            [$name, $id]
        );
    }

    public function openPhysical(int $artworkId): ?array
    {
        return $this->one(
            "SELECT * FROM artwork_physical_proofs WHERE artwork_id = ? AND status <> 'APPROVED' ORDER BY id DESC LIMIT 1",
            [$artworkId]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertBrand(array $row): int
    {
        $this->run(
            'INSERT INTO customer_brand_assets (
                customer_id, category, title, version_number, status, is_primary, sha256, original_filename,
                stored_filename, mime_type, file_size, supersedes_id, created_by
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['customer_id'], $row['category'], $row['title'], $row['version_number'], $row['status'],
                $row['is_primary'], $row['sha256'], $row['original_filename'], $row['stored_filename'],
                $row['mime_type'], $row['file_size'], $row['supersedes_id'], $row['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function primaryBrand(int $customerId, string $category): ?array
    {
        return $this->one(
            "SELECT * FROM customer_brand_assets WHERE customer_id = ? AND category = ? AND is_primary = 1 AND status = 'CURRENT' ORDER BY id DESC LIMIT 1",
            [$customerId, $category]
        );
    }

    public function clearPrimary(int $customerId, string $category): void
    {
        $this->run(
            "UPDATE customer_brand_assets SET is_primary = 0, status = 'SUPERSEDED' WHERE customer_id = ? AND category = ? AND is_primary = 1",
            [$customerId, $category]
        );
    }

    public function linkBrand(int $artworkId, int $assetId): void
    {
        $this->run(
            'INSERT IGNORE INTO artwork_brand_links (artwork_id, brand_asset_id) VALUES (?,?)',
            [$artworkId, $assetId]
        );
    }

    public function brandChanged(int $artworkId): ?array
    {
        return $this->one(
            "SELECT b.id, b.title, b.version_number
             FROM artwork_brand_links l
             JOIN customer_brand_assets linked ON linked.id = l.brand_asset_id
             JOIN customer_brand_assets b ON b.customer_id = linked.customer_id AND b.category = linked.category AND b.is_primary = 1 AND b.status = 'CURRENT'
             WHERE l.artwork_id = ? AND b.id <> linked.id
             LIMIT 1",
            [$artworkId]
        );
    }

    public function libraryAdd(int $customerId, string $title, string $category, string $reuse, int $artworkId, ?int $revisionId): int
    {
        $this->run(
            'INSERT INTO customer_artwork_library (customer_id, title, category, status, reuse_class, artwork_id, revision_id)
             VALUES (?,?,?,?,?,?,?)',
            [$customerId, $title, $category, 'APPROVED', $reuse, $artworkId, $revisionId]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function library(int $customerId): array
    {
        return $this->rows(
            'SELECT * FROM customer_artwork_library WHERE customer_id = ? ORDER BY id DESC LIMIT 100',
            [$customerId]
        );
    }

    /**
     * @return array<string, int>
     */
    public function projectCounts(int $projectId): array
    {
        $rows = $this->rows(
            'SELECT status, COUNT(*) AS n FROM job_artworks WHERE project_id = ? GROUP BY status',
            [$projectId]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function queue(string $status, int $limit): array
    {
        $limit = max(1, min(100, $limit));

        return $this->rows(
            "SELECT a.id, a.artwork_number, a.title, a.status, a.priority, a.design_due, a.customer_due, j.job_number
             FROM job_artworks a JOIN jobs j ON j.id = a.job_id
             WHERE a.status = ? AND a.artwork_number IS NOT NULL
             ORDER BY a.design_due IS NULL, a.design_due, a.id
             LIMIT {$limit}",
            [$status]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $like = '%' . $term . '%';

        return $this->rows(
            "SELECT f.id, f.original_filename, f.category, f.sha256, f.artwork_id, a.artwork_number, j.job_number
             FROM artwork_files f
             LEFT JOIN job_artworks a ON a.id = f.artwork_id
             LEFT JOIN jobs j ON j.id = f.job_id
             WHERE f.original_filename LIKE ? OR a.artwork_number LIKE ? OR j.job_number LIKE ?
             ORDER BY f.id DESC
             LIMIT {$limit}",
            [$like, $like, $like]
        );
    }

    /**
     * @return array{bytes: int, files: int, orphans: int, duplicates: int}
     */
    public function storageFacts(): array
    {
        $row = $this->one('SELECT COALESCE(SUM(file_size),0) AS bytes, COUNT(*) AS files FROM artwork_files');
        $orphans = $this->one('SELECT COUNT(*) AS n FROM artwork_files WHERE artwork_id IS NULL AND job_id IS NULL AND customer_id IS NULL');
        $dupes = $this->one('SELECT COUNT(*) AS n FROM (SELECT sha256 FROM artwork_files GROUP BY sha256 HAVING COUNT(*) > 1) d');

        return [
            'bytes' => (int) ($row['bytes'] ?? 0),
            'files' => (int) ($row['files'] ?? 0),
            'orphans' => (int) ($orphans['n'] ?? 0),
            'duplicates' => (int) ($dupes['n'] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function largest(int $limit): array
    {
        $limit = max(1, min(20, $limit));

        return $this->rows("SELECT id, original_filename, category, file_size, sha256 FROM artwork_files ORDER BY file_size DESC LIMIT {$limit}");
    }

    public function logDownload(int $fileId, ?int $userId, string $revision, string $version): void
    {
        $this->run(
            'INSERT INTO artwork_download_log (production_file_id, user_id, revision_label, version_label) VALUES (?,?,?,?)',
            [$fileId, $userId, $revision, $version]
        );
    }

    /**
     * @return array{physical_pending: bool, file_missing: bool, stale: bool}
     */
    public function releaseFacts(int $jobId): array
    {
        $physical = $this->one(
            "SELECT COUNT(*) AS n FROM job_artworks
             WHERE job_id = ? AND physical_sample_status IN ('REQUIRED','PREPARED','CUSTOMER_REVIEW')",
            [$jobId]
        );
        $missing = $this->one(
            "SELECT COUNT(*) AS n FROM job_artworks a
             WHERE a.job_id = ? AND a.production_file_required = 1
             AND NOT EXISTS (
                SELECT 1 FROM production_files f
                WHERE f.artwork_id = a.id AND f.status = 'APPROVED_FOR_PRODUCTION'
             )",
            [$jobId]
        );
        $stale = $this->one(
            "SELECT COUNT(*) AS n FROM artwork_release_snapshots s
             JOIN production_files f ON f.id = s.production_file_id
             JOIN production_releases r ON r.id = s.release_id
             WHERE r.job_id = ? AND r.status = 'RELEASED' AND f.status = 'SUPERSEDED'",
            [$jobId]
        );

        return [
            'physical_pending' => (int) ($physical['n'] ?? 0) > 0,
            'file_missing' => (int) ($missing['n'] ?? 0) > 0,
            'stale' => (int) ($stale['n'] ?? 0) > 0,
        ];
    }

    public function captureRelease(int $releaseId, int $jobId): void
    {
        $rows = $this->rows(
            "SELECT a.id AS artwork_id, a.current_revision_id, p.id AS proof_id, f.id AS file_id, f.sha256
             FROM job_artworks a
             LEFT JOIN artwork_proofs p ON p.revision_id = a.current_revision_id
             LEFT JOIN production_files f ON f.artwork_id = a.id AND f.status = 'APPROVED_FOR_PRODUCTION'
             WHERE a.job_id = ? AND a.artwork_number IS NOT NULL",
            [$jobId]
        );
        foreach ($rows as $row) {
            $this->run(
                'INSERT INTO artwork_release_snapshots (release_id, artwork_id, revision_id, proof_id, production_file_id, file_hash)
                 VALUES (?,?,?,?,?,?)',
                [$releaseId, $row['artwork_id'], $row['current_revision_id'], $row['proof_id'], $row['file_id'], $row['sha256']]
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function snapshotRows(int $jobId): array
    {
        return $this->rows(
            "SELECT a.artwork_number, r.revision_label, p.sha256 AS proof_hash, f.version_label, f.sha256 AS file_hash, f.status
             FROM job_artworks a
             LEFT JOIN artwork_revisions r ON r.id = a.current_revision_id
             LEFT JOIN artwork_proofs p ON p.revision_id = r.id
             LEFT JOIN production_files f ON f.artwork_id = a.id AND f.status = 'APPROVED_FOR_PRODUCTION'
             WHERE a.job_id = ? AND a.artwork_number IS NOT NULL",
            [$jobId]
        );
    }

    public function checkout(int $artworkId, int $userId): void
    {
        $this->run('UPDATE job_artworks SET checkout_user_id = ?, checkout_at = NOW() WHERE id = ?', [$userId, $artworkId]);
    }

    public function clearCheckout(int $artworkId): void
    {
        $this->run('UPDATE job_artworks SET checkout_user_id = NULL, checkout_at = NULL WHERE id = ?', [$artworkId]);
    }

    public function setPriority(int $artworkId, string $priority): void
    {
        $this->run('UPDATE job_artworks SET priority = ? WHERE id = ?', [$priority, $artworkId]);
    }

    public function setBlock(int $artworkId, string $reason, string $message, int $userId): void
    {
        $this->run('UPDATE job_artworks SET block_reason = ?, status = ? WHERE id = ?', [$reason, 'CHANGES_REQUESTED', $artworkId]);
        $this->run(
            'INSERT INTO artwork_blocks (artwork_id, reason, customer_message, created_by) VALUES (?,?,?,?)',
            [$artworkId, $reason, $message, $userId]
        );
    }

    public function setBleed(int $artworkId, string $top, string $right, string $bottom, string $left): void
    {
        $this->run(
            'UPDATE job_artworks SET bleed_top_mm = ?, bleed_right_mm = ?, bleed_bottom_mm = ?, bleed_left_mm = ? WHERE id = ?',
            [$top, $right, $bottom, $left, $artworkId]
        );
    }

    public function setCut(int $artworkId, string $state): void
    {
        $this->run('UPDATE job_artworks SET cut_contour = ? WHERE id = ?', [$state, $artworkId]);
    }

    public function reminders(int $days): array
    {
        $days = max(1, $days);

        return $this->rows(
            "SELECT a.id, a.artwork_number, a.title, j.job_number
             FROM job_artworks a JOIN jobs j ON j.id = a.job_id
             WHERE a.status = 'CUSTOMER_REVIEW'
             AND a.first_proof_sent_at IS NOT NULL
             AND a.first_proof_sent_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)
             AND (a.last_customer_reminder_at IS NULL OR a.last_customer_reminder_at < DATE_SUB(NOW(), INTERVAL {$days} DAY))
             ORDER BY a.id LIMIT 50"
        );
    }

    public function stampReminder(int $artworkId): void
    {
        $this->run('UPDATE job_artworks SET last_customer_reminder_at = NOW() WHERE id = ?', [$artworkId]);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function metrics(): array
    {
        $revs = $this->one('SELECT COUNT(*) AS n, COUNT(DISTINCT artwork_id) AS arts FROM artwork_revisions');
        $approved = $this->one("SELECT COUNT(*) AS n FROM artwork_approvals WHERE approval_status = 'APPROVED' AND revision_id IS NOT NULL");

        return [
            'revisions' => (int) ($revs['n'] ?? 0),
            'artworks' => (int) ($revs['arts'] ?? 0),
            'approvals' => (int) ($approved['n'] ?? 0),
        ];
    }

    public function insertTask(int $jobId, string $title, int $userId): int
    {
        $this->run(
            'INSERT INTO job_tasks (job_id, title, task_type, status, created_by) VALUES (?,?,?,?,?)',
            [$jobId, $title, 'ARTWORK', 'TODO', $userId]
        );

        return $this->insertId();
    }

    public function checklistSeed(int $revisionId, string $code): void
    {
        $this->run(
            'INSERT IGNORE INTO artwork_checklist_items (revision_id, check_code) VALUES (?,?)',
            [$revisionId, $code]
        );
    }

    public function confirmChecklist(int $revisionId, string $code, int $userId): void
    {
        $this->run(
            "UPDATE artwork_checklist_items SET result = 'CONFIRMED', checked_by = ?, checked_at = NOW() WHERE revision_id = ? AND check_code = ?",
            [$userId, $revisionId, $code]
        );
    }
}
