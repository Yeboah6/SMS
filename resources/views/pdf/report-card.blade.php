@php
    $d = $card['details'];
    $date = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('j M Y') : '';
    $money = fn ($v) => $v === null || $v === '' ? '' : number_format((float) $v, 2);
    $hasFees = ($d['arrears'] ?? null) !== null || ($d['nextFee'] ?? null) !== null;
    $due = (float) ($d['arrears'] ?? 0) + (float) ($d['nextFee'] ?? 0);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Report card – {{ $card['student']['name'] }}</title>
    <style>
        @page { margin: 22px 26px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 15px; text-align: center; margin: 0 0 2px; }
        .sub { text-align: center; color: #444; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        .box { border: 2px solid #222; padding: 8px; }
        .plain td { border: none; padding: 4px 4px; }
        .plain td.val { border-bottom: 1px dotted #555; }
        .plain td.lab { font-weight: bold; white-space: nowrap; width: 1%; }
        .scores th, .scores td { border: 1px solid #222; padding: 5px 5px; }
        .scores th { font-size: 9px; text-transform: uppercase; }
        .c { text-align: center; }
        .scores .total td { border-top: 2px solid #222; font-weight: bold; }
        .block { margin-top: 10px; }
    </style>
</head>
<body>
    <table class="plain" style="margin-bottom: 6px;">
        <tr>
            @if ($school['logoDataUri'])
                <td style="width: 64px;"><img src="{{ $school['logoDataUri'] }}" style="height: 56px; width: 56px;"></td>
            @endif
            <td style="text-align: center;">
                <div style="font-size: 15px; font-weight: bold; text-transform: uppercase;">{{ $school['name'] }}</div>
                @if ($school['motto'])<div style="font-style: italic; color: #444;">{{ $school['motto'] }}</div>@endif
                @if ($school['contact'])<div style="color: #444; font-size: 9px;">{{ $school['contact'] }}</div>@endif
                <div class="sub" style="margin-bottom: 0;">Terminal Report · {{ $exam['name'] }}</div>
            </td>
            @if ($school['logoDataUri'])
                <td style="width: 64px;"></td>
            @endif
        </tr>
    </table>
    <div class="sub"><strong>{{ $card['student']['name'] }}</strong>@if($card['student']['admissionNumber']) · {{ $card['student']['admissionNumber'] }}@endif</div>

    <div class="box">
        <table class="plain">
            <tr>
                <td class="lab">FORM:</td><td class="val">{{ $exam['className'] }}</td>
                <td class="lab">TERM:</td><td class="val">{{ $exam['termName'] }}</td>
                <td class="lab">YEAR:</td><td class="val">{{ $exam['academicYear'] }}</td>
            </tr>
            <tr>
                <td class="lab">DATE OF REPORT:</td><td class="val" colspan="2">{{ $date($exam['reportDate']) }}</td>
                <td class="lab">CLOSING DATE:</td><td class="val" colspan="2">{{ $date($d['closing']) }}</td>
            </tr>
            <tr>
                <td class="lab">NEXT TERM BEGINS:</td><td class="val" colspan="2">{{ $date($d['nextBegins']) }}</td>
                <td class="lab">NEXT TERM ENDS:</td><td class="val" colspan="2">{{ $date($d['nextEnds']) }}</td>
            </tr>
            <tr>
                <td class="lab">ATTENDANCE OBTAINED:</td><td class="val" colspan="2">{{ $d['attendance'] }}</td>
                <td class="lab">OUT OF:</td><td class="val" colspan="2">{{ $d['outOf'] }}</td>
            </tr>
        </table>
    </div>

    <table class="scores" style="margin-top: 8px;">
        <thead>
            <tr>
                <th style="text-align:left;">Subject</th>
                <th>Class score<br>30%</th>
                <th>Exams score<br>70%</th>
                <th>Total score<br>100%</th>
                <th>Position</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($card['rows'] as $row)
                <tr class="c">
                    <td style="text-align:left;">{{ $row['subject'] }}</td>
                    <td>{{ $row['classScore'] }}</td>
                    <td>{{ $row['examScore'] }}</td>
                    <td><strong>{{ $row['total'] }}</strong></td>
                    <td>{{ $row['position'] }}</td>
                    <td>{{ $row['remark'] }}</td>
                </tr>
            @endforeach
            <tr class="c total">
                <td style="text-align:left;">TOTAL</td>
                <td></td>
                <td></td>
                <td>{{ $card['grandTotal'] }}</td>
                <td>{{ $card['overallPosition'] ? $card['overallPosition'].' of '.$card['classSize'] : '' }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="block">
        <table class="plain">
            <tr><td class="lab">Conduct:</td><td class="val">{{ $d['conduct'] }}</td></tr>
            <tr><td class="lab">Attitude:</td><td class="val">{{ $d['attitude'] }}</td></tr>
            <tr><td class="lab">Class Teacher’s Remarks:</td><td class="val">{{ $d['teacherRemarks'] }}</td></tr>
        </table>
    </div>

    <div class="block">
        <table class="plain" style="width: 66%;">
            <tr><td class="lab">School Fees Arrears GH¢:</td><td class="val">{{ $money($d['arrears']) }}</td></tr>
            <tr><td class="lab">Next Term’s Fee GH¢:</td><td class="val">{{ $money($d['nextFee']) }}</td></tr>
            <tr><td class="lab">Total Amount Due GH¢:</td><td class="val">{{ $hasFees ? number_format($due, 2) : '' }}</td></tr>
        </table>
    </div>

    <div class="block">
        <table class="plain">
            <tr><td class="lab">Head Teacher’s Remarks:</td><td class="val">{{ $d['headRemarks'] }}</td></tr>
        </table>
    </div>
</body>
</html>