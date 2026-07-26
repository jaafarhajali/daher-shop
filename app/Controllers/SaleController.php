<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Models\Customer;
use App\Models\Sale;

final class SaleController extends Controller
{
    /** GET sales/pos — the point-of-sale screen. */
    public function pos(): void
    {
        $preselected = null;
        $customerId = $this->queryInt('customer_id');
        if ($customerId > 0) {
            $c = (new Customer())->find($customerId);
            if ($c !== null) {
                $preselected = ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone']];
            }
        }

        $this->render('sales/pos', [
            'preselectedCustomer' => $preselected,
            'pageScript'          => 'pos',
            'inlineScript'        => 'window.POS = ' . json_encode([
                'currency' => setting('currency_symbol', '$'),
                'customer' => $preselected,
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';',
        ], 'Point of Sale');
    }

    /** POST sales/checkout — AJAX endpoint that finalizes the cart. */
    public function checkout(): void
    {
        $this->requireValidPostJson();

        $itemsRaw = json_decode((string) ($_POST['items'] ?? '[]'), true);
        if (!is_array($itemsRaw) || $itemsRaw === []) {
            $this->json(['ok' => false, 'error' => 'The cart is empty.'], 422);
        }
        if (count($itemsRaw) > 100) {
            $this->json(['ok' => false, 'error' => 'Too many lines in one sale.'], 422);
        }

        $items = [];
        foreach ($itemsRaw as $row) {
            if (!is_array($row) || !isset($row['id'], $row['qty'], $row['price'])
                || !is_numeric($row['id']) || !is_numeric($row['qty']) || !is_numeric($row['price'])) {
                $this->json(['ok' => false, 'error' => 'Invalid cart data. Refresh and try again.'], 422);
            }
            $items[] = [
                'product_id' => (int) $row['id'],
                'quantity'   => (int) $row['qty'],
                'unit_price' => (float) $row['price'],
            ];
        }

        $method = $this->input('payment_method', 'cash');
        if (!in_array($method, ['cash', 'card', 'bank_transfer', 'other'], true)) {
            $method = 'cash';
        }

        $customerId = $this->inputInt('customer_id');
        if ($customerId > 0 && (new Customer())->find($customerId) === null) {
            $customerId = 0;
        }

        try {
            $saleId = (new Sale())->createSale(
                [
                    'customer_id'    => $customerId > 0 ? $customerId : null,
                    'discount'       => $this->inputFloat('discount'),
                    'payment_method' => $method,
                    'notes'          => mb_substr($this->input('notes'), 0, 255),
                ],
                $items,
                Auth::id()
            );
        } catch (\RuntimeException $ex) {
            $this->json(['ok' => false, 'error' => $ex->getMessage()], 422);
        }

        Flash::set('success', 'Sale completed.');
        $this->json(['ok' => true, 'sale_id' => $saleId]);
    }

    /** GET sales/index — sales history. */
    public function index(): void
    {
        $filters = [
            'q'      => $this->queryString('q'),
            'from'   => $this->queryString('from'),
            'to'     => $this->queryString('to'),
            'status' => $this->queryString('status'),
            'method' => $this->queryString('method'),
        ];

        $pg = (new Sale())->search($filters, $this->queryInt('page', 1));

        $this->render('sales/index', ['pg' => $pg, 'filters' => $filters], 'Sales');
    }

    /** GET sales/show — invoice detail + print. */
    public function show(): void
    {
        $m = new Sale();
        $sale = $m->find($this->queryInt('id'));
        if ($sale === null) {
            Flash::set('danger', 'Sale not found.');
            redirect('sales/index');
        }

        $this->render('sales/show', [
            'sale'  => $sale,
            'items' => $m->items((int) $sale['id']),
        ], $sale['invoice_no']);
    }

    /** POST sales/cancel — void a sale and restock its items. */
    public function cancel(): void
    {
        $this->requireValidPost();
        $id = $this->inputInt('id');

        try {
            (new Sale())->cancel($id, Auth::id());
            Flash::set('success', 'Sale cancelled and items returned to stock.');
        } catch (\RuntimeException $ex) {
            Flash::set('danger', $ex->getMessage());
        }

        redirect('sales/show', ['id' => $id]);
    }
}
