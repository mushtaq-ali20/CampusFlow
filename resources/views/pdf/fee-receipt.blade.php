<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Fee receipt') }} {{ $payment->receipt_number }}</title>
    <style>
        body { font-family: dejavusans; font-size: 10pt; color: #222222; }
        h1 { font-size: 15pt; margin: 0; text-align: center; }
        .branch { text-align: center; font-size: 9pt; color: #555555; margin-bottom: 2mm; }
        .title { text-align: center; font-size: 12pt; font-weight: bold; margin: 3mm 0; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 1mm 0; vertical-align: top; }
        .items { margin-top: 3mm; }
        .items th { background: #eeeeee; text-align: left; padding: 1.5mm; border: 0.2mm solid #999999; }
        .items td { padding: 1.5mm; border: 0.2mm solid #999999; }
        .right { text-align: right; }
        .total td { font-weight: bold; }
        .signatures { margin-top: 12mm; }
        .signatures td { width: 50%; text-align: center; padding-top: 6mm; }
    </style>
</head>
<body>
    <h1>{{ $institution }}</h1>
    <div class="branch">
        {{ $payment->campus?->name }}@if ($payment->campus?->address), {{ $payment->campus->address }}@endif
        @if ($payment->campus?->phone) &middot; {{ $payment->campus->phone }}@endif
    </div>
    <div class="title">{{ __('Fee receipt') }}</div>

    <table class="meta">
        <tr>
            <td><strong>{{ __('Receipt No.') }}:</strong> {{ $payment->receipt_number }}</td>
            <td class="right"><strong>{{ __('Date') }}:</strong> {{ $payment->paid_on->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('Student') }}:</strong> {{ $voucher->admission->student->name }}</td>
            <td class="right"><strong>{{ __('Admission No.') }}:</strong> {{ $voucher->admission->admission_number }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('Class') }}:</strong> {{ $voucher->schoolClass->name }} - {{ $voucher->section->name }}</td>
            <td class="right"><strong>{{ __('Fee month') }}:</strong> {{ $voucher->month->translatedFormat('F Y') }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('Voucher') }}:</strong> {{ $voucher->voucher_number }}</td>
            <td class="right"><strong>{{ __('Payment method') }}:</strong> {{ $payment->method->getLabel() }}@if ($payment->reference) ({{ $payment->reference }})@endif</td>
        </tr>
    </table>

    <table class="items">
        <tr>
            <th>{{ __('Description') }}</th>
            <th class="right">{{ __('Amount') }}</th>
        </tr>
        @foreach ($voucher->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="right">{{ $item->kind->value === 'discount' ? '- ' : '' }}{{ \App\Services\FeeService::money($item->amount) }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td class="right">{{ __('Total payable') }}</td>
            <td class="right">{{ \App\Services\FeeService::money($voucher->net_amount) }}</td>
        </tr>
        <tr class="total">
            <td class="right">{{ __('Paid now') }}</td>
            <td class="right">{{ \App\Services\FeeService::money($payment->amount) }}</td>
        </tr>
        <tr class="total">
            <td class="right">{{ __('Balance') }}</td>
            <td class="right">{{ \App\Services\FeeService::money($voucher->balance) }}</td>
        </tr>
    </table>

    <table class="signatures">
        <tr>
            <td>{{ __('Collected by') }}: {{ $payment->collector?->name }}</td>
            <td>{{ __('Signature and stamp') }}</td>
        </tr>
    </table>
</body>
</html>
