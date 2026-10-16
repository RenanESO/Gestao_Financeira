<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Models\TitleSettlement;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReceiptController extends Controller
{
    public function store(TitleSettlement $settlement, ReceiptService $service): JsonResponse
    {
        $recibo = $service->generate($settlement);

        return response()->json(['id' => $recibo->id, 'receipt_number' => $recibo->receipt_number,
            'download_url' => route('receipts.download', $recibo)]);
    }

    public function download(Receipt $receipt, ReceiptService $service): Response
    {
        return $service->download($receipt);
    }
}
