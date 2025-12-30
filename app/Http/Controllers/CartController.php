<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->with('items.product')
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

        $item->update([
            'quantity' => $validated['quantity'],
        ]);

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

        $item->delete();

        return back();
    }

    /**
     * Clear all items from the cart.
     */
    public function clear(Request $request): RedirectResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();

        if ($cart) {
            $cart->items()->delete();
        }

        return back();
    }
}

