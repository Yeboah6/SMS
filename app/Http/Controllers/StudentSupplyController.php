<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Concerns\FormatsSupplies;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\Student;
use App\Models\StudentSupplySale;
use App\Services\SupplyRequirementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentSupplyController extends Controller
{
    use FormatsSupplies;

    /** Type-ahead for the sale screen: admission number, name, class or student id. */
    public function search(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('supplies.sales.create'), 403);

        $term = $request->string('q')->trim()->toString();
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $year = AcademicYear::active()->first();

        $students = Student::query()
            ->where('status', 'active')
            ->where(function ($query) use ($term): void {
                if (ctype_digit($term)) {
                    $query->orWhere('id', (int) $term);
                }
                $query->orWhere('admission_number', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhereHas('enrolments', fn ($enrolments) => $enrolments->where('status', 'active')
                        ->whereHas('schoolClass', fn ($classes) => $classes->where('name', 'like', "%{$term}%")));
            })
            ->with(['enrolments' => fn ($enrolments) => $enrolments->where('status', 'active')->when($year, fn ($query) => $query->where('academic_year_id', $year->id))->with('schoolClass:id,name')])
            ->orderBy('last_name')->orderBy('first_name')
            ->limit(10)
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'admission_number']);

        return response()->json($students->map(fn (Student $student): array => [
            'id' => $student->id,
            'name' => $this->studentName($student),
            'admissionNumber' => $student->admission_number,
            'className' => $student->enrolments->first()?->schoolClass?->name,
        ])->values());
    }

    /** One student's complete purchase history and where they stand on their class requirements. */
    public function show(Request $request, Student $student, SupplyRequirementService $requirements): Response
    {
        $this->authorize('viewAny', StudentSupplySale::class);

        $year = AcademicYear::active()->first();
        $enrolment = $year
            ? Enrolment::query()->with('schoolClass:id,name')->where('student_id', $student->id)->where('academic_year_id', $year->id)->where('status', 'active')->first()
            : null;
        $completion = $year && $enrolment?->schoolClass ? $requirements->completion($year, $enrolment->schoolClass, $student->id) : null;

        $totals = StudentSupplySale::query()->where('student_id', $student->id)
            ->selectRaw('COUNT(*) as sales, COALESCE(SUM(total_amount - returned_amount), 0) as net')
            ->first();

        return Inertia::render('Supplies/Students/Show', [
            'student' => ['id' => $student->id, 'name' => $this->studentName($student), 'admissionNumber' => $student->admission_number, 'className' => $enrolment?->schoolClass?->name],
            'sales' => StudentSupplySale::query()
                ->with(['student:id,first_name,middle_name,last_name,admission_number', 'schoolClass:id,name', 'academicYear:id,name', 'seller:id,name'])
                ->where('student_id', $student->id)->latest('sold_at')->latest('id')
                ->paginate(15)->withQueryString()
                ->through(fn (StudentSupplySale $sale): array => $this->salePayload($sale)),
            'totals' => [
                'sales' => (int) $totals->sales,
                'net' => (float) $totals->net,
                'outstanding' => round((float) StudentSupplySale::query()->outstanding()->where('student_id', $student->id)->get()->sum(fn (StudentSupplySale $sale): float => $sale->balance()), 2),
            ],
            'requirements' => $completion ? [
                'requirements' => $completion['requirements'],
                'lines' => $completion['students'][0]['lines'] ?? [],
                'complete' => $completion['students'][0]['complete'] ?? false,
            ] : null,
            'can' => ['sell' => $request->user()->can('supplies.sales.create')],
        ]);
    }
}