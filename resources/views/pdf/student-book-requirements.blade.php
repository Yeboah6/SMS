<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Book Requirements</title>
    <style>
        body { color: #0f172a; font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { color: #115e59; font-size: 20px; margin-bottom: 4px; }
        .muted { color: #64748b; margin: 0 0 18px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 9px 7px; text-align: left; }
        th { background: #f1f5f9; color: #334155; font-size: 10px; text-transform: uppercase; }
        .notes { color: #64748b; font-size: 9px; }
        .empty { background: #f8fafc; color: #64748b; padding: 14px; }
    </style>
</head>
<body>
    <h1>{{ $schoolName }} · Book Requirements</h1>
    <p class="muted">
        {{ trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name]))) }}
        · {{ $student->admission_number }} · {{ $bookRequirements['className'] }}
        · {{ $bookRequirements['academicYear'] }}
    </p>

    @if (count($bookRequirements['items']) === 0)
        <p class="empty">No book requirements are set for this class.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Book / item</th>
                    <th>Quantity</th>
                    <th>Term</th>
                    <th>Purchased</th>
                    <th>Still needed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bookRequirements['items'] as $item)
                    <tr>
                        <td>
                            {{ $item['itemName'] }}
                            @unless ($item['isRequired']) (recommended) @endunless
                            @if ($item['notes'])
                                <div class="notes">{{ $item['notes'] }}</div>
                            @endif
                        </td>
                        <td>{{ $item['quantity'] }} {{ $item['unit'] }}</td>
                        <td>{{ $item['termName'] ?: 'Full year' }}</td>
                        <td>{{ $item['purchased'] }}</td>
                        <td>{{ $item['outstanding'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
