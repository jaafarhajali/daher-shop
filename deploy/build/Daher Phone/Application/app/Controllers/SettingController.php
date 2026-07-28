<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Setting;
use App\Models\User;

final class SettingController extends Controller
{
    /** GET settings/profile — my profile + password change. */
    public function profile(): void
    {
        $user = (new User())->find(Auth::id());
        $this->render('settings/profile', ['account' => $user], 'My profile');
    }

    /** POST settings/save-profile */
    public function saveProfile(): void
    {
        $this->requireValidPost();

        $v = Validator::make($_POST, [
            'full_name' => 'required|maxlen:100',
            'email'     => 'email|maxlen:150',
        ]);
        if ($v->fails()) {
            $this->failBack($v->errors());
        }

        (new User())->updateProfile(Auth::id(), $this->input('full_name'), $this->input('email'));
        $_SESSION['full_name'] = $this->input('full_name');

        Flash::set('success', 'Profile updated.');
        redirect('settings/profile');
    }

    /** POST settings/change-password */
    public function changePassword(): void
    {
        $this->requireValidPost();

        $v = Validator::make($_POST, [
            'current_password' => 'required',
            'new_password'     => 'required|minlen:8|maxlen:255',
            'confirm_password' => 'required',
        ]);
        if ($v->fails()) {
            $this->failBack($v->errors());
        }

        $userModel = new User();
        $user = $userModel->find(Auth::id());

        if ($user === null
            || !password_verify((string) ($_POST['current_password'] ?? ''), $user['password_hash'])) {
            $this->failBack(['current_password' => 'Your current password is incorrect.']);
        }
        if (($_POST['new_password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
            $this->failBack(['confirm_password' => 'The new passwords do not match.']);
        }

        $userModel->updatePassword(Auth::id(), (string) $_POST['new_password']);
        Flash::set('success', 'Password changed.');
        redirect('settings/profile');
    }

    /** GET settings/shop — shop identity & preferences (admin only). */
    public function shop(): void
    {
        $this->render('settings/shop', [
            'values' => (new Setting())->all(),
        ], 'Shop settings');
    }

    /** POST settings/save-shop (admin only) */
    public function saveShop(): void
    {
        $this->requireValidPost();

        $v = Validator::make($_POST, [
            'shop_name'         => 'required|maxlen:100',
            'shop_address'      => 'maxlen:255',
            'shop_phone'        => 'maxlen:30',
            'shop_email'        => 'email|maxlen:150',
            'currency_symbol'   => 'required|maxlen:8',
            'currency_position' => 'required|in:before,after',
            'date_format'       => 'required|in:d/m/Y,m/d/Y,Y-m-d',
            'receipt_footer'    => 'maxlen:255',
            'default_min_stock' => 'required|int|min:0|max:1000',
            'accent_color'      => 'required|maxlen:7',
        ]);
        if ($v->fails()) {
            $this->failBack($v->errors());
        }

        $accent = $this->input('accent_color');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
            $this->failBack(['accent_color' => 'Accent color must be a hex value like #0d9488.']);
        }

        (new Setting())->setMany([
            'shop_name'         => $this->input('shop_name'),
            'shop_address'      => $this->input('shop_address'),
            'shop_phone'        => $this->input('shop_phone'),
            'shop_email'        => $this->input('shop_email'),
            'currency_symbol'   => $this->input('currency_symbol'),
            'currency_position' => $this->input('currency_position'),
            'date_format'       => $this->input('date_format'),
            'receipt_footer'    => $this->input('receipt_footer'),
            'default_min_stock' => (string) $this->inputInt('default_min_stock', 3),
            'accent_color'      => $accent,
        ]);

        Flash::set('success', 'Shop settings saved.');
        redirect('settings/shop');
    }
}
