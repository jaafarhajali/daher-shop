<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;

final class AuthController extends Controller
{
    /** GET auth/login — the sign-in screen. */
    public function login(): void
    {
        if (Auth::check()) {
            redirect('dashboard/index');
        }
        $this->renderBare('auth/login', [], 'Sign in');
    }

    /** POST auth/attempt */
    public function attempt(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Csrf::verify($_POST['_token'] ?? null)) {
            redirect('auth/login');
        }

        $v = Validator::make($_POST, [
            'username' => 'required|maxlen:50',
            'password' => 'required|maxlen:255',
        ]);
        if ($v->fails()) {
            Flash::set('danger', $v->firstError());
            redirect('auth/login');
        }

        $error = Auth::attempt($this->input('username'), (string) ($_POST['password'] ?? ''));

        if ($error !== null) {
            Flash::set('danger', $error);
            redirect('auth/login');
        }

        Flash::set('success', 'Welcome back, ' . ($_SESSION['full_name'] ?? '') . '!');
        redirect('dashboard/index');
    }

    /** POST auth/logout */
    public function logout(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && Csrf::verify($_POST['_token'] ?? null)) {
            Auth::logout();
        }
        redirect('auth/login');
    }
}
