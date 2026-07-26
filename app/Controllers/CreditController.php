<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\CreditPayment;
use App\Models\Customer;

final class CreditController extends Controller
{
    /** GET credit/index — all customers with outstanding balances. */
    public function index(): void
    {
        $m = new CreditPayment();

        $this->render('credit/index', [
            'debtors' => $m->debtors(),
            'total'   => $m->totalOutstanding(),
        ], 'Credit (دين)');
    }

    /** GET credit/customer — one customer's open invoices + payment form. */
    public function customer(): void
    {
        $customer = (new Customer())->find($this->queryInt('id'));
        if ($customer === null) {
            Flash::set('danger', 'Customer not found.');
            redirect('credit/index');
        }

        $m = new CreditPayment();
        $id = (int) $customer['id'];

        $this->render('credit/customer', [
            'customer' => $customer,
            'invoices' => $m->outstandingInvoices($id),
            'totals'   => $m->customerTotals($id),
            'history'  => $m->historyForCustomer($id),
        ], 'Credit — ' . $customer['name']);
    }

    /** POST credit/pay — record a full or partial payment on an invoice. */
    public function pay(): void
    {
        $this->requireValidPost();

        $v = Validator::make($_POST, [
            'sale_id' => 'required|int',
            'amount'  => 'required|numeric|min:0.01',
            'method'  => 'required|in:cash,card',
            'notes'   => 'maxlen:255',
        ]);
        if ($v->fails()) {
            $this->failBack($v->errors());
        }

        $customerId = $this->inputInt('customer_id');

        try {
            $balance = (new CreditPayment())->recordPayment(
                $this->inputInt('sale_id'),
                $this->inputFloat('amount'),
                $this->input('method'),
                $this->input('notes'),
                Auth::id()
            );
            Flash::set(
                'success',
                'Payment of ' . money($this->inputFloat('amount')) . ' recorded.'
                . ($balance <= 0.004 ? ' Invoice fully paid ✓' : ' Remaining: ' . money($balance))
            );
        } catch (\RuntimeException $ex) {
            Flash::set('danger', $ex->getMessage());
        }

        if ($customerId > 0) {
            redirect('credit/customer', ['id' => $customerId]);
        }
        redirect('credit/index');
    }
}
