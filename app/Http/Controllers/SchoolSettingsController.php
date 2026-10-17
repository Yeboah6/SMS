<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFeeSettingsRequest;
use App\Http\Requests\UpdateGradingSettingsRequest;
use App\Http\Requests\UpdateNotificationSettingsRequest;
use App\Http\Requests\UpdateSchoolProfileRequest;
use App\Models\SchoolSetting;
use App\Services\SchoolSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * School settings are one record, so there is no list, create or delete.
 * The overview shows every area; each area has its own edit page and save route.
 * The school profile (name) must be saved first, because the record cannot exist without it.
 */
class SchoolSettingsController extends Controller
{
    public function index(Request $request, SchoolSettingsService $service): Response
    {
        $settings = $this->current();

        return Inertia::render('Settings/Index', [
            'settings' => $this->payload($settings, $service),
            'configured' => $settings !== null,
            'can' => ['update' => $request->user()->can('settings.update')],
        ]);
    }

    public function school(Request $request, SchoolSettingsService $service): Response
    {
        abort_unless($request->user()->can('settings.update'), 403);

        return Inertia::render('Settings/School', [
            'settings' => $this->payload($this->current(), $service),
            'configured' => $this->current() !== null,
        ]);
    }

    public function grading(Request $request, SchoolSettingsService $service): Response|RedirectResponse
    {
        return $this->editPage($request, $service, 'Settings/Grading');
    }

    public function fees(Request $request, SchoolSettingsService $service): Response|RedirectResponse
    {
        return $this->editPage($request, $service, 'Settings/Fees');
    }

    public function notifications(Request $request, SchoolSettingsService $service): Response|RedirectResponse
    {
        return $this->editPage($request, $service, 'Settings/Notifications');
    }

    public function updateSchool(UpdateSchoolProfileRequest $request, SchoolSettingsService $service): RedirectResponse
    {
        $data = $request->validated();

        // First-time setup: give a brand-new record its default threshold.
        if ($this->current() === null) {
            $data += ['attendance_threshold' => 75];
        }

        $service->update($data);

        return to_route('settings.index')->with('status', 'School profile saved.');
    }

    public function updateGrading(UpdateGradingSettingsRequest $request, SchoolSettingsService $service): RedirectResponse
    {
        return $this->save($request->validated(), $service, 'Grading and attendance settings saved.');
    }

    public function updateFees(UpdateFeeSettingsRequest $request, SchoolSettingsService $service): RedirectResponse
    {
        return $this->save($request->validated(), $service, 'Fee defaults saved.');
    }

    public function updateNotifications(UpdateNotificationSettingsRequest $request, SchoolSettingsService $service): RedirectResponse
    {
        return $this->save($request->validated(), $service, 'Notification settings saved.');
    }

    // ------------------------------------------------------------------ helpers

    private function current(): ?SchoolSetting
    {
        return SchoolSetting::query()->where('singleton_key', 'primary')->first();
    }

    private function editPage(Request $request, SchoolSettingsService $service, string $component): Response|RedirectResponse
    {
        abort_unless($request->user()->can('settings.update'), 403);

        $settings = $this->current();
        if ($settings === null) {
            return to_route('settings.school')->with('status', 'Save the school profile first.');
        }

        return Inertia::render($component, ['settings' => $this->payload($settings, $service)]);
    }

    private function save(array $data, SchoolSettingsService $service, string $message): RedirectResponse
    {
        if ($this->current() === null) {
            return to_route('settings.school')->with('status', 'Save the school profile first.');
        }

        $service->update($data);

        return to_route('settings.index')->with('status', $message);
    }

    /** @return array<string, mixed> */
    private function payload(?SchoolSetting $settings, SchoolSettingsService $service): array
    {
        $defaults = $service->defaults();

        return [
            'school_name' => $settings?->school_name ?? '',
            'motto' => $settings?->motto ?? '',
            'address' => $settings?->address ?? '',
            'phone' => $settings?->phone ?? '',
            'email' => $settings?->email ?? '',
            'website' => $settings?->website ?? '',
            'attendance_threshold' => $settings?->attendance_threshold ?? '75.00',
            'grade_settings' => array_replace($defaults['grade_settings'], $settings?->grade_settings ?? []),
            'fee_settings' => array_replace($defaults['fee_settings'], $settings?->fee_settings ?? []),
            'notification_settings' => array_replace($defaults['notification_settings'], $settings?->notification_settings ?? []),
            'logo_url' => $settings?->logo_path ? Storage::disk('public')->url($settings->logo_path) : null,
            // Areas never customised are running on the built-in defaults.
            'uses_default_grades' => $settings === null || empty($settings->grade_settings),
            'uses_default_fees' => $settings === null || empty($settings->fee_settings),
            'uses_default_notifications' => $settings === null || empty($settings->notification_settings),
        ];
    }
}