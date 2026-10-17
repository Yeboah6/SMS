<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\AdmissionFollowUp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmissionFollowUpService
{
    /**
     * @param  array{follow_up_at: string, method: string, notes: string}  $data
     */
    public function schedule(Admission $admission, array $data, User $actor): AdmissionFollowUp
    {
        return $admission->followUps()->create([
            ...$data,
            'created_by' => $actor->id,
            'status' => 'pending',
        ]);
    }

    public function complete(AdmissionFollowUp $followUp, string $outcome, User $actor): AdmissionFollowUp
    {
        return DB::transaction(function () use ($followUp, $outcome, $actor): AdmissionFollowUp {
            $followUp = AdmissionFollowUp::query()->lockForUpdate()->findOrFail($followUp->id);

            if ($followUp->status !== 'pending') {
                throw ValidationException::withMessages([
                    'outcome' => 'Only pending follow-ups can be completed.',
                ]);
            }

            $followUp->update([
                'status' => 'completed',
                'outcome' => $outcome,
                'completed_by' => $actor->id,
                'completed_at' => now(),
            ]);

            return $followUp->refresh();
        });
    }
}
