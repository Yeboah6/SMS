<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentTransferRequest;
use App\Models\Student;
use App\Services\StudentTransferService;
use Illuminate\Http\RedirectResponse;

class StudentTransferController extends Controller
{
    public function store(
        StoreStudentTransferRequest $request,
        Student $student,
        StudentTransferService $service,
    ): RedirectResponse {
        $service->transfer($student, $request->validated(), $request->user());

        return redirect()->route('students.show', $student)->with('status', 'Student transfer recorded.');
    }
}
