<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; margin: 0; color: #0f172a; }
        
        .card {
            box-sizing: border-box;
            height: 54mm;
            width: 86mm;
            position: relative;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        
        /* Top brand accent */
        .accent-bar {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4mm;
            background-color: #0f766e; /* teal-700 */
        }
        
        /* Header */
        .header {
            position: absolute;
            top: 8mm;
            left: 5mm;
            right: 5mm;
            height: 10mm;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2mm;
        }
        
        .logo {
            height: 8mm;
            max-width: 18mm;
            float: left;
            margin-right: 3mm;
        }
        
        .school-name {
            font-size: 9pt;
            font-weight: bold;
            color: #0f172a;
            line-height: 8mm;
            float: left;
            max-width: 50mm;
            height: 8mm;
            overflow: hidden;
        }
        
        /* Photo */
        .photo-wrapper {
            position: absolute;
            top: 21mm;
            left: 5mm;
            width: 20mm;
            height: 26mm;
            border: 1px solid #cbd5e1;
            border-radius: 1.5mm;
            overflow: hidden;
            background-color: #f8fafc;
        }
        
        .photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .photo-placeholder {
            width: 100%;
            height: 100%;
            text-align: center;
            vertical-align: middle;
            color: #94a3b8;
            font-size: 7pt;
        }
        
        /* Details */
        .details {
            position: absolute;
            top: 21mm;
            left: 28mm;
            width: 35mm;
        }
        
        .name {
            font-size: 10pt;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 3mm 0;
            line-height: 1.2;
        }
        
        .detail-row {
            margin-bottom: 2mm;
        }
        
        .detail-label {
            color: #64748b;
            font-size: 6.5pt;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5mm;
        }
        
        .detail-value {
            font-size: 8pt;
            font-weight: bold;
            color: #1e293b;
        }
        
        /* QR Code */
        .qr {
            position: absolute;
            bottom: 4mm;
            right: 5mm;
            width: 16mm;
            height: 16mm;
        }
        
        .qr svg {
            width: 100%;
            height: 100%;
            display: block;
        }
        
        /* Bottom brand accent */
        .footer-strip {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2mm;
            background-color: #0f766e;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="accent-bar"></div>
        
        <header class="header">
            @if ($schoolLogo)
                <img class="logo" src="{{ $schoolLogo }}" alt="">
            @endif
            <div class="school-name">{{ $schoolName ?: 'Student Identification Card' }}</div>
        </header>

        <div class="photo-wrapper">
            @if ($photo)
                <img class="photo" src="{{ $photo }}" alt="">
            @else
                <table class="photo-placeholder"><tr><td>No Photo</td></tr></table>
            @endif
        </div>

        <section class="details">
            <h1 class="name">{{ trim($student->first_name.' '.$student->middle_name.' '.$student->last_name) }}</h1>
            
            <div class="detail-row">
                <div class="detail-label">Admission No</div>
                <div class="detail-value">{{ $student->admission_number }}</div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Status</div>
                <div class="detail-value">{{ ucfirst($student->status) }}</div>
            </div>
        </section>

        <div class="qr">
            {!! $qrCode !!}
        </div>
        
        <div class="footer-strip"></div>
    </main>
</body>
</html>