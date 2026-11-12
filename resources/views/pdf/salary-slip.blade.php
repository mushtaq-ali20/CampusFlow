<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Salary slip') }}</title>
    @include('pdf.partials.styles')
    <style>
        .net { width: 100%; margin-top: 6mm; }
        .net td { border: 0.2mm solid #999999; padding: 3mm; font-size: 13pt; font-weight: bold; }
        .signatures { width: 100%; margin-top: 18mm; }
        .signatures td { width: 33%; text-align: center; padding-top: 2mm; border-top: 0.2mm solid #999999; }
    </style>
</head>
<body>
@foreach ($slips as $slip)
    @if (! $loop->first)
        <pagebreak />
    @endif

    @php
        $money = fn (int $amount): string => \App\Services\FeeService::money($amount);
        $allowances = $slip->items->where('kind', \App\Enums\PayItemKind::Earning);
        $deductions = $slip->items->where('kind', \App\Enums\PayItemKind::Deduction);
    @endphp

    @include('pdf.partials.header')

    <h1>{{ __('Salary slip') }} · {{ $run->monthLabel() }}</h1>

    <table class="details">
        <tr>
            <td class="label">{{ __('Employee') }}</td>
            <td><strong>{{ $slip->employee->user->name }}</strong></td>
            <td class="label">{{ __('Employee No.') }}</td>
            <td>{{ $slip->employee->employee_code }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Designation') }}</td>
            <td>{{ $slip->employee->designation }}</td>
            <td class="label">{{ __('Branch') }}</td>
            <td>{{ $slip->employee->campus?->name }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Slip No.') }}</td>
            <td>{{ $slip->slip_number }}</td>
            <td class="label">{{ __('Paid on') }}</td>
            <td>{{ $slip->paid_on ? $slip->paid_on->format('d M Y').' ('.$slip->method?->getLabel().')' : '—' }}</td>
        </tr>
    </table>

    <table class="grid">
        <tr>
            <th>{{ __('Earnings') }}</th>
            <th class="number">{{ __('Amount') }}</th>
            <th>{{ __('Deductions') }}</th>
            <th class="number">{{ __('Amount') }}</th>
        </tr>
        @php
            $rowCount = max($allowances->count() + 1, $deductions->count() + ($slip->unpaid_deduction > 0 ? 1 : 0), 1);
        @endphp
        @for ($i = 0; $i < $rowCount; $i++)
            @php
                $earning = $i === 0 ? ['name' => __('Basic salary'), 'amount' => $slip->basic_salary] : $allowances->values()->get($i - 1)?->only(['name', 'amount']);
                $deduction = $deductions->values()->get($i)?->only(['name', 'amount']);

                if ($deduction === null && $slip->unpaid_deduction > 0 && $i === $deductions->count()) {
                    $deduction = ['name' => __('Unpaid leave (:days days)', ['days' => $slip->unpaid_days]), 'amount' => $slip->unpaid_deduction];
                }
            @endphp
            <tr>
                <td>{{ $earning['name'] ?? '' }}</td>
                <td class="number">{{ $earning ? $money($earning['amount']) : '' }}</td>
                <td>{{ $deduction['name'] ?? '' }}</td>
                <td class="number">{{ $deduction ? $money($deduction['amount']) : '' }}</td>
            </tr>
        @endfor
        <tr>
            <th>{{ __('Total earnings') }}</th>
            <th class="number">{{ $money($slip->basic_salary + $slip->allowances_total) }}</th>
            <th>{{ __('Total deductions') }}</th>
            <th class="number">{{ $money($slip->deductions_total + $slip->unpaid_deduction) }}</th>
        </tr>
    </table>

    <table class="net">
        <tr>
            <td>{{ __('Net salary') }}</td>
            <td style="text-align: right;">{{ $money($slip->net_salary) }}</td>
        </tr>
    </table>

    <table class="signatures">
        <tr>
            <td>{{ __('Prepared by') }}</td>
            <td>{{ __('Accountant') }}</td>
            <td>{{ __('Received by') }}</td>
        </tr>
    </table>
@endforeach
</body>
</html>
