<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignTeacherToSectionRequest;
use App\Http\Requests\AssignTeacherToSubjectRequest;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Subject;
use App\Services\AcademicStructureService;
use Illuminate\Http\RedirectResponse;

class TeacherAssignmentController extends Controller
{
    public function storeSection(
        AssignTeacherToSectionRequest $request,
        Section $section,
        AcademicStructureService $service,
    ): RedirectResponse {
        $staff = Staff::query()->findOrFail($request->validated('staff_id'));
        $service->assignTeacherToSection($staff, $section);

        return back()->with('status', 'Teacher assigned to section.');
    }

    public function storeSubject(
        AssignTeacherToSubjectRequest $request,
        Section $section,
        AcademicStructureService $service,
    ): RedirectResponse {
        $data = $request->validated();
        $staff = Staff::query()->findOrFail($data['staff_id']);
        $subject = Subject::query()->findOrFail($data['subject_id']);
        $service->assignTeacherToSubject($staff, $section, $subject);

        return back()->with('status', 'Teacher assigned to subject.');
    }
}
