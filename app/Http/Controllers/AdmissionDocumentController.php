<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadAdmissionDocumentRequest;
use App\Models\Admission;
use App\Models\AdmissionDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmissionDocumentController extends Controller
{
    public function store(
        UploadAdmissionDocumentRequest $request,
        Admission $admission,
    ): RedirectResponse {
        $file = $request->file('document');
        $storagePath = $file->storeAs(
            "admission-documents/{$admission->id}",
            Str::uuid().'.'.$file->extension(),
            'local',
        );

        if (! $storagePath) {
            abort(500, 'The admission document could not be stored.');
        }

        try {
            $admission->documents()->create([
                'uploaded_by' => $request->user()->id,
                'category' => $request->validated('category'),
                'original_name' => $file->getClientOriginalName(),
                'storage_path' => $storagePath,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storagePath);

            throw $exception;
        }

        return back()->with('status', 'Admission document uploaded.');
    }

    public function download(
        Admission $admission,
        AdmissionDocument $document,
    ): StreamedResponse {
        abort_unless(request()->user()?->can('admissions.documents.manage'), 403);
        abort_unless($document->admission_id === $admission->id, 404);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->download($document->storage_path, $document->original_name);
    }
}
