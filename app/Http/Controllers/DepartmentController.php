<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class DepartmentController extends StaffLookupController
{
    protected function model(): string
    {
        return Department::class;
    }

    protected function permission(): string
    {
        return 'departments.manage';
    }

    protected function column(): string
    {
        return 'department_id';
    }

    protected function otherRelation(): string
    {
        return 'designation';
    }

    protected function folder(): string
    {
        return 'Departments';
    }

    protected function url(): string
    {
        return 'departments';
    }

    protected function noun(): string
    {
        return 'department';
    }

    public function index(Request $request): Response
    {
        return $this->renderIndex($request);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        return $this->save($request->validated());
    }

    public function show(Request $request, Department $department): Response
    {
        return $this->renderShow($request, $department);
    }

    public function update(StoreDepartmentRequest $request, Department $department): RedirectResponse
    {
        return $this->save($request->validated(), $department);
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        return $this->remove($request, $department);
    }
}