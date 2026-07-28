<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Category;

final class CategoryController extends Controller
{
    public function index(): void
    {
        $this->render('categories/index', [
            'categories' => (new Category())->allWithCounts(),
        ], 'Categories');
    }

    public function store(): void
    {
        $this->requireValidPost();

        $v = Validator::make($_POST, [
            'name'        => 'required|maxlen:100',
            'description' => 'maxlen:255',
        ]);
        if ($v->fails()) {
            $this->failBack($v->errors());
        }

        $m = new Category();
        if ($m->nameExists($this->input('name'))) {
            $this->failBack(['name' => 'A category with that name already exists.']);
        }

        $m->create($this->input('name'), $this->input('description'));
        Flash::set('success', 'Category "' . $this->input('name') . '" added.');
        redirect('categories/index');
    }

    public function update(): void
    {
        $this->requireValidPost();
        $id = $this->inputInt('id');

        $m = new Category();
        if ($id < 1 || $m->find($id) === null) {
            Flash::set('danger', 'Category not found.');
            redirect('categories/index');
        }

        $v = Validator::make($_POST, [
            'name'        => 'required|maxlen:100',
            'description' => 'maxlen:255',
        ]);
        if ($v->fails()) {
            $this->failBack($v->errors());
        }
        if ($m->nameExists($this->input('name'), $id)) {
            $this->failBack(['name' => 'Another category already uses that name.']);
        }

        $m->update($id, $this->input('name'), $this->input('description'));
        Flash::set('success', 'Category updated.');
        redirect('categories/index');
    }

    public function delete(): void
    {
        $this->requireValidPost();
        $id = $this->inputInt('id');

        $m = new Category();
        $category = $m->find($id);
        if ($category === null) {
            Flash::set('danger', 'Category not found.');
            redirect('categories/index');
        }

        $inUse = $m->productCount($id);
        if ($inUse > 0) {
            Flash::set(
                'warning',
                "\"{$category['name']}\" still has {$inUse} product(s). "
                . 'Move or delete those products first.'
            );
            redirect('categories/index');
        }

        $m->delete($id);
        Flash::set('success', 'Category "' . $category['name'] . '" deleted.');
        redirect('categories/index');
    }
}
