<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller: view rendering, input access, CSRF-checked POST guard,
 * JSON responses, and the redirect-back-with-errors pattern.
 */
abstract class Controller
{
    /** Render a view inside the main application layout. */
    protected function render(string $view, array $data = [], string $pageTitle = ''): void
    {
        $data['pageTitle'] = $pageTitle !== '' ? $pageTitle : APP_NAME;
        extract($data, EXTR_SKIP);

        ob_start();
        require APP_PATH . '/Views/' . $view . '.php';
        $content = ob_get_clean();

        require APP_PATH . '/Views/layouts/main.php';
    }

    /** Render a standalone view (login screen, printable receipts). */
    protected function renderBare(string $view, array $data = [], string $pageTitle = ''): void
    {
        $data['pageTitle'] = $pageTitle !== '' ? $pageTitle : APP_NAME;
        extract($data, EXTR_SKIP);
        require APP_PATH . '/Views/' . $view . '.php';
    }

    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        exit;
    }

    /**
     * Guard for state-changing actions: requires POST + a valid CSRF token.
     * Sends the user back with an error message otherwise.
     */
    protected function requireValidPost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            redirect('dashboard/index');
        }
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            Flash::set('danger', 'Your session token expired. Please try again.');
            $this->back();
        }
    }

    /** Same guard for AJAX endpoints — answers JSON instead of redirecting. */
    protected function requireValidPostJson(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
            || !Csrf::verify($_POST['_token'] ?? null)) {
            $this->json(['ok' => false, 'error' => 'Invalid request token. Refresh the page.'], 419);
        }
    }

    // --- input helpers -------------------------------------------------------

    protected function input(string $key, string $default = ''): string
    {
        return trim((string) ($_POST[$key] ?? $default));
    }

    protected function inputInt(string $key, int $default = 0): int
    {
        $v = $_POST[$key] ?? null;

        return is_numeric($v) ? (int) $v : $default;
    }

    protected function inputFloat(string $key, float $default = 0.0): float
    {
        $v = $_POST[$key] ?? null;

        return is_numeric($v) ? round((float) $v, 2) : $default;
    }

    protected function queryInt(string $key, int $default = 0): int
    {
        $v = $_GET[$key] ?? null;

        return is_numeric($v) ? (int) $v : $default;
    }

    protected function queryString(string $key, string $default = ''): string
    {
        return trim((string) ($_GET[$key] ?? $default));
    }

    // --- redirect-back pattern ------------------------------------------------

    /** Store validation errors + submitted input, then return to the form. */
    protected function failBack(array $errors): never
    {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old']    = $_POST;
        Flash::set('danger', (string) reset($errors));
        $this->back();
    }

    protected function back(): never
    {
        $target = $_SERVER['HTTP_REFERER'] ?? url('dashboard/index');
        header('Location: ' . $target);
        exit;
    }
}
