<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Models\Backup;

/**
 * Admin-only (enforced by the router's ADMIN_PREFIXES).
 */
final class BackupController extends Controller
{
    public function index(): void
    {
        $this->render('backup/index', [
            'files' => (new Backup())->listFiles(),
        ], 'Backup & restore');
    }

    /** POST backup/create */
    public function create(): void
    {
        $this->requireValidPost();

        try {
            $filename = (new Backup())->create();
            Flash::set('success', 'Backup created: ' . $filename);
        } catch (\Throwable $ex) {
            Flash::set('danger', 'Backup failed: ' . $ex->getMessage());
        }

        redirect('backup/index');
    }

    /** GET backup/download&file=... */
    public function download(): void
    {
        $path = (new Backup())->resolve($this->queryString('file'));
        if ($path === null) {
            Flash::set('danger', 'Backup file not found.');
            redirect('backup/index');
        }

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** POST backup/restore — from an existing file or an uploaded .sql. */
    public function restore(): void
    {
        $this->requireValidPost();
        $m = new Backup();

        $path = null;
        $cleanup = null;

        if (!empty($_FILES['upload']['tmp_name']) && is_uploaded_file($_FILES['upload']['tmp_name'])) {
            $name = (string) $_FILES['upload']['name'];
            if (!str_ends_with(strtolower($name), '.sql')) {
                Flash::set('danger', 'Only .sql files can be restored.');
                redirect('backup/index');
            }
            if ((int) $_FILES['upload']['size'] > 100 * 1024 * 1024) {
                Flash::set('danger', 'Upload exceeds the 100 MB limit.');
                redirect('backup/index');
            }
            $path = $_FILES['upload']['tmp_name'];
        } else {
            $path = $m->resolve($this->input('file'));
            if ($path === null) {
                Flash::set('danger', 'Choose a backup file to restore.');
                redirect('backup/index');
            }
        }

        try {
            $count = $m->restore($path);
            Flash::set('success', "Database restored ({$count} statements executed).");
        } catch (\Throwable $ex) {
            Flash::set(
                'danger',
                'Restore failed and may be partial — restore a known-good backup. Error: ' . $ex->getMessage()
            );
        }

        redirect('backup/index');
    }

    /** POST backup/delete */
    public function delete(): void
    {
        $this->requireValidPost();

        if ((new Backup())->delete($this->input('file'))) {
            Flash::set('success', 'Backup deleted.');
        } else {
            Flash::set('danger', 'Backup file not found.');
        }

        redirect('backup/index');
    }
}
