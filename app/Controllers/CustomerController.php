<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Customer;

final class CustomerController extends Controller
{
    public function index(): void
    {
        $q = $this->queryString('q');
        $pg = (new Customer())->search($q, $this->queryInt('page', 1));

        $this->render('customers/index', ['pg' => $pg, 'q' => $q], 'Customers');
    }

    public function show(): void
    {
        $m = new Customer();
        $customer = $m->find($this->queryInt('id'));
        if ($customer === null) {
            Flash::set('danger', 'Customer not found.');
            redirect('customers/index');
        }

        $id = (int) $customer['id'];
        $this->render('customers/show', [
            'customer'     => $customer,
            'purchases'    => $m->purchaseHistory($id),
            'repairs'      => $m->repairHistory($id),
            'lifetime'     => $m->lifetimeValue($id),
            'creditTotals' => (new \App\Models\CreditPayment())->customerTotals($id),
        ], $customer['name']);
    }

    public function create(): void
    {
        $this->render('customers/form', ['customer' => null], 'Add customer');
    }

    public function store(): void
    {
        $this->requireValidPost();
        $data = $this->validated();

        $id = (new Customer())->create($data);
        Flash::set('success', 'Customer "' . $data['name'] . '" added.');

        // POS hand-off: ?return=pos sends the new customer straight back to the sale.
        if ($this->input('return') === 'pos') {
            redirect('sales/pos', ['customer_id' => $id]);
        }
        redirect('customers/show', ['id' => $id]);
    }

    public function edit(): void
    {
        $customer = (new Customer())->find($this->queryInt('id'));
        if ($customer === null) {
            Flash::set('danger', 'Customer not found.');
            redirect('customers/index');
        }

        $this->render('customers/form', ['customer' => $customer], 'Edit customer');
    }

    public function update(): void
    {
        $this->requireValidPost();
        $id = $this->inputInt('id');

        $m = new Customer();
        if ($m->find($id) === null) {
            Flash::set('danger', 'Customer not found.');
            redirect('customers/index');
        }

        $m->update($id, $this->validated());
        Flash::set('success', 'Customer updated.');
        redirect('customers/show', ['id' => $id]);
    }

    public function delete(): void
    {
        $this->requireValidPost();
        $id = $this->inputInt('id');

        $m = new Customer();
        $customer = $m->find($id);
        if ($customer === null) {
            Flash::set('danger', 'Customer not found.');
            redirect('customers/index');
        }

        if (!$m->delete($id)) {
            Flash::set(
                'warning',
                '"' . $customer['name'] . '" has repair tickets on file and cannot be deleted. '
                . 'Past sales would survive, but repair history must keep its customer.'
            );
            redirect('customers/show', ['id' => $id]);
        }

        Flash::set('success', 'Customer "' . $customer['name'] . '" deleted. Their past sales are kept as walk-in sales.');
        redirect('customers/index');
    }

    /** GET customers/search-json — POS customer picker. */
    public function searchJson(): void
    {
        $q = $this->queryString('q');
        $items = $q === '' ? [] : (new Customer())->quickSearch($q);

        $this->json([
            'ok' => true,
            'items' => array_map(static fn (array $c): array => [
                'id'    => (int) $c['id'],
                'name'  => $c['name'],
                'phone' => $c['phone'],
            ], $items),
        ]);
    }

    private function validated(): array
    {
        $v = Validator::make($_POST, [
            'name'    => 'required|maxlen:100',
            'phone'   => 'maxlen:30',
            'email'   => 'email|maxlen:150',
            'address' => 'maxlen:255',
            'notes'   => 'maxlen:2000',
        ]);
        if ($v->fails()) {
            $this->failBack($v->errors());
        }

        return [
            'name'    => $this->input('name'),
            'phone'   => $this->input('phone'),
            'email'   => $this->input('email'),
            'address' => $this->input('address'),
            'notes'   => $this->input('notes'),
        ];
    }
}
