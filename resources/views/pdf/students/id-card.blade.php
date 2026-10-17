<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; margin: 0; color: #0f172a; }
        .card { box-sizing: border-box; height: 54mm; padding: 5mm; position: relative; width: 86mm; }
        .school { border-bottom: 1px solid #cbd5e1; font-size: 10pt; font-weight: bold; height: 11mm; padding-bottom: 2mm; }
        .logo { float: right; height: 10mm; max-width: 18mm; object-fit: contain; }
        .photo { border: 1px solid #cbd5e1; float: left; height: 25mm; margin-right: 4mm; object-fit: cover; width: 20mm; }
        .details { font-size: 8pt; line-height: 1.55; padding-top: 3mm; }
        .name { font-size: 11pt; font-weight: bold; margin: 0 0 1mm; }
        .qr { bottom: 3mm; height: 18mm; position: absolute; right: 4mm; width: 18mm; }
    </style>
</head>
<body>
    <main class="card">
        @if ($schoolLogo)
            <img class="logo" src="{{ $schoolLogo }}" alt="">
        @endif
        <header class="school">{{ $schoolName ?: 'Student Identification Card' }}</header>
        @if ($photo)
            <img class="photo" src="{{ $photo }}" alt="">
        @endif
        <section class="details">
            <p class="name">{{ trim($student->first_name.' '.$student->middle_name.' '.$student->last_name) }}</p>
            <div>Admission No: {{ $student->admission_number }}</div>
            <div>Status: {{ ucfirst($student->status) }}</div>
        </section>
        <div class="qr">{!! $qrCode !!}</div>
    </main>
</body>
</html>
