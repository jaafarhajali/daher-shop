<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\ProductReturn;
use App\Models\Sale;

final class ReturnController extends Controller
{
    /** GET returns/index — list of processed returns. */
    public function index(): void
    {
        $filters = [
            'q'    => $this->queryString('q'),
            'from' => $this->queryString('from'),
            'to'   => $this->queryString('to'),
        ];

        $pg = (new ProductReturn())->search($filters, $this->queryInt('page', 1));

        $this->render('returns/index', ['pg' => $pg, 'filters' => $filters], 'Returns');
    }

    /**
     * GET returns/create — step 1: find the invoice; step 2: pick items.
     * Accepts ?sale_id=N (from the invoice page) or ?invoice=INV-000123.
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

        $items = [];
        if ($sale !== null && $sale['status'] === 'completed') {
            $items = $saleModel->itemsWithReturnable((int) $sale['id']);
        } elseif ($sale !== null) {
            Flash::set('warning', 'Invoice ' . $sale['invoice_no'] . ' is cancelled — returns are not possible.');
            $sale = null;
        }

        $this->render('returns/create', [
            'sale'       => $sale,
            'items'      => $items,
            'pageScript' => $sale === null ? 'invoice-picker' : null,
        ], 'New return');
    }

    /** POST returns/store */
    public function store(): void
    {
        $this->requireValidPost();

        $v = Validator::make($_POST, [
            'sale_id' => 'required|int',
            'reason'  => 'required|maxlen:255',
        ]);
        if ($v->fails()) {
            $this->failBack($v->errors());
        }

        // quantities arrive as qty[<sale_item_id>] = n
        $items = [];
        foreach ((array) ($_POST['qty'] ?? []) as $saleItemId => $qty) {
            if (is_numeric($saleItemId) && is_numeric($qty) && (int) $qty > 0) {
                $items[] = ['sale_item_id' => (int) $saleItemId, 'quantity' => (int) $qty];
            }
        }

        try {
            $id = (new ProductReturn())->create(
                $this->inputInt('sale_id'),
                $items,
                $this->input('reason'),
                Auth::id()
            );
            Flash::set('success', 'Return processed — items are back in stock.');
            redirect('returns/show', ['id' => $id]);
        } catch (\RuntimeException $ex) {
            Flash::set('danger', $ex->getMessage());
            redirect('returns/create', ['sale_id' => $this->inputInt('sale_id')]);
        }
    }

    /** GET returns/show */
    public function show(): void
    {
        $m = new ProductReturn();
        $return = $m->find($this->queryInt('id'));
        if ($return === null) {
            Flash::set('danger', 'Return not found.');
            redirect('returns/index');
        }

        $this->render('returns/show', [
            'return' => $return,
            'items'  => $m->items((int) $return['id']),
        ], $return['return_no']);
    }
}
