<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Refund;
use App\Models\Sale;

final class RefundController extends Controller
{
    /** GET refunds/index */
    public function index(): void
    {
        $filters = [
            'q'    => $this->queryString('q'),
            'from' => $this->queryString('from'),
            'to'   => $this->queryString('to'),
        ];

        $pg = (new Refund())->search($filters, $this->queryInt('page', 1));

        $this->render('refunds/index', ['pg' => $pg, 'filters' => $filters], 'Refunds');
    }

    /**
     * GET refunds/create — find the invoice, then enter amount/reason/method.
     * Accepts ?sale_id=N or ?invoice=INV-000123.
     */
    public function create(): void
    {
        $saleModel = new Sale();
        $sale = null;

        if ($this->queryInt('sale_id') > 0) {
            $sale = $saleModel->find($this->queryInt('sale_id'));
        } elseif ($this->queryString('invoice') !== '') {
            $sale = $saleModel->findByInvoiceNo($this->queryString('invoice'));
            if ($sale === null) {
                Flash::set('warning', 'No invoice found with number "' . $this->queryString('invoice') . '".');
            }
        }

        $refundable = 0.0;
        if ($sale !== null) {
            if ($sale['status'] !== 'completed') {
                Flash::set('warning', 'Invoice ' . $sale['invoice_no'] . ' is cancelled — refunds are not possible.');
                $sale = null;
            } else {
                $refundable = round(
                    (float) $sale['paid_amount'] - $saleModel->refundedTotal((int) $sale['id']),
                    2
                );
            }
        }

        $this->render('refunds/create', [
            'sale'       => $sale,
            'refundable' => $refundable,
        ], 'New refund');
    }

    /** POST refunds/store */
    public function store(): void
    {
        $this->requireValidPost();

        $v = Validator::make($_POST, [
            'sale_id' => 'required|int',
            'amount'  => 'required|numeric|min:0.01',
            'reason'  => 'required|maxlen:255',
            'method'  => 'required|in:cash,card',
        ]);
        if ($v->fails()) {
            $this->failBack($v->errors());
        }

        try {
            $id = (new Refund())->create(
                $this->inputInt('sale_id'),
                $this->inputFloat('amount'),
                $this->input('reason'),
                $this->input('method'),
                Auth::id()
            );
            Flash::set('success', 'Refund recorded.');
            redirect('refunds/show', ['id' => $id]);
        } catch (\RuntimeException $ex) {
            Flash::set('danger', $ex->getMessage());
            redirect('refunds/create', ['sale_id' => $this->inputInt('sale_id')]);
        }
    }

    /** GET refunds/show */
    public function show(): void
    {
        $refund = (new Refund())->find($this->queryInt('id'));
        if ($refund === null) {
            Flash::set('danger', 'Refund not found.');
            redirect('refunds/index');
        }

        $this->render('refunds/show', ['refund' => $refund], $refund['refund_no']);
    }

    /** GET refunds/print — printable refund receipt (bare layout). */
    public function print(): void
    {
        $refund = (new Refund())->find($this->queryInt('id'));
        if ($refund === null) {
            Flash::set('danger', 'Refund not found.');
            redirect('refunds/index');
        }

        $this->renderBare('refunds/print', ['refund' => $refund], $refund['refund_no']);
    }
}
