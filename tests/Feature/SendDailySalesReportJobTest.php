<?php

use App\Jobs\SendDailySalesReportJob;
use App\Mail\DailySalesReport;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

describe('SendDailySalesReportJob', function () {
    test('sends daily sales report email', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '10.00']);
        ProductStock::factory()->forProduct($product)->create(['quantity' => 100]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        SendDailySalesReportJob::dispatch();

        Mail::assertSent(DailySalesReport::class, function ($mail) {
            return $mail->hasTo(config('administration.admin_email'));
        });
    });

    test('sends report even when no sales today', function () {
        SendDailySalesReportJob::dispatch();

        Mail::assertSent(DailySalesReport::class, function ($mail) {
            return $mail->salesData->isEmpty();
        });
    });

    test('includes cart items created today', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Today Product', 'price' => '25.00']);
        ProductStock::factory()->forProduct($product)->create(['quantity' => 100]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        SendDailySalesReportJob::dispatch();

        Mail::assertSent(DailySalesReport::class, function ($mail) use ($product) {
            return $mail->salesData->count() === 1
                && $mail->salesData->first()['product']->id === $product->id
                && $mail->salesData->first()['quantity'] === 3;
        });
    });

    test('excludes cart items from previous days', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '10.00']);
        ProductStock::factory()->forProduct($product)->create(['quantity' => 100]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        // Backdate the cart item to yesterday using query builder to avoid auto-updating timestamps
        CartItem::where('id', $cartItem->id)->update([
            'created_at' => Carbon::yesterday(),
            'updated_at' => Carbon::yesterday(),
        ]);

        SendDailySalesReportJob::dispatch();

        Mail::assertSent(DailySalesReport::class, function ($mail) {
            return $mail->salesData->isEmpty();
        });
    });

    test('aggregates quantities for same product', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $product = Product::factory()->create(['price' => '15.00']);
        ProductStock::factory()->forProduct($product)->create(['quantity' => 100]);

        $cart1 = Cart::factory()->create(['user_id' => $user1->id]);
        $cart2 = Cart::factory()->create(['user_id' => $user2->id]);

        CartItem::factory()->create([
            'cart_id' => $cart1->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart2->id,
            'product_id' => $product->id,
            'quantity' => 7,
        ]);

        SendDailySalesReportJob::dispatch();

        Mail::assertSent(DailySalesReport::class, function ($mail) {
            return $mail->salesData->count() === 1
                && $mail->salesData->first()['quantity'] === 10;
        });
    });

    test('calculates grand total correctly', function () {
        $user = User::factory()->create();

        $product1 = Product::factory()->create(['price' => '10.00']);
        $product2 = Product::factory()->create(['price' => '20.00']);
        ProductStock::factory()->forProduct($product1)->create(['quantity' => 100]);
        ProductStock::factory()->forProduct($product2)->create(['quantity' => 100]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product1->id,
            'quantity' => 2, // 2 * 10 = 20
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product2->id,
            'quantity' => 3, // 3 * 20 = 60
        ]);

        SendDailySalesReportJob::dispatch();

        Mail::assertSent(DailySalesReport::class, function ($mail) {
            return $mail->grandTotal == 80.00;
        });
    });

    test('uses admin email from config', function () {
        config(['administration.admin_email' => 'reports@example.com']);

        SendDailySalesReportJob::dispatch();

        Mail::assertSent(DailySalesReport::class, function ($mail) {
            return $mail->hasTo('reports@example.com');
        });
    });

    test('report date is today', function () {
        SendDailySalesReportJob::dispatch();

        Mail::assertSent(DailySalesReport::class, function ($mail) {
            return $mail->reportDate->isToday();
        });
    });
});

