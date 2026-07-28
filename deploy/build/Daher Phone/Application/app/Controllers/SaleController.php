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
        if (!in_array($method, ['cash', 'card', 'credit'], true)) {
            $method = 'cash';
        }

        $customerId = $this->inputInt('customer_id');
        if ($customerId > 0 && (new Customer())->find($customerId) === null) {
            $customerId = 0;
        }

        // A debt needs a debtor: credit sales are impossible without a customer.
        if ($method === 'credit' && $customerId < 1) {
            $this->json([
                'ok'    => false,
                'error' => 'Credit sales must have a customer — please select the customer first.',
            ], 422);
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

    /**
     * GET sales/search-invoices-json — live invoice picker for Returns/Refunds.
     * ?q=…&sort=date_desc|date_asc|invoice|customer|total&page=N
     */
    public function searchInvoicesJson(): void
    {
        $sort = $this->queryString('sort', 'date_desc');
        if (!in_array($sort, ['date_desc', 'date_asc', 'invoice', 'customer', 'total'], true)) {
            $sort = 'date_desc';
        }

        $pg = (new Sale())->searchForPicker(
            mb_substr($this->queryString('q'), 0, 80),
            $sort,
            $this->queryInt('page', 1)
        );

        $rows = array_map(static function (array $r): array {
            $paidMeta = paid_status_meta((float) $r['total'], (float) $r['paid_amount']);
            // Refundable = money actually received (paid minus return credits) − refunds.
            $refundable = round(
                (float) $r['paid_amount'] - (float) $r['return_credits'] - (float) $r['refunded'],
                2
            );

            return [
                'id'              => (int) $r['id'],
                'invoice_no'      => $r['invoice_no'],
                'date'            => fmt_date($r['created_at'], true),
                'customer'        => $r['customer_name'],
                'phone'           => $r['phone'] ?? '',
                'total_fmt'       => money($r['total']),
                'paid_fmt'        => money($r['paid_amount']),
                'refundable'      => max(0.0, $refundable),
                'refundable_fmt'  => money(max(0, $refundable)),
                'payment'         => payment_label((string) $r['payment_method']),
                'pay_label'       => $paidMeta['label'],
                'pay_color'       => $paidMeta['color'],
                'has_returnable'  => (bool) $r['has_returnable'],
                'matched_product' => $r['matched_product'] ?? null,
            ];
        }, $pg['rows']);

        $this->json([
            'ok'    => true,
            'rows'  => $rows,
            'page'  => $pg['page'],
            'pages' => $pg['pages'],
            'total' => $pg['total'],
        ]);
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

        $saleId = (int) $sale['id'];
        $refunded = $m->refundedTotal($saleId);
        $moneyReceived = (new \App\Models\Finance())->moneyReceived($saleId);

        $this->render('sales/show', [
            'sale'          => $sale,
            'items'         => $m->items($saleId),
            'payments'      => (new \App\Models\CreditPayment())->paymentsForSale($saleId),
            'returns'       => (new \App\Models\ProductReturn())->forSale($saleId),
            'refunds'       => (new \App\Models\Refund())->forSale($saleId),
            'refunded'      => $refunded,
            'moneyReceived' => $moneyReceived,
            'returnCredit'  => round((float) $sale['paid_amount'] - $moneyReceived, 2),
            'refundable'    => round($moneyReceived - $refunded, 2),
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
