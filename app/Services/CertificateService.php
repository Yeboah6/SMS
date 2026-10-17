<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\CertificateRequest;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CertificateService
{
    public function __construct(private StudentActivityService $activities) {}

    /**
     * @param  array{certificate_type: string, reason: string, student_id?: int}  $data
     */
    public function submit(array $data, User $actor): CertificateRequest
    {
        if (! $actor->can('portal.certificates.request')) {
            throw new AuthorizationException('You are not allowed to request student certificates.');
        }
        if (! $actor->hasRole('Student') && ! $actor->hasRole('Parent')) {
            throw new AuthorizationException('A student or linked parent profile is required.');
        }

        return DB::transaction(function () use ($data, $actor): CertificateRequest {
            $student = $actor->hasRole('Student')
                ? Student::query()->where('user_id', $actor->id)->lockForUpdate()->first()
                : Student::query()->whereKey($data['student_id'] ?? 0)->lockForUpdate()->first();

            if (
                ! $student
                || ($actor->hasRole('Parent') && ! $actor->hasRole('Student')
                    && ! $student->guardians()->where('guardians.user_id', $actor->id)->exists())
            ) {
                throw ValidationException::withMessages([
                    'student_id' => 'Select a student linked to your account.',
                ]);
            }

            $pendingRequestExists = CertificateRequest::query()
                ->where('student_id', $student->id)
                ->where('certificate_type', $data['certificate_type'])
                ->where('status', 'pending')
                ->lockForUpdate()
                ->exists();

            if ($pendingRequestExists) {
                throw ValidationException::withMessages([
                    'certificate_type' => 'A request for this certificate type is already pending.',
                ]);
            }

            return CertificateRequest::query()->create([
                'student_id' => $student->id,
                'requested_by' => $actor->id,
                'certificate_type' => $data['certificate_type'],
                'reason' => $data['reason'],
                'status' => 'pending',
            ]);
        });
    }

    /**
     * @param  array{outcome: string, review_comments?: ?string}  $data
     */
    public function review(CertificateRequest $certificateRequest, array $data, User $actor): CertificateRequest
    {
        if (! $actor->can('certificates.issue')) {
            throw new AuthorizationException('You are not allowed to review certificate requests.');
        }

        return DB::transaction(function () use ($certificateRequest, $data, $actor): CertificateRequest {
            $certificateRequest = CertificateRequest::query()
                ->lockForUpdate()
                ->findOrFail($certificateRequest->id);

            if ($certificateRequest->requested_by === $actor->id) {
                throw new AuthorizationException('You cannot review your own certificate request.');
            }

            if ($certificateRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'outcome' => 'Only pending certificate requests can be reviewed.',
                ]);
            }

            $update = [
                'status' => $data['outcome'],
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_comments' => $data['review_comments'] ?? null,
            ];

            if ($data['outcome'] === 'issued') {
                $student = $certificateRequest->student()->lockForUpdate()->firstOrFail();
                $update += $this->issuedAttributes($certificateRequest, $student);
            }

            $certificateRequest->update($update);

            return $certificateRequest->refresh();
        });
    }

    public function cancel(CertificateRequest $certificateRequest, User $actor): void
    {
        DB::transaction(function () use ($certificateRequest, $actor): void {
            $certificateRequest = CertificateRequest::query()
                ->lockForUpdate()
                ->findOrFail($certificateRequest->id);

            abort_unless($certificateRequest->requested_by === $actor->id, 404);

            if ($certificateRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'certificate_request' => 'Only pending certificate requests can be cancelled.',
                ]);
            }

            $certificateRequest->update(['status' => 'cancelled']);
        });
    }

    /**
     * Issues a certificate straight away for a student who never submitted a request.
     * The office user is recorded as requester and issuer, so the "cannot review your own request"
     * rule in review() does not apply here.
     *
     * @param  array{certificate_type: string, reason: string, review_comments?: ?string}  $data
     */
    public function issueDirect(Student $student, array $data, User $actor): CertificateRequest
    {
        if (! $actor->can('certificates.issue')) {
            throw new AuthorizationException('You are not allowed to issue certificates.');
        }

        return DB::transaction(function () use ($student, $data, $actor): CertificateRequest {
            $student = Student::query()->lockForUpdate()->findOrFail($student->id);

            $certificateRequest = CertificateRequest::query()->create([
                'student_id' => $student->id,
                'requested_by' => $actor->id,
                'certificate_type' => $data['certificate_type'],
                'reason' => $data['reason'],
                'status' => 'issued',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_comments' => $data['review_comments'] ?? 'Issued directly by the school office.',
            ]);

            $certificateRequest->update($this->issuedAttributes($certificateRequest, $student));

            return $certificateRequest->refresh();
        });
    }

    /** Corrects a request that has not been decided yet. */
    public function updatePending(
        CertificateRequest $certificateRequest,
        Student $student,
        array $data,
        User $actor,
    ): CertificateRequest {
        if (! $actor->can('certificates.update')) {
            throw new AuthorizationException('You are not allowed to edit certificate requests.');
        }

        return DB::transaction(function () use ($certificateRequest, $student, $data): CertificateRequest {
            $request = CertificateRequest::query()->lockForUpdate()->findOrFail($certificateRequest->id);

            if ($request->status !== 'pending') {
                throw ValidationException::withMessages([
                    'certificate' => 'Only pending requests can be edited. Issued certificates are revoked, not edited.',
                ]);
            }

            $duplicate = CertificateRequest::query()
                ->where('student_id', $student->id)
                ->where('certificate_type', $data['certificate_type'])
                ->where('status', 'pending')
                ->whereKeyNot($request->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'certificate_type' => 'A request for this certificate type is already pending for that student.',
                ]);
            }

            $request->forceFill([
                'student_id' => $student->id,
                'certificate_type' => $data['certificate_type'],
                'reason' => $data['reason'],
            ])->save();

            return $request;
        });
    }

    /** Deletes a request that never became a certificate. */
    public function deleteUnissued(CertificateRequest $certificateRequest, User $actor): void
    {
        if (! $actor->can('certificates.delete')) {
            throw new AuthorizationException('You are not allowed to delete certificate requests.');
        }

        DB::transaction(function () use ($certificateRequest): void {
            $request = CertificateRequest::query()->lockForUpdate()->findOrFail($certificateRequest->id);

            if (! in_array($request->status, ['pending', 'rejected', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'certificate' => 'Issued certificates cannot be deleted. Revoke the certificate instead.',
                ]);
            }

            $request->delete();
        });
    }

    /** Voids an issued certificate. Its number stays used and students and parents can no longer open it. */
    public function revoke(CertificateRequest $certificateRequest, string $reason, User $actor): CertificateRequest
    {
        if (! $actor->can('certificates.revoke')) {
            throw new AuthorizationException('You are not allowed to revoke certificates.');
        }

        return DB::transaction(function () use ($certificateRequest, $reason, $actor): CertificateRequest {
            $request = CertificateRequest::query()->with('student')->lockForUpdate()->findOrFail($certificateRequest->id);

            if ($request->status !== 'issued') {
                throw ValidationException::withMessages([
                    'certificate' => 'Only issued certificates can be revoked.',
                ]);
            }

            $request->forceFill([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoked_by' => $actor->id,
                'revocation_reason' => $reason,
            ])->save();

            // Audit entry on the student record (skipped when there is no active academic year).
            if ($year = AcademicYear::active()->first()) {
                $this->activities->record(
                    $request->student,
                    $actor,
                    'certificate.revoked',
                    "Certificate {$request->certificate_number} revoked.",
                    $year,
                    ['certificate_request_id' => $request->id, 'reason' => $reason],
                );
            }

            return $request;
        });
    }

    /**
     * The number and the school/student snapshot printed on the certificate.
     *
     * @return array<string, mixed>
     */
    private function issuedAttributes(CertificateRequest $certificateRequest, Student $student): array
    {
        $schoolSetting = SchoolSetting::query()->where('singleton_key', 'primary')->first();

        return [
            'certificate_number' => sprintf('CERT-%08d', $certificateRequest->id),
            'school_name' => $schoolSetting?->school_name ?? config('app.name'),
            'student_name' => $this->studentName($student),
            'admission_number' => $student->admission_number,
            'issued_at' => now(),
        ];
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