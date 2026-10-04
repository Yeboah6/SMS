<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSchoolSettingsRequest;
use App\Models\SchoolSetting;
use App\Services\SchoolSettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SchoolSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Settings/Edit', [
            'settings' => SchoolSetting::query()->where('singleton_key', 'primary')->first(),
        ]);
    }

    public function update(
        UpdateSchoolSettingsRequest $request,
        SchoolSettingsService $service,
    ): RedirectResponse {
        $service->update($request->validated());

        return back()->with('status', 'School settings saved.');
    }
}
