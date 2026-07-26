<?php

declare(strict_types=1);

namespace App\Core;

/**
 * One-shot flash messages, rendered as toast notifications.
 * Types map to Bootstrap contextual colors: success, danger, warning, info.
 */
final class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int, array{type:string, message:string}> */
    public static function pull(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        return $messages;
    }

    private function __construct()
    {
    }
}
