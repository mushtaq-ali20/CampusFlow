<?php

namespace App\Services;

use App\Models\FeePayment;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The fee receipt of a payment, as a PDF.
 */
class ReceiptService
{
    public function download(FeePayment $payment): StreamedResponse
    {
        $pdf = $this->render($payment);

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf;
        }, 'receipt-'.Str::slug($payment->receipt_number ?? (string) $payment->id).'.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function render(FeePayment $payment): string
    {
        $payment->loadMissing(['voucher.items', 'voucher.admission.student', 'voucher.schoolClass', 'voucher.section', 'campus', 'collector']);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A5',
            'orientation' => 'L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'tempDir' => storage_path('framework/cache/mpdf'),
        ]);

        $mpdf->WriteHTML(view('pdf.fee-receipt', [
            'payment' => $payment,
            'voucher' => $payment->voucher,
            'institution' => config('campusflow.company_name') ?: config('app.name'),
        ])->render());

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
