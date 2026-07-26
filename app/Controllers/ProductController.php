<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Category;
use App\Models\Product;

final class ProductController extends Controller
{
    public function index(): void
    {
        $filters = [
            'q'           => $this->queryString('q'),
            'category_id' => $this->queryInt('category_id'),
            'stock'       => $this->queryString('stock'),
            'price'       => $this->queryString('price'),
            'sort'        => $this->queryString('sort', 'name'),
            'dir'         => $this->queryString('dir', 'asc'),
        ];

        $pg = (new Product())->search($filters, $this->queryInt('page', 1));

        $this->render('products/index', [
            'pg'         => $pg,
            'filters'    => $filters,
            'categories' => (new Category())->all(),
        ], 'Products');
    }

    /** Shortcut route used by dashboard/sidebar links. */
    public function lowStock(): void
    {
        redirect('products/index', ['stock' => 'low']);
    }

    public function create(): void
    {
        $this->render('products/form', [
            'product'    => null,
            'categories' => (new Category())->all(),
        ], 'Add product');
    }

    public function store(): void
    {
        $this->requireValidPost();
        $data = $this->validated();

        $m = new Product();
        if ($data['barcode'] !== '' && $m->barcodeExists($data['barcode'])) {
            $this->failBack(['barcode' => 'That barcode is already assigned to another product.']);
        }

        $id = $m->create($data, Auth::id());
        Flash::set('success', 'Product "' . $data['name'] . '" added.');
        redirect('products/edit', ['id' => $id]);
    }

    public function edit(): void
    {
        $product = (new Product())->find($this->queryInt('id'));
        if ($product === null || (int) $product['is_active'] !== 1) {
            Flash::set('danger', 'Product not found.');
            redirect('products/index');
        }

        $this->render('products/form', [
            'product'    => $product,
            'categories' => (new Category())->all(),
            'movements'  => (new Product())->movements((int) $product['id']),
        ], 'Edit product');
    }

    public function update(): void
    {
        $this->requireValidPost();
        $id = $this->inputInt('id');

        $m = new Product();
        if ($m->find($id) === null) {
            Flash::set('danger', 'Product not found.');
            redirect('products/index');
        }

        $data = $this->validated();
        if ($data['barcode'] !== '' && $m->barcodeExists($data['barcode'], $id)) {
            $this->failBack(['barcode' => 'That barcode is already assigned to another product.']);
        }

        $m->update($id, $data);
        Flash::set('success', 'Product updated.');
        redirect('products/edit', ['id' => $id]);
    }

    /** POST products/adjust-stock — manual restock / correction with a reason. */
    public function adjustStock(): void
    {
        $this->requireValidPost();
        $id = $this->inputInt('id');
        $change = $this->inputInt('change');
        $note = $this->input('note');

        if ($change === 0) {
            $this->failBack(['change' => 'Enter a positive or negative quantity, e.g. 5 or -2.']);
        }

        $m = new Product();
        if ($m->find($id) === null) {
            Flash::set('danger', 'Product not found.');
            redirect('products/index');
        }

        try {
            $m->adjustStock(
                $id,
                $change,
                $change > 0 ? 'restock' : 'adjustment',
                null,
                $note !== '' ? $note : null,
                Auth::id()
            );
            Flash::set('success', 'Stock updated (' . ($change > 0 ? '+' : '') . $change . ').');
        } catch (\RuntimeException $ex) {
            Flash::set('danger', $ex->getMessage());
        }

        redirect('products/edit', ['id' => $id]);
    }

    public function delete(): void
    {
        $this->requireValidPost();
        $id = $this->inputInt('id');

        $m = new Product();
        $product = $m->find($id);
        if ($product === null) {
            Flash::set('danger', 'Product not found.');
            redirect('products/index');
        }

        if ($m->hasHistory($id)) {
            // Referenced by sales/repairs: keep history intact, hide the product.
            $m->deactivate($id);
            Flash::set(
                'success',
                '"' . $product['name'] . '" was archived — it appears on past invoices, '
                . 'so its history is kept but it can no longer be sold.'
            );
        } else {
            $m->hardDelete($id);
            Flash::set('success', '"' . $product['name'] . '" deleted.');
        }

        redirect('products/index');
    }

    /** GET products/search-json — used by the POS live search. */
    public function searchJson(): void
    {
        $q = $this->queryString('q');
        if (mb_strlen($q) < 1) {
            $this->json(['ok' => true, 'items' => []]);
        }

        $m = new Product();

        // Scanner-friendly: exact barcode match wins.
        $exact = $m->findByBarcode($q);
        $items = $exact !== null ? [$exact] : $m->posSearch($q);

        $this->json([
            'ok'    => true,
            'exact' => $exact !== null,
            'items' => array_map(static fn (array $p): array => [
                'id'       => (int) $p['id'],
                'name'     => $p['name'],
                'barcode'  => $p['barcode'],
                // null = no selling price yet — the POS blocks these with a message
                'price'    => $p['selling_price'] === null ? null : (float) $p['selling_price'],
                'stock'    => (int) $p['quantity'],
                'warranty' => (int) ($p['warranty_days'] ?? 0),
            ], $items),
        ]);
    }

    /** Shared validation for store/update. @return array<string, mixed> */
    private function validated(): array
    {
        // Selling price is OPTIONAL (empty = "no price yet"); cost stays mandatory.
        $v = Validator::make($_POST, [
            'category_id'   => 'required|int',
            'name'          => 'required|maxlen:150',
            'description'   => 'maxlen:2000',
            'barcode'       => 'maxlen:64',
            'cost_price'    => 'required|numeric|min:0',
            'selling_price' => 'numeric|min:0',
            'quantity'      => 'int|min:0',
            'min_stock'     => 'required|int|min:0',
            'warranty_days' => 'int|min:0',
        ]);
        if ($v->fails()) {
            $this->failBack($v->errors());
        }

        if ((new Category())->find($this->inputInt('category_id')) === null) {
            $this->failBack(['category_id' => 'Please choose a valid category.']);
        }

        // Warranty: empty or 0 = no warranty; otherwise a positive whole number of days.
        $warrantyRaw = $this->input('warranty_days');
        $warrantyDays = $warrantyRaw === '' ? 0 : $this->inputInt('warranty_days', -1);
        if ($warrantyDays < 0 || ($warrantyRaw !== '' && $warrantyRaw !== '0' && $warrantyDays < 1)) {
            $this->failBack(['warranty_days' => 'Warranty must be a positive number of days (or empty for no warranty).']);
        }

        return [
            'category_id'   => $this->inputInt('category_id'),
            'name'          => $this->input('name'),
            'description'   => $this->input('description'),
            'barcode'       => $this->input('barcode'),
            'cost_price'    => $this->inputFloat('cost_price'),
            'selling_price' => $this->input('selling_price') === '' ? null : $this->inputFloat('selling_price'),
            'quantity'      => max(0, $this->inputInt('quantity')),
            'min_stock'     => max(0, $this->inputInt('min_stock', (int) setting('default_min_stock', '3'))),
            'warranty_days' => $warrantyDays,
        ];
    }
}
