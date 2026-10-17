<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteAdmissionFollowUpRequest;
use App\Http\Requests\StoreAdmissionFollowUpRequest;
use App\Models\Admission;
use App\Models\AdmissionFollowUp;
use App\Services\AdmissionFollowUpService;
use Illuminate\Http\RedirectResponse;

class AdmissionFollowUpController extends Controller
{
    public function store(
        StoreAdmissionFollowUpRequest $request,
        Admission $admission,
        AdmissionFollowUpService $service,
    ): RedirectResponse {
        $service->schedule($admission, $request->validated(), $request->user());

        return back()->with('status', 'Admission follow-up scheduled.');
    }

    public function complete(
        CompleteAdmissionFollowUpRequest $request,
        AdmissionFollowUp $followUp,
        AdmissionFollowUpService $service,
    ): RedirectResponse {
        $service->complete($followUp, $request->validated('outcome'), $request->user());

        return back()->with('status', 'Admission follow-up completed.');
    }
}
