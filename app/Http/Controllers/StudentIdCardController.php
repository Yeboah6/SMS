<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentIdCardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class StudentIdCardController extends Controller
{
    public function __invoke(Request $request, Student $student, StudentIdCardService $service): InertiaResponse
    {
        $student = Student::query()->visibleTo($request->user())->findOrFail($student->getKey());
        $this->authorize('view', $student);

        return Inertia::render('Students/IdCard', $service->payload($student));
    }
}