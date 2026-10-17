<?php

namespace App\Http\Controllers;

use App\Enums\AcademicYearStatus;
use App\Http\Requests\StoreAdmissionRequest;
use App\Http\Requests\TransitionAdmissionRequest;
use App\Http\Requests\UpdateAdmissionRequest;
use App\Models\AcademicYear;
use App\Models\Admission;
use App\Services\AdmissionWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionController extends Controller
{
    private const STATUSES = ['enquiry', 'application_received', 'under_review', 'approved', 'rejected', 'enrolled'];

    /** Details can be corrected until the application is decided. */
    private const EDITABLE = ['enquiry', 'application_received', 'under_review'];

    /** Only records that never became a student can be removed. */
    private const DELETABLE = ['enquiry', 'application_received', 'rejected'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('admissions.view'), 403);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();

        $admissions = Admission::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('application_number', 'like', "%{$search}%")
                    ->orWhere('parent_name', 'like', "%{$search}%")
                    ->orWhere('parent_phone', 'like', "%{$search}%")
                    ->orWhere('child_first_name', 'like', "%{$search}%")
                    ->orWhere('child_last_name', 'like', "%{$search}%");
            }))
            ->when(in_array($status, self::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Admission $admission): array => [
                'id' => $admission->id,
                'applicationNumber' => $admission->application_number,
                'childName' => $this->childName($admission),
                'parentName' => $admission->parent_name,
                'parentPhone' => $admission->parent_phone,
                'applyingFor' => $admission->applying_for,
                'status' => $admission->status,
                'createdAt' => $admission->created_at->toDateString(),
                'canEdit' => $user->can('admissions.update') && in_array($admission->status, self::EDITABLE, true),
                'canDelete' => $user->can('admissions.delete') && $this->isDeletable($admission),
            ]);

        return Inertia::render('Admissions/Index', [
            'admissions' => $admissions,
            'search' => $search,
            'selectedStatus' => $status,
            // Pipeline totals for the stage tabs (not affected by the search box).
            'counts' => Admission::query()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'can' => ['create' => $user->can('admissions.create')],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('admissions.create'), 403);

        return Inertia::render('Admissions/Create', ['today' => today()->toDateString()]);
    }

    public function store(StoreAdmissionRequest $request, AdmissionWorkflowService $service): RedirectResponse
    {
        $admission = $service->create($request->validated(), $request->user());

        if (! $admission instanceof Admission) {
            return to_route('admissions.index')->with('status', 'Enquiry created.');
        }

        $admission->statusHistory()->create([
            'from_status' => null,
            'to_status' => $admission->status,
            'changed_by' => $request->user()->id,
        ]);

        return to_route('admissions.show', $admission)->with('status', 'Enquiry created.');
    }

    public function show(Request $request, Admission $admission): Response
    {
        $user = $request->user();
        abort_unless($user->can('admissions.view'), 403);

        $admission->load([
            'student:id,admission_number',
            'documents:id,admission_id,category,original_name,created_at',
            'followUps:id,admission_id,follow_up_at,method,notes,status,outcome,completed_at',
            'statusHistory.changedBy:id,name',
        ]);

        $needsEnrolmentOptions = $admission->status === 'approved' && $user->can('admissions.enrol');

        return Inertia::render('Admissions/Show', [
            'admission' => [
                'id' => $admission->id,
                'applicationNumber' => $admission->application_number,
                'status' => $admission->status,
                'childName' => $this->childName($admission),
                'dateOfBirth' => $admission->date_of_birth->toDateString(),
                'gender' => $admission->gender,
                'childAddress' => $admission->child_address,
                'applyingFor' => $admission->applying_for,
                'source' => $admission->source,
                'parentName' => $admission->parent_name,
                'parentPhone' => $admission->parent_phone,
                'parentEmail' => $admission->parent_email,
                'parentOccupation' => $admission->parent_occupation,
                'parentAddress' => $admission->parent_address,
                'reviewNotes' => $admission->review_notes,
                'createdAt' => $admission->created_at->toDateString(),
                'studentId' => $admission->student?->id,
                'studentAdmissionNumber' => $admission->student?->admission_number,
                'documents' => $user->can('admissions.documents.manage')
                    ? $admission->documents->map(fn ($document): array => [
                        'id' => $document->id,
                        'category' => $document->category,
                        'name' => $document->original_name,
                        'createdAt' => $document->created_at->toDateString(),
                    ])->all()
                    : [],
                'followUps' => $user->can('admissions.followups.manage')
                    ? $admission->followUps->map(fn ($followUp): array => [
                        'id' => $followUp->id,
                        'followUpAt' => $followUp->follow_up_at->toDateTimeString(),
                        'method' => $followUp->method,
                        'notes' => $followUp->notes,
                        'status' => $followUp->status,
                        'outcome' => $followUp->outcome,
                    ])->all()
                    : [],
                'history' => $admission->statusHistory->map(fn ($entry): array => [
                    'id' => $entry->id,
                    'from' => $entry->from_status,
                    'to' => $entry->to_status,
                    'by' => $entry->changedBy?->name ?? 'System',
                    'notes' => $entry->notes,
                    'at' => $entry->created_at->format('Y-m-d H:i'),
                ])->all(),
            ],
            'academicYears' => $needsEnrolmentOptions ? $this->enrolmentOptions() : [],
            'today' => today()->toDateString(),
            'followUpDefault' => now()->addHour()->format('Y-m-d\TH:i'),
            'can' => [
                'update' => $user->can('admissions.update') && in_array($admission->status, self::EDITABLE, true),
                'delete' => $user->can('admissions.delete') && $this->isDeletable($admission),
                'review' => $user->can('admissions.review'),
                'approve' => $user->can('admissions.approve'),
                'enrol' => $user->can('admissions.enrol'),
                'manageDocuments' => $user->can('admissions.documents.manage'),
                'manageFollowUps' => $user->can('admissions.followups.manage'),
                'assignFees' => $user->can('invoices.create'),
                'recordFirstPayment' => $user->can('payments.create'),
            ],
        ]);
    }

    public function edit(Request $request, Admission $admission): Response
    {
        abort_unless($request->user()->can('admissions.update'), 403);
        abort_unless(in_array($admission->status, self::EDITABLE, true), 403);

        return Inertia::render('Admissions/Edit', [
            'admission' => [
                'id' => $admission->id,
                'applicationNumber' => $admission->application_number,
                'parentName' => $admission->parent_name,
                'parentPhone' => $admission->parent_phone,
                'parentEmail' => $admission->parent_email,
                'parentOccupation' => $admission->parent_occupation,
                'parentAddress' => $admission->parent_address,
                'childFirstName' => $admission->child_first_name,
                'childMiddleName' => $admission->child_middle_name,
                'childLastName' => $admission->child_last_name,
                'dateOfBirth' => $admission->date_of_birth->toDateString(),
                'gender' => $admission->gender,
                'childAddress' => $admission->child_address,
                'applyingFor' => $admission->applying_for,
                'source' => $admission->source,
            ],
            'today' => today()->toDateString(),
        ]);
    }

    public function update(UpdateAdmissionRequest $request, Admission $admission): RedirectResponse
    {
        abort_unless(in_array($admission->status, self::EDITABLE, true), 403);

        $admission->forceFill($request->validated())->save();

        return to_route('admissions.show', $admission)->with('status', 'Admission details updated.');
    }

    public function destroy(Request $request, Admission $admission): RedirectResponse
    {
        abort_unless($request->user()->can('admissions.delete'), 403);
        abort_unless($this->isDeletable($admission), 403);

        $paths = [];
        DB::transaction(function () use ($admission, &$paths): void {
            foreach ($admission->documents as $document) {
                $paths[] = $document->storage_path;
                $document->delete();
            }
            $admission->followUps()->delete();
            $admission->statusHistory()->delete();
            $admission->delete();
        });
        Storage::disk('local')->delete($paths);

        return to_route('admissions.index')->with('status', 'Admission record deleted.');
    }

    public function transition(
        TransitionAdmissionRequest $request,
        Admission $admission,
        AdmissionWorkflowService $service,
    ): RedirectResponse {
        $from = $admission->status;
        $data = $request->validated();

        $service->transition($admission, $data, $request->user());

        $admission->refresh();
        if ($admission->status !== $from) {
            $admission->statusHistory()->create([
                'from_status' => $from,
                'to_status' => $admission->status,
                'changed_by' => $request->user()->id,
                'notes' => $data['review_notes'] ?? null,
            ]);
        }

        return back()->with('status', 'Admission stage updated.');
    }

    private function isDeletable(Admission $admission): bool
    {
        return in_array($admission->status, self::DELETABLE, true) && $admission->student_id === null;
    }

    private function childName(Admission $admission): string
    {
        return trim(implode(' ', array_filter([
            $admission->child_first_name,
            $admission->child_middle_name,
            $admission->child_last_name,
        ])));
    }

    /** Years, classes, sections and fees offered by the enrolment step. */
    private function enrolmentOptions(): array
    {
        return AcademicYear::query()
            ->whereNot('status', AcademicYearStatus::Locked)
            ->with(['classes.sections', 'classes.feeStructures.feeHead'])
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (AcademicYear $year): array => [
                'id' => $year->id,
                'name' => $year->name,
                'classes' => $year->classes->map(fn ($schoolClass): array => [
                    'id' => $schoolClass->id,
                    'name' => $schoolClass->name,
                    'fees' => $schoolClass->feeStructures->map(fn ($structure): array => [
                        'name' => $structure->feeHead->name,
                        'amount' => $structure->amount,
                    ])->values(),
                    'sections' => $schoolClass->sections->map(fn ($section): array => [
                        'id' => $section->id,
                        'name' => $section->name,
                    ])->values(),
                ])->values(),
            ])
            ->all();
    }
}