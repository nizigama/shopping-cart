<?php

namespace App\Jobs;

use App\Mail\DailySalesReport;
use App\Models\CartItem;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendDailySalesReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $today = Carbon::today();

        // Get all cart items added or updated today, grouped by product
        $salesData = CartItem::whereDate('created_at', $today)
            ->orWhereDate('updated_at', $today)
            ->with('product')
            ->get()
            ->groupBy('product_id')
            ->map(function ($items) {
                $product = $items->first()->product;
                $totalQuantity = $items->sum('quantity');

                return [
                    'product' => $product,
                    'quantity' => $totalQuantity,
                    'total' => $totalQuantity * $product->price,
                ];
            })
            ->values();

        $grandTotal = $salesData->sum('total');
        $adminEmail = config('administration.admin_email');

        Mail::to($adminEmail)
            ->send(new DailySalesReport($salesData, $grandTotal, $today));
    }
}

