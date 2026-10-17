<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdmissionWorkflowService
{
    public function __construct(private StudentProfileService $studentProfiles) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Admission
    {
        return $this->createAdmission($data, $actor);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPublicEnquiry(array $data): Admission
    {
        $data['source'] = 'website';

        return $this->createAdmission($data, null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createAdmission(array $data, ?User $actor): Admission
    {
        return DB::transaction(function () use ($data, $actor): Admission {
            $admission = Admission::query()->create([
                ...$data,
                'status' => 'enquiry',
                'created_by' => $actor?->id,
            ]);

            $admission->update([
                'application_number' => 'APP-'.str_pad((string) $admission->id, 8, '0', STR_PAD_LEFT),
            ]);

            return $admission->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function transition(Admission $admission, array $data, User $actor): Admission
    {
        $studentAccount = null;
        DB::transaction(function () use ($admission, $data, $actor, &$studentAccount): void {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->getKey());
            $targetStatus = $data['status'];
            $allowedTransitions = [
                'enquiry' => ['application_received'],
                'application_received' => ['under_review'],
                'under_review' => ['approved', 'rejected'],
                'approved' => ['enrolled'],
                'rejected' => [],
                'enrolled' => [],
            ];

            if (! in_array($targetStatus, $allowedTransitions[$admission->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => 'This admission cannot move to the selected stage.',
                ]);
            }

            if ($targetStatus === 'enrolled') {
                $student = $this->createEnrolledStudent($admission, $data);
                $admission->update([
                    'student_id' => $student->id,
                    'status' => 'enrolled',
                ]);

                if ($data['assign_fees'] ?? false) {
                    $invoice = app(FeeService::class)->assignStudentFees(
                        (int) $data['academic_year_id'],
                        (int) $data['class_id'],
                        $student->id,
                        $actor,
                    );

                    if ($data['first_payment'] ?? false) {
                        app(FeeService::class)->recordPayment($invoice, [
                            'amount' => $data['first_payment_amount'],
                            'payment_method' => $data['first_payment_method'],
                            'transaction_reference' => $data['first_payment_reference'] ?? null,
                            'payment_date' => $data['first_payment_date'],
                            'notes' => $data['first_payment_notes'] ?? null,
                        ], $actor);
                    }
                }

                if ($data['create_student_account'] ?? false) {
                    $studentAccount = $this->createStudentAccount($student, $data['student_email']);
                }

                return;
            }

            $updates = [
                'status' => $targetStatus,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ];

            if (array_key_exists('review_notes', $data)) {
                $updates['review_notes'] = $data['review_notes'];
            }

            $admission->update($updates);

        });

        if ($studentAccount) {
            $status = Password::sendResetLink(['email' => $studentAccount->email]);
            if ($status !== Password::RESET_LINK_SENT) {
                throw ValidationException::withMessages([
                    'student_email' => 'The student was enrolled, but the password setup email could not be sent. Use password reset to resend the link.',
                ]);
            }
        }

        return $admission->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createEnrolledStudent(Admission $admission, array $data): Student
    {
        $student = $this->studentProfiles->create([
            'admission_number' => 'STU-'.str_pad((string) $admission->id, 8, '0', STR_PAD_LEFT),
            'first_name' => $admission->child_first_name,
            'middle_name' => $admission->child_middle_name,
            'last_name' => $admission->child_last_name,
            'date_of_birth' => $admission->date_of_birth->toDateString(),
            'gender' => $admission->gender,
            'address' => $admission->child_address,
            'admission_date' => today()->toDateString(),
            'status' => 'active',
            'guardians' => [[
                'name' => $admission->parent_name,
                'phone' => $admission->parent_phone,
                'email' => $admission->parent_email,
                'occupation' => $admission->parent_occupation,
                'address' => $admission->parent_address,
                'relationship' => 'Parent',
                'is_primary' => true,
                'lives_with_student' => true,
            ]],
        ]);

        $this->studentProfiles->enrol($student, [
            'academic_year_id' => $data['academic_year_id'],
            'class_id' => $data['class_id'],
            'section_id' => $data['section_id'],
            'enrolment_date' => $data['enrolment_date'],
        ]);

        return $student;
    }

    private function createStudentAccount(Student $student, string $email): User
    {
        $email = Str::lower(trim($email));

        if (User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'student_email' => 'An account already uses this email address.',
            ]);
        }

        $user = User::query()->create([
            'name' => trim(implode(' ', array_filter([
                $student->first_name,
                $student->middle_name,
                $student->last_name,
            ]))),
            'email' => $email,
            'password' => Str::random(64),
        ]);
        $user->assignRole('Student');
        $student->user()->associate($user);
        $student->save();

        return $user;
    }
}
