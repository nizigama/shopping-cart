<?php

namespace App\Jobs;

use App\Mail\LowStockAlert;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class CheckLowStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Product $product
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Refresh the stock relationship to get the latest value from the database
        $stock = $this->product->stock()->first();

        $threshold = config('administration.low_stock_threshold');
        $adminEmail = config('administration.admin_email');

        if ($stock && $stock->quantity < $threshold) {
            Mail::to($adminEmail)
                ->send(new LowStockAlert($this->product));
        }
    }
}

