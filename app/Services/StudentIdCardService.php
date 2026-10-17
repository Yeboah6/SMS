<?php

namespace App\Services;

use App\Models\SchoolSetting;
use App\Models\Student;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class StudentIdCardService
{
    public function download(Student $student): Response
    {
        $settings = SchoolSetting::query()->first();
        $photoData = $student->photo_path && Storage::disk('local')->exists($student->photo_path)
            ? 'data:'.Storage::disk('local')->mimeType($student->photo_path).';base64,'.base64_encode(
                Storage::disk('local')->get($student->photo_path),
            )
            : null;
        $logoData = $settings?->logo_path && Storage::disk('public')->exists($settings->logo_path)
            ? 'data:'.Storage::disk('public')->mimeType($settings->logo_path).';base64,'.base64_encode(
                Storage::disk('public')->get($settings->logo_path),
            )
            : null;
        $qrCode = (new Writer(new ImageRenderer(
            new RendererStyle(240, 1),
            new SvgImageBackEnd,
        )))->writeString($student->admission_number);

        return Pdf::loadView('pdf.students.id-card', [
            'student' => $student,
            'schoolName' => $settings?->school_name,
            'schoolLogo' => $logoData,
            'photo' => $photoData,
            'qrCode' => $qrCode,
        ])
            ->setPaper([0, 0, 243, 153])
            ->download('student-id-'.$student->admission_number.'.pdf');
    }
}
