<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Customer;
use App\Models\Repair;

final class RepairController extends Controller
{
    private const DEVICE_TYPES = ['Phone', 'Laptop', 'Desktop PC', 'Tablet', 'Game console', 'Other'];

    public function index(): void
    {
        $filters = [
            'q'      => $this->queryString('q'),
            'status' => $this->queryString('status'),
            'from'   => $this->queryString('from'),
            'to'     => $this->queryString('to'),
        ];

        $pg = (new Repair())->search($filters, $this->queryInt('page', 1));

        $this->render('repairs/index', ['pg' => $pg, 'filters' => $filters], 'Repairs');
    }

    public function create(): void
    {
        $preselected = null;
        $customerId = $this->queryInt('customer_id');
        if ($customerId > 0) {
            $preselected = (new Customer())->find($customerId);
        }

        $this->render('repairs/form', [
            'customers'   => (new Customer())->all(),
            'preselected' => $preselected,
            'deviceTypes' => self::DEVICE_TYPES,
        ], 'New repair ticket');
    }

    public function store(): void
    {
        $this->requireValidPost();

        $v = Validator::make($_POST, [
            'customer_id' => 'required|int',
            'device_type' => 'required|maxlen:50',
            'brand'       => 'maxlen:50',
            'model'       => 'maxlen:80',
            'serial_no'   => 'maxlen:80',
            'problem'     => 'required|maxlen:5000',
            'labor_cost'  => 'numeric|min:0',
            'deposit'     => 'numeric|min:0',
        ]);
        if ($v->fails()) {
            $this->failBack($v->errors());
        }

        if ((new Customer())->find($this->inputInt('customer_id')) === null) {
            $this->failBack(['customer_id' => 'Please choose a valid customer.']);
        }

        $id = (new Repair())->create([
            'customer_id' => $this->inputInt('customer_id'),
            'device_type' => $this->input('device_type'),
            'brand'       => $this->input('brand'),
            'model'       => $this->input('model'),
            'serial_no'   => $this->input('serial_no'),
            'problem'     => $this->input('problem'),
            'labor_cost'  => $this->inputFloat('labor_cost'),
            'deposit'     => $this->inputFloat('deposit'),
        ], Auth::id());

        Flash::set('success', 'Repair ticket created.');
        redirect('repairs/show', ['id' => $id]);
    }

    public function show(): void
    {
        $m = new Repair();
        $repair = $m->find($this->queryInt('id'));
        if ($repair === null) {
            Flash::set('danger', 'Repair ticket not found.');
            redirect('repairs/index');
        }

        $this->render('repairs/show', [
            'repair'      => $repair,
            'parts'       => $m->parts((int) $repair['id']),
            'history'     => $m->statusHistory((int) $repair['id']),
            'deviceTypes' => self::DEVICE_TYPES,
        ], 'Ticket ' . $repair['ticket_no']);
    }

    /** POST repairs/update — header/details fields from the ticket page. */
    public function update(): void
    {
        $this->requireValidPost();
        $id = $this->inputInt('id');

        $m = new Repair();
        if ($m->find($id) === null) {
            Flash::set('danger', 'Repair ticket not found.');
            redirect('repairs/index');
        }

        $v = Validator::make($_POST, [
            'device_type' => 'required|maxlen:50',
            'brand'       => 'maxlen:50',
            'model'       => 'maxlen:80',
            'serial_no'   => 'maxlen:80',
            'problem'     => 'required|maxlen:5000',
            'tech_notes'  => 'maxlen:5000',
            'labor_cost'  => 'numeric|min:0',
        ]);
        if ($v->fails()) {
            $this->failBack($v->errors());
        }

        $m->updateDetails($id, [
            'device_type' => $this->input('device_type'),
            'brand'       => $this->input('brand'),
            'model'       => $this->input('model'),
            'serial_no'   => $this->input('serial_no'),
            'problem'     => $this->input('problem'),
            'tech_notes'  => $this->input('tech_notes'),
            'labor_cost'  => $this->inputFloat('labor_cost'),
        ]);

        Flash::set('success', 'Ticket updated.');
        redirect('repairs/show', ['id' => $id]);
    }

    /** POST repairs/set-status */
    public function setStatus(): void
    {
        $this->requireValidPost();
        $id = $this->inputInt('id');

        try {
            (new Repair())->setStatus($id, $this->input('status'), $this->input('note'), Auth::id());
            Flash::set('success', 'Status updated.');
        } catch (\RuntimeException $ex) {
            Flash::set('danger', $ex->getMessage());
        }

        redirect('repairs/show', ['id' => $id]);
    }

    /** POST repairs/add-part — from stock (product_id) or external (free text). */
    public function addPart(): void
    {
        $this->requireValidPost();
        $id = $this->inputInt('id');
        $productId = $this->inputInt('product_id');
        $qty = max(1, $this->inputInt('quantity', 1));

        if ($productId < 1) {
            $v = Validator::make($_POST, [
                'part_name'  => 'required|maxlen:150',
                'unit_cost'  => 'numeric|min:0',
                'unit_price' => 'required|numeric|min:0',
            ]);
            if ($v->fails()) {
                $this->failBack($v->errors());
            }
        }

        try {
            (new Repair())->addPart(
                $id,
                $productId > 0 ? $productId : null,
                $this->input('part_name'),
                $qty,
                $this->inputFloat('unit_cost'),
                $this->inputFloat('unit_price'),
                Auth::id()
            );
            Flash::set('success', 'Part added to the ticket.');
        } catch (\RuntimeException $ex) {
            Flash::set('danger', $ex->getMessage());
        }

        redirect('repairs/show', ['id' => $id]);
    }

    /** POST repairs/remove-part */
    public function removePart(): void
    {
        $this->requireValidPost();
        $repairId = $this->inputInt('repair_id');

        try {
            (new Repair())->removePart($this->inputInt('part_id'), Auth::id());
            Flash::set('success', 'Part removed and returned to stock where applicable.');
        } catch (\RuntimeException $ex) {
            Flash::set('danger', $ex->getMessage());
        }

        redirect('repairs/show', ['id' => $repairId]);
    }

    /** POST repairs/add-payment */
    public function addPayment(): void
    {
        $this->requireValidPost();
        $id = $this->inputInt('id');
        $amount = $this->inputFloat('amount');

        $m = new Repair();
        $repair = $m->find($id);
        if ($repair === null) {
            Flash::set('danger', 'Repair ticket not found.');
            redirect('repairs/index');
        }
        if ($amount <= 0) {
            $this->failBack(['amount' => 'Enter a payment amount greater than zero.']);
        }

        $balance = (float) $repair['total_cost'] - (float) $repair['paid_amount'];
        if ($amount > $balance + 0.005) {
            $this->failBack(['amount' => 'Payment exceeds the remaining balance of ' . money($balance) . '.']);
        }

        $m->recordPayment($id, $amount);
        Flash::set('success', 'Payment of ' . money($amount) . ' recorded.');
        redirect('repairs/show', ['id' => $id]);
    }

    /** GET repairs/print — printable customer receipt (bare layout). */
    public function print(): void
    {
        $m = new Repair();
        $repair = $m->find($this->queryInt('id'));
        if ($repair === null) {
            Flash::set('danger', 'Repair ticket not found.');
            redirect('repairs/index');
        }

        $this->renderBare('repairs/print', [
            'repair' => $repair,
            'parts'  => $m->parts((int) $repair['id']),
        ], 'Ticket ' . $repair['ticket_no']);
    }
}
