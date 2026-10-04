<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\StoreDesignationRequest;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Http\RedirectResponse;

class OrganizationLookupController extends Controller
{
    public function storeDepartment(StoreDepartmentRequest $request): RedirectResponse
    {
        Department::query()->create($request->validated());

        return back()->with('status', 'Department created.');
    }

    public function storeDesignation(StoreDesignationRequest $request): RedirectResponse
    {
        Designation::query()->create($request->validated());

        return back()->with('status', 'Designation created.');
    }
}
