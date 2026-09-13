<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\BakongService;

class BakongPaymentController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $amount = $request->amount;

        $billNumber = 'INV-' . strtoupper(
            Str::random(10)
        );

        return response()->json([
            'success' => true,
            'bill_number' => $billNumber,
            'amount' => $amount,
        ]);
    }
}