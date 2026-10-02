<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\ArtworkProofingRepository;
use App\Services\ArtworkProofingService;
use App\Services\SettingsService;

/**
 * Staff artwork workspace. Files are downloaded through this controller.
 */
final class ArtworkController
{
    public function queue(): void
    {
        $service = new ArtworkProofingService();
        $sections = [];
        foreach (ArtworkProofingService::QUEUE as $status) {
            $sections[$status] = (new ArtworkProofingRepository())->queue($status, 40);
        }
        View::render('artwork/queue', [
            'title' => 'Design queue',
            'activeNav' => 'design',
            'sections' => $sections,
            'metrics' => (new ArtworkProofingRepository())->metrics(),
        ]);
    }

    public function show(string $id): void
    {
        $art = $this->artwork($id);
        $repo = new ArtworkProofingRepository();
        $revisionId = (int) ($art['current_revision_id'] ?? 0);
        $proof = $revisionId > 0 ? $repo->latestProof($revisionId) : null;
        $tab = (string) ($_GET['tab'] ?? 'overview');
        View::render('artwork/workspace', [
            'title' => (string) $art['artwork_number'],
            'activeNav' => 'design',
            'artwork' => $art,
            'tab' => $tab,
            'revisions' => $repo->revisions((int) $art['id']),
            'proof' => $proof,
            'annotations' => $proof === null ? [] : $repo->annotations((int) $proof['id'], null, null),
            'approvals' => $repo->approvals((int) $art['id']),
            'files' => $repo->productionFiles((int) $art['id']),
            'brandWarning' => (new ArtworkProofingService())->brandWarning((int) $art['id']),
            'statement' => SettingsService::get('artwork_approval_statement', ''),
        ]);
    }

    public function revise(string $id): void
    {
        $art = $this->artwork($id);
        $result = (new ArtworkProofingService())->revise((int) $art['id'], $_POST, (int) auth_user()['id']);
        $this->back($result['errors'], (int) $art['id'], $result['errors'] === [] ? 'Revision ' . $result['label'] . ' created. The previous revision was not replaced.' : '');
    }

    public function proof(string $id): void
    {
        $art = $this->artwork($id);
        $upload = $this->upload();
        if ($upload === null) {
            $this->back(['_form' => 'Choose a PDF, JPG, or PNG proof.'], (int) $art['id'], '');
        }
        $result = (new ArtworkProofingService())->sendProof((int) ($_POST['revision_id'] ?? 0), $upload, (int) auth_user()['id']);
        $this->back($result['errors'], (int) $art['id'], 'Proof sent. It is marked PROOF and is not a production file.');
    }

    public function annotate(string $id): void
    {
        $art = $this->artwork($id);
        $result = (new ArtworkProofingService())->annotate(
            (int) ($_POST['proof_id'] ?? 0),
            $_POST,
            'STAFF',
            (int) auth_user()['id'],
            null,
            trim((string) (auth_user()['first_name'] ?? '') . ' ' . (string) (auth_user()['last_name'] ?? ''))
        );
        $this->back($result['errors'], (int) $art['id'], 'Comment placed on the proof.');
    }

    public function production(string $id): void
    {
        $art = $this->artwork($id);
        $upload = $this->upload();
        if ($upload === null) {
            $this->back(['_form' => 'Choose the production file.'], (int) $art['id'], '');
        }
        $result = (new ArtworkProofingService())->productionFile(
            (int) $art['id'],
            (int) ($_POST['revision_id'] ?? 0),
            strtoupper((string) ($_POST['category'] ?? 'PRINT')),
            $upload,
            (int) auth_user()['id']
        );
        $this->back($result['errors'], (int) $art['id'], $result['label'] !== null ? $result['label'] . ' stored. It still needs production approval.' : '');
    }

    public function approveProduction(string $id): void
    {
        $art = $this->artwork($id);
        $result = (new ArtworkProofingService())->approveProductionFile((int) ($_POST['file_id'] ?? 0), (int) auth_user()['id']);
        $this->back($result['errors'], (int) $art['id'], 'Production file approved for production.');
    }

    public function workshop(string $jobId): void
    {
        $pack = (new ArtworkProofingService())->workshop((int) $jobId);
        View::render('artwork/workshop', [
            'title' => 'Production file',
            'activeNav' => 'workshop-floor',
            'jobId' => (int) $jobId,
            'pack' => $pack,
        ]);
    }

    public function downloadProduction(string $id): void
    {
        $file = (new ArtworkProofingRepository())->productionFile((int) $id);
        if ($file === null || (string) $file['status'] !== 'APPROVED_FOR_PRODUCTION') {
            abort_not_found('That production file is not the current approved file.');
        }
        (new ArtworkProofingService())->recordProductionDownload((int) $file['id'], (int) auth_user()['id']);
        $this->send((string) $file['stored_filename'], (string) $file['mime_type'], (string) $file['original_name']);
    }

    public function downloadSource(string $id): void
    {
        $file = (new ArtworkProofingRepository())->file((int) $id);
        if ($file === null || !(new ArtworkProofingService())->canDownloadSource($file, false)) {
            abort_not_found('That file is not available.');
        }
        $this->send((string) $file['stored_filename'], (string) $file['mime_type'], (string) $file['original_filename']);
    }

    public function storage(): void
    {
        View::render('artwork/storage', [
            'title' => 'Artwork storage',
            'activeNav' => 'design',
            'storage' => (new ArtworkProofingService())->storage(),
            'results' => (new ArtworkProofingService())->search((string) ($_GET['q'] ?? '')),
        ]);
    }

    public function compare(string $id): void
    {
        $art = $this->artwork($id);
        $repo = new ArtworkProofingRepository();
        $left = $repo->latestProof((int) ($_GET['left'] ?? 0));
        $right = $repo->latestProof((int) ($_GET['right'] ?? 0));
        View::render('artwork/compare', [
            'title' => 'Compare revisions',
            'activeNav' => 'design',
            'artwork' => $art,
            'left' => $left,
            'right' => $right,
            'revisions' => $repo->revisions((int) $art['id']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function artwork(string $id): array
    {
        $art = (new ArtworkProofingRepository())->artwork((int) $id);
        if ($art === null || $art['artwork_number'] === null) {
            abort_not_found('That artwork was not found.');
        }

        return $art;
    }

    /**
     * @return array{name: string, bytes: string}|null
     */
    private function upload(): ?array
    {
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $bytes = (string) file_get_contents((string) $file['tmp_name']);

        return ['name' => (string) $file['name'], 'bytes' => $bytes];
    }

    /**
     * @param array<string, string> $errors
     */
    private function back(array $errors, int $artworkId, string $success): void
    {
        if ($errors !== []) {
            flash('error', (string) reset($errors));
        } else {
            flash('success', $success);
        }
        redirect('/artwork/' . $artworkId);
    }

    private function send(string $relative, string $mime, string $name): void
    {
        if (str_contains($relative, '..')) {
            abort_not_found('That file was not found.');
        }
        $path = base_path('storage/uploads/' . $relative);
        if (!is_file($path)) {
            abort_not_found('That file is missing.');
        }
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
        readfile($path);
        exit;
    }
}
