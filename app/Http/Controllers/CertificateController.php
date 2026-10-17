<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueCertificateRequest;
use App\Http\Requests\ReviewCertificateRequest;
use App\Http\Requests\RevokeCertificateRequest;
use App\Http\Requests\StoreCertificateRequest;
use App\Http\Requests\UpdateManagedCertificateRequest;
use App\Models\CertificateRequest as StudentCertificateRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    private const STATUSES = ['pending', 'issued', 'rejected', 'cancelled', 'revoked'];

    /** Requests that never became a certificate can be deleted. */
    private const DELETABLE = ['pending', 'rejected', 'cancelled'];

    public function index(Request $request): Response
    {
        return $this->canManage($request->user())
            ? $this->managerIndex($request)
            : $this->portalIndex($request);
    }

    // ---------------------------------------------------------------- staff pages

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('certificates.issue'), 403);

        return Inertia::render('Certificates/Create', [
            'certificateTypes' => StudentCertificateRequest::TYPES,
        ]);
    }

    public function issue(IssueCertificateRequest $request, CertificateService $service): RedirectResponse
    {
        $student = Student::query()->where('admission_number', $request->validated('admission_number'))->firstOrFail();
        $certificate = $service->issueDirect($student, $request->validated(), $request->user());

        return to_route('certificates.show', $certificate)->with('status', 'Certificate issued.');
    }

    public function edit(Request $request, StudentCertificateRequest $certificateRequest): Response
    {
        abort_unless($request->user()->can('certificates.update'), 403);
        abort_unless($certificateRequest->status === 'pending', 403);

        $certificateRequest->load('student:id,first_name,middle_name,last_name,admission_number');

        return Inertia::render('Certificates/Edit', [
            'certificateRequest' => [
                'id' => $certificateRequest->id,
                'admissionNumber' => $certificateRequest->student->admission_number,
                'studentName' => $this->studentName($certificateRequest->student),
                'certificateType' => $certificateRequest->certificate_type,
                'reason' => $certificateRequest->reason,
            ],
            'certificateTypes' => StudentCertificateRequest::TYPES,
        ]);
    }

    public function update(
        UpdateManagedCertificateRequest $request,
        StudentCertificateRequest $certificateRequest,
        CertificateService $service,
    ): RedirectResponse {
        $student = Student::query()->where('admission_number', $request->validated('admission_number'))->firstOrFail();
        $service->updatePending($certificateRequest, $student, $request->validated(), $request->user());

        return to_route('certificates.show', $certificateRequest)->with('status', 'Certificate request updated.');
    }

    public function destroy(Request $request, StudentCertificateRequest $certificateRequest, CertificateService $service): RedirectResponse
    {
        abort_unless($request->user()->can('certificates.delete'), 403);
        $service->deleteUnissued($certificateRequest, $request->user());

        return to_route('certificates.index')->with('status', 'Certificate request deleted.');
    }

    public function revoke(RevokeCertificateRequest $request, StudentCertificateRequest $certificateRequest, CertificateService $service): RedirectResponse
    {
        $service->revoke($certificateRequest, $request->validated('reason'), $request->user());

        return back()->with('status', 'Certificate revoked.');
    }

    // ------------------------------------------------------------- portal actions

    public function store(StoreCertificateRequest $request, CertificateService $service): RedirectResponse
    {
        $service->submit($request->validated(), $request->user());

        return back()->with('status', 'Certificate request submitted.');
    }

    public function review(
        ReviewCertificateRequest $request,
        StudentCertificateRequest $certificateRequest,
        CertificateService $service,
    ): RedirectResponse {
        $service->review($certificateRequest, $request->validated(), $request->user());

        return back()->with('status', 'Certificate request reviewed.');
    }

    public function cancel(
        Request $request,
        StudentCertificateRequest $certificateRequest,
        CertificateService $service,
    ): RedirectResponse {
        abort_unless($request->user()->can('portal.certificates.request'), 403);
        $service->cancel($certificateRequest, $request->user());

        return back()->with('status', 'Certificate request cancelled.');
    }

    // ----------------------------------------------------------------------- show

    public function show(Request $request, StudentCertificateRequest $certificateRequest): Response
    {
        $user = $request->user();

        if ($this->canManage($user)) {
            return $this->managerShow($user, $certificateRequest);
        }

        // Students and parents only ever see issued certificates.
        abort_unless($certificateRequest->status === 'issued', 404);

        $canAccessAsStudent = $user->can('portal.certificates.request')
            && $user->hasRole('Student')
            && Student::query()
                ->whereKey($certificateRequest->student_id)
                ->where('user_id', $user->id)
                ->exists();
        $canAccessAsParent = $user->can('portal.certificates.request')
            && ! $user->hasRole('Student')
            && $user->hasRole('Parent')
            && $certificateRequest->student()
                ->forGuardianAccount($user)
                ->exists();

        abort_unless($canAccessAsStudent || $canAccessAsParent, 404);

        return Inertia::render('Certificates/Show', [
            'mode' => 'portal',
            'certificate' => $this->certificatePayload($certificateRequest),
            'request' => null,
            'can' => [],
        ]);
    }

    // ------------------------------------------------------------------- internals

    private function managerIndex(Request $request): Response
    {
        $user = $request->user();
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();

        $requests = StudentCertificateRequest::query()
            ->with(['student:id,first_name,middle_name,last_name,admission_number', 'requester:id,name'])
            ->when(in_array($status, self::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when(in_array($type, StudentCertificateRequest::TYPES, true), fn ($query) => $query->where('certificate_type', $type))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('certificate_number', 'like', "%{$search}%")
                ->orWhereHas('student', fn ($students) => $students
                    ->where('admission_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%"))))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (StudentCertificateRequest $item): array => [
                'id' => $item->id,
                'studentName' => $this->studentName($item->student),
                'admissionNumber' => $item->student->admission_number,
                'certificateType' => $item->certificate_type,
                'status' => $item->status,
                'certificateNumber' => $item->certificate_number,
                'requestedBy' => $item->requester?->name,
                'requestedAt' => $item->created_at->toDateString(),
                'issuedAt' => $item->issued_at?->toDateString(),
                'canEdit' => $user->can('certificates.update') && $item->status === 'pending',
                'canDelete' => $user->can('certificates.delete') && in_array($item->status, self::DELETABLE, true),
            ]);

        return Inertia::render('Certificates/Index', [
            'requests' => $requests,
            'filters' => ['search' => $search, 'status' => $status, 'type' => $type],
            'certificateTypes' => StudentCertificateRequest::TYPES,
            'counts' => StudentCertificateRequest::query()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'can' => ['issue' => $user->can('certificates.issue')],
        ]);
    }

    private function portalIndex(Request $request): Response
    {
        $user = $request->user();
        $canRequest = $user->can('portal.certificates.request');
        $isStudent = $canRequest && $user->hasRole('Student');
        $students = $isStudent
            ? Student::query()->where('user_id', $user->id)->orderBy('id')->get()
            : ($canRequest && $user->hasRole('Parent')
                ? Student::query()->forGuardianAccount($user)->orderBy('first_name')->orderBy('last_name')->get()
                : collect());

        abort_unless($canRequest && $students->isNotEmpty(), 403);

        $requests = StudentCertificateRequest::query()
            ->with(['student:id,first_name,middle_name,last_name,admission_number', 'requester:id,name'])
            ->whereIn('student_id', $students->modelKeys())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (StudentCertificateRequest $item): array => [
                'id' => $item->id,
                'studentName' => $this->studentName($item->student),
                'admissionNumber' => $item->student->admission_number,
                'isOwn' => $item->requested_by === $user->id,
                'certificateType' => $item->certificate_type,
                'reason' => $item->reason,
                'status' => $item->status,
                'certificateNumber' => $item->certificate_number,
                'reviewComments' => $item->review_comments,
            ]);

        return Inertia::render('Certificates/Portal', [
            'certificateRequests' => $requests,
            'certificateTypes' => StudentCertificateRequest::TYPES,
            'students' => $students->map(fn (Student $student): array => [
                'id' => $student->id,
                'name' => $this->studentName($student),
                'admissionNumber' => $student->admission_number,
            ])->values(),
            'requiresStudentSelection' => ! $isStudent,
        ]);
    }

    private function managerShow(User $user, StudentCertificateRequest $certificateRequest): Response
    {
        $certificateRequest->load([
            'student:id,first_name,middle_name,last_name,admission_number',
            'requester:id,name',
            'reviewer:id,name',
            'revoker:id,name',
        ]);
        $status = $certificateRequest->status;

        return Inertia::render('Certificates/Show', [
            'mode' => 'manager',
            'certificate' => in_array($status, ['issued', 'revoked'], true)
                ? $this->certificatePayload($certificateRequest)
                : null,
            'request' => [
                'id' => $certificateRequest->id,
                'status' => $status,
                'certificateType' => $certificateRequest->certificate_type,
                'certificateNumber' => $certificateRequest->certificate_number,
                'studentId' => $certificateRequest->student_id,
                'studentName' => $this->studentName($certificateRequest->student),
                'admissionNumber' => $certificateRequest->student->admission_number,
                'reason' => $certificateRequest->reason,
                'requestedBy' => $certificateRequest->requester?->name,
                'requestedAt' => $certificateRequest->created_at->toDateTimeString(),
                'reviewer' => $certificateRequest->reviewer?->name,
                'reviewComments' => $certificateRequest->review_comments,
                'issuedAt' => $certificateRequest->issued_at?->toDateString(),
                'revokedAt' => $certificateRequest->revoked_at?->toDateTimeString(),
                'revokedBy' => $certificateRequest->revoker?->name,
                'revocationReason' => $certificateRequest->revocation_reason,
            ],
            'can' => [
                'review' => $user->can('certificates.issue') && $status === 'pending',
                'update' => $user->can('certificates.update') && $status === 'pending',
                'delete' => $user->can('certificates.delete') && in_array($status, self::DELETABLE, true),
                'revoke' => $user->can('certificates.revoke') && $status === 'issued',
            ],
        ]);
    }

    private function certificatePayload(StudentCertificateRequest $certificateRequest): array
    {
        return [
            'number' => $certificateRequest->certificate_number,
            'type' => $certificateRequest->certificate_type,
            'schoolName' => $certificateRequest->school_name,
            'studentName' => $certificateRequest->student_name,
            'admissionNumber' => $certificateRequest->admission_number,
            'issuedAt' => $certificateRequest->issued_at?->toDateString(),
            'revoked' => $certificateRequest->status === 'revoked',
        ];
    }

    private function canManage(User $user): bool
    {
        return $user->can('certificates.view') || $user->can('certificates.issue');
    }

    private function studentName(Student $student): string
    {
        return trim(implode(' ', array_filter([
            $student->first_name,
            $student->middle_name,
            $student->last_name,
        ], fn (?string $name): bool => $name !== null && $name !== '')));
    }
}