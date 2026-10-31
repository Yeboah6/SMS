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
    private ?SchoolSetting $settings = null;

    /** Existing PDF download — unchanged output. */
    public function download(Student $student): Response
    {
        return Pdf::loadView('pdf.students.id-card', $this->cardData($student))
            ->setPaper([0, 0, 243, 153])
            ->download('student-id-'.$student->admission_number.'.pdf');
    }

    /**
     * Props for the React ID card page.
     *
     * @return array<string, mixed>
     */
    public function payload(Student $student): array
    {
        $student->loadMissing('enrolments.schoolClass', 'enrolments.section');

        $data = $this->cardData($student);

        return [
            'student' => [
                'id' => $student->id,
                'name' => $this->fullName($student),
                'admissionNumber' => $student->admission_number,
                'status' => $student->status,
                'enrolment' => $student->enrolment ? [
                    'class' => $student->enrolment->schoolClass?->name,
                    'section' => $student->enrolment->section?->name,
                ] : null,
            ],
            'schoolName' => $data['schoolName'] ?? '',
            'schoolLogo' => $data['schoolLogo'],
            'photo' => $data['photo'],
            'qrCode' => $data['qrCode'],
            'backUrl' => route('students.show', $student->id),
        ];
    }

    /**
     * Shared builder — the Blade view keeps its `$student` variable, and the
     * React page reads the same logo/photo/QR values.
     *
     * @return array<string, mixed>
     */
    private function cardData(Student $student): array
    {
        return [
            'student' => $student,
            'schoolName' => $this->settings()?->school_name,
            'schoolLogo' => $this->logoDataUri(),
            'photo' => $this->photoDataUri($student),
            'qrCode' => $this->qrSvg($student),
        ];
    }

    private function settings(): ?SchoolSetting
    {
        return $this->settings ??= SchoolSetting::query()
            ->where('singleton_key', 'primary')
            ->first() ?? SchoolSetting::query()->first();
    }

    private function photoDataUri(Student $student): ?string
    {
        if (! $student->photo_path || ! Storage::disk('local')->exists($student->photo_path)) {
            return null;
        }

        return 'data:'.Storage::disk('local')->mimeType($student->photo_path)
            .';base64,'.base64_encode(Storage::disk('local')->get($student->photo_path));
    }

    private function logoDataUri(): ?string
    {
        $path = $this->settings()?->logo_path;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return 'data:'.Storage::disk('public')->mimeType($path)
            .';base64,'.base64_encode(Storage::disk('public')->get($path));
    }

    private function qrSvg(Student $student): string
    {
        return (new Writer(new ImageRenderer(
            new RendererStyle(240, 1),
            new SvgImageBackEnd,
        )))->writeString($student->admission_number);
    }

    private function fullName(Student $student): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
            $student->first_name,
            $student->middle_name,
            $student->last_name,
        ]))));
    }
}