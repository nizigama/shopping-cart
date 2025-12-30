<?php

use App\Jobs\CheckLowStockJob;
use App\Mail\LowStockAlert;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

describe('CheckLowStockJob', function () {
    test('sends email when stock is below threshold', function () {
        $product = Product::factory()->create();
        ProductStock::factory()->forProduct($product)->create(['quantity' => 3]);

        CheckLowStockJob::dispatch($product);

        Mail::assertSent(LowStockAlert::class, function ($mail) {
            return $mail->hasTo(config('administration.admin_email'));
        });
    });

    test('does not send email when stock is at threshold', function () {
        $product = Product::factory()->create();
        ProductStock::factory()->forProduct($product)->create(['quantity' => 5]);

        CheckLowStockJob::dispatch($product);

        Mail::assertNotSent(LowStockAlert::class);
    });

    test('does not send email when stock is above threshold', function () {
        $product = Product::factory()->create();
        ProductStock::factory()->forProduct($product)->create(['quantity' => 10]);

        CheckLowStockJob::dispatch($product);

        Mail::assertNotSent(LowStockAlert::class);
    });

    test('does not send email when product has no stock record', function () {
        $product = Product::factory()->create();

        CheckLowStockJob::dispatch($product);

        Mail::assertNotSent(LowStockAlert::class);
    });

    test('uses admin email from config', function () {
        config(['administration.admin_email' => 'custom@example.com']);

        $product = Product::factory()->create();
        ProductStock::factory()->forProduct($product)->create(['quantity' => 2]);

        CheckLowStockJob::dispatch($product);

        Mail::assertSent(LowStockAlert::class, function ($mail) {
            return $mail->hasTo('custom@example.com');
        });
    });

    test('uses threshold from config', function () {
        config(['administration.low_stock_threshold' => 10]);

        $product = Product::factory()->create();
        ProductStock::factory()->forProduct($product)->create(['quantity' => 8]);

        CheckLowStockJob::dispatch($product);

        Mail::assertSent(LowStockAlert::class);
    });

    test('email contains product information', function () {
        $product = Product::factory()->create(['name' => 'Test Product']);
        ProductStock::factory()->forProduct($product)->create(['quantity' => 2]);

        CheckLowStockJob::dispatch($product);

        Mail::assertSent(LowStockAlert::class, function ($mail) use ($product) {
            return $mail->product->id === $product->id;
        });
    });
});

