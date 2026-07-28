<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Expense;

final class ExpenseController extends Controller
{
    public function index(): void
    {
        $filters = [
            'q'        => $this->queryString('q'),
            'category' => $this->queryString('category'),
            'from'     => $this->queryString('from'),
            'to'       => $this->queryString('to'),
        ];

        $m = new Expense();

        $this->render('expenses/index', [
            'pg'          => $m->search($filters, $this->queryInt('page', 1)),
            'filters'     => $filters,
            'total'       => $m->totalFor($filters),
            'categories'  => array_values(array_unique(array_merge(Expense::CATEGORIES, $m->usedCategories()))),
        ], 'Expenses');
    }

    public function store(): void
    {
        $this->requireValidPost();
        $data = $this->validated();

        (new Expense())->create($data, Auth::id());
        Flash::set('success', 'Expense recorded.');
        redirect('expenses/index');
    }

    public function update(): void
    {
        $this->requireValidPost();
        $id = $this->inputInt('id');

        $m = new Expense();
        if ($m->find($id) === null) {
            Flash::set('danger', 'Expense not found.');
            redirect('expenses/index');
        }

        $m->update($id, $this->validated());
        Flash::set('success', 'Expense updated.');
        redirect('expenses/index');
    }

    public function delete(): void
    {
        $this->requireValidPost();
        $id = $this->inputInt('id');

        $m = new Expense();
        if ($m->find($id) === null) {
            Flash::set('danger', 'Expense not found.');
        } else {
            $m->delete($id);
            Flash::set('success', 'Expense deleted.');
        }
        redirect('expenses/index');
    }

    private function validated(): array
    {
        $v = Validator::make($_POST, [
            'name'         => 'required|maxlen:150',
            'category'     => 'required|maxlen:50',
            'amount'       => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'notes'        => 'maxlen:255',
        ]);
        if ($v->fails()) {
            $this->failBack($v->errors());
        }

        return [
            'name'         => $this->input('name'),
            'category'     => $this->input('category'),
            'amount'       => $this->inputFloat('amount'),
            'expense_date' => $this->input('expense_date'),
            'notes'        => $this->input('notes'),
        ];
    }
}
