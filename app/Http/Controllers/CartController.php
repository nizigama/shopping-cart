<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    /**
     * Display the user's cart.
     */
    public function index(Request $request): Response
    {
        $cart = Cart::where('user_id', $request->user()->id)
            ->with('items.product.stock')
            ->first();

        $items = $cart ? $cart->items : collect();
        $total = $items->sum(fn ($item) => $item->quantity * $item->product->price);

        return Inertia::render('Cart', [
            'items' => $items,
            'total' => number_format($total, 2),
        ]);
    }

    /**
     * Add a product to the cart.
     */
    public function store(Request $request, Product $product): RedirectResponse
    {
        // Check if product has stock available
        $stock = $product->stock;

        if (!$stock || $stock->quantity < 1) {
            return back()->withErrors(['stock' => 'This product is out of stock.']);
        }

        DB::transaction(function () use ($request, $product, $stock) {
            $cart = Cart::firstOrCreate([
                'user_id' => $request->user()->id,
            ]);

            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->first();

            if ($cartItem) {
                $cartItem->increment('quantity');
            } else {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]);
            }

            // Reduce stock
            $stock->decrement('quantity');
        });

        return back();
    }

    /**
     * Update the quantity of a cart item.
     */
    public function update(Request $request, CartItem $item): RedirectResponse
    {
        // Ensure the item belongs to the current user's cart
        if ($item->cart->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $newQuantity = $validated['quantity'];
        $currentQuantity = $item->quantity;
        $difference = $newQuantity - $currentQuantity;

        // If increasing quantity, check stock
        if ($difference > 0) {
            $stock = $item->product->stock;

            if (!$stock || $stock->quantity < $difference) {
                return back()->withErrors(['stock' => 'Not enough stock available.']);
            }

            DB::transaction(function () use ($item, $newQuantity, $stock, $difference) {
                $item->update(['quantity' => $newQuantity]);
                $stock->decrement('quantity', $difference);
            });
        } else {
            // Decreasing quantity, restore stock
            DB::transaction(function () use ($item, $newQuantity, $difference) {
                $item->update(['quantity' => $newQuantity]);
                $item->product->stock?->increment('quantity', abs($difference));
            });
        }

        return back();
    }

    /**
     * Remove a cart item.
     */
    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        // Ensure the item belongs to the current user's cart
        if ($item->cart->user_id !== $request->user()->id) {
            abort(403);
        }

        DB::transaction(function () use ($item) {
            $quantity = $item->quantity;

            // Restore stock
            $item->product->stock?->increment('quantity', $quantity);

            $item->delete();
        });

        return back();
    }

    /**
     * Clear all items from the cart.
     */
    public function clear(Request $request): RedirectResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)
            ->with('items.product.stock')
            ->first();

        if ($cart) {
            DB::transaction(function () use ($cart) {
                // Restore stock for all items
                foreach ($cart->items as $item) {
                    $item->product->stock?->increment('quantity', $item->quantity);
                }

                $cart->items()->delete();
            });
        }

        return back();
    }
}
