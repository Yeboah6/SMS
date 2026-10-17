<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentIdCardService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentIdCardController extends Controller
{
    public function __invoke(Request $request, Student $student, StudentIdCardService $service): Response
    {
        $student = Student::query()->visibleTo($request->user())->findOrFail($student->getKey());
        $this->authorize('view', $student);

        return $service->download($student);
    }
}
