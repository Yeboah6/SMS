<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicEnquiryRequest;
use App\Services\AdmissionWorkflowService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublicEnquiryController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Public/Enquiry');
    }

    public function store(
        StorePublicEnquiryRequest $request,
        AdmissionWorkflowService $service,
    ): RedirectResponse {
        $service->createPublicEnquiry($request->validated());

        return back()->with('status', 'Thank you. The admissions team will contact you soon.');
    }
}
