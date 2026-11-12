<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Result card') }}</title>
    @include('pdf.partials.styles')
    <style>
        .summary { width: 100%; margin-top: 6mm; }
        .summary td { border: 0.2mm solid #999999; padding: 2mm; text-align: center; }
        .summary .value { font-size: 13pt; font-weight: bold; }
        .fail { color: #b3261e; }
        .signatures { width: 100%; margin-top: 18mm; }
        .signatures td { width: 33%; text-align: center; padding-top: 2mm; border-top: 0.2mm solid #999999; }
    </style>
</head>
<body>
@foreach ($rows as $row)
    @if (! $loop->first)
        <pagebreak />
    @endif

    @include('pdf.partials.header')

    <h1>{{ __('Result card') }} · {{ $exam->getLabel() }}</h1>

    <table class="details">
        <tr>
            <td class="label">{{ __('Student') }}</td>
            <td><strong>{{ $row['name'] }}</strong></td>
            <td class="label">{{ __('Admission No.') }}</td>
            <td>{{ $row['admission']->admission_number }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Class') }}</td>
            <td>{{ $section->full_name }}</td>
            <td class="label">{{ __('Academic Year') }}</td>
            <td>{{ $section->year?->name }}</td>
        </tr>
    </table>

    <table class="grid">
        <tr>
            <th>{{ __('Subject') }}</th>
            <th class="number">{{ __('Total') }}</th>
            <th class="number">{{ __('Passing') }}</th>
            <th class="number">{{ __('Obtained') }}</th>
            @if ($weight > 0)
                <th class="number">{{ __('Class assessments') }}</th>
            @endif
            <th class="number">%</th>
            <th>{{ __('Grade') }}</th>
        </tr>
        @foreach ($subjects as $subject)
            @php($cell = $row['subjects'][$subject['id']])
            <tr>
                <td>{{ $subject['name'] }}</td>
                <td class="number">{{ $subject['total'] }}</td>
                <td class="number">{{ $subject['passing'] }}</td>
                <td class="number {{ $cell['passed'] === false ? 'fail' : '' }}">{{ $cell['marks'] !== null ? rtrim(rtrim(number_format($cell['marks'], 2), '0'), '.') : '—' }}</td>
                @if ($weight > 0)
                    <td class="number">{{ $cell['formative'] !== null ? $cell['formative'].'%' : '—' }}</td>
                @endif
                <td class="number">{{ $cell['percentage'] !== null ? $cell['percentage'].'%' : '—' }}</td>
                <td>{{ $grading->gradeFor($cell['percentage']) ?? '—' }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td>{{ __('Total') }}</td>
            <td class="number">{{ $row['total'] }}</td>
            <td></td>
            <td class="number">{{ rtrim(rtrim(number_format($row['obtained'], 2), '0'), '.') }}</td>
            @if ($weight > 0)
                <td></td>
            @endif
            <td class="number">{{ $row['percentage'] !== null ? $row['percentage'].'%' : '—' }}</td>
            <td>{{ $row['grade'] ?? '—' }}</td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td>{{ __('Percentage') }}<br><span class="value">{{ $row['percentage'] !== null ? $row['percentage'].'%' : '—' }}</span></td>
            <td>{{ __('Grade') }}<br><span class="value">{{ $row['grade'] ?? '—' }}</span></td>
            <td>{{ __('Position') }}<br><span class="value">{{ $row['position'] ? $row['position'].' / '.$rows->whereNotNull('position')->count() : '—' }}</span></td>
            <td>{{ __('Result') }}<br><span class="value {{ $row['result'] === 'fail' ? 'fail' : '' }}">{{ \App\Services\ResultService::resultLabel($row['result']) }}</span></td>
            <td>{{ __('Attendance') }}<br><span class="value">{{ $row['attendance']['percentage'] !== null ? $row['attendance']['percentage'].'%' : '—' }}</span></td>
        </tr>
    </table>

    @if ($weight > 0)
        <p class="muted">{{ __('Each subject counts the exam for :exam% and the class assessments of the term for :weight%.', ['exam' => 100 - $weight, 'weight' => $weight]) }}</p>
    @endif

    <table class="signatures">
        <tr>
            <td>{{ __('Class teacher') }}</td>
            <td>{{ __('Principal') }}</td>
            <td>{{ __('Parent') }}</td>
        </tr>
    </table>
@endforeach
</body>
</html>
