<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDesignationRequest;
use App\Models\Designation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class DesignationController extends StaffLookupController
{
    protected function model(): string
    {
        return Designation::class;
    }

    protected function permission(): string
    {
        return 'designations.manage';
    }

    protected function column(): string
    {
        return 'designation_id';
    }

    protected function otherRelation(): string
    {
        return 'department';
    }

    protected function folder(): string
    {
        return 'Designations';
    }

    protected function url(): string
    {
        return 'designations';
    }

    protected function noun(): string
    {
        return 'designation';
    }

    public function index(Request $request): Response
    {
        return $this->renderIndex($request);
    }

    public function store(StoreDesignationRequest $request): RedirectResponse
    {
        return $this->save($request->validated());
    }

    public function show(Request $request, Designation $designation): Response
    {
        return $this->renderShow($request, $designation);
    }

    public function update(StoreDesignationRequest $request, Designation $designation): RedirectResponse
    {
        return $this->save($request->validated(), $designation);
    }

    public function destroy(Request $request, Designation $designation): RedirectResponse
    {
        return $this->remove($request, $designation);
    }
}