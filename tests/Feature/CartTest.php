<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->product = Product::factory()->create(['price' => '29.99']);
    // Create stock for the product
    ProductStock::factory()->forProduct($this->product)->create(['quantity' => 10]);
});

describe('cart page', function () {
    test('guests are redirected to login', function () {
        $response = $this->get(route('cart.index'));
        $response->assertRedirect(route('login'));
    });

    test('authenticated users can view cart', function () {
        $response = $this->actingAs($this->user)->get(route('cart.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cart')
            ->has('items')
            ->has('total')
        );
    });

    test('cart displays items for authenticated user', function () {
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->user)->get(route('cart.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cart')
            ->has('items', 1)
            ->where('total', '59.98') // 29.99 * 2
        );
    });

    test('cart only shows items for current user', function () {
        $otherUser = User::factory()->create();
        $otherCart = Cart::factory()->create(['user_id' => $otherUser->id]);
        CartItem::factory()->create([
            'cart_id' => $otherCart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->user)->get(route('cart.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('items', 0)
        );
    });
});

describe('add to cart', function () {
    test('guests cannot add to cart', function () {
        $response = $this->post(route('cart.store', $this->product));
        $response->assertRedirect(route('login'));
    });

    test('can add product to cart', function () {
        $response = $this->actingAs($this->user)
            ->post(route('cart.store', $this->product));

        $response->assertRedirect();

        $this->assertDatabaseHas('carts', [
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);
    });

    test('adding product reduces stock', function () {
        $initialStock = $this->product->stock->quantity;

        $this->actingAs($this->user)->post(route('cart.store', $this->product));

        $this->product->stock->refresh();
        expect($this->product->stock->quantity)->toBe($initialStock - 1);
    });

    test('adding same product increments quantity and reduces stock', function () {
        $initialStock = $this->product->stock->quantity;

        // Add first time
        $this->actingAs($this->user)->post(route('cart.store', $this->product));

        // Add second time
        $this->actingAs($this->user)->post(route('cart.store', $this->product));

        $cart = Cart::where('user_id', $this->user->id)->first();
        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $this->product->id)
            ->first();

        expect($cartItem->quantity)->toBe(2);

        $this->product->stock->refresh();
        expect($this->product->stock->quantity)->toBe($initialStock - 2);
    });

    test('cannot add product with no stock', function () {
        $this->product->stock->update(['quantity' => 0]);

        $response = $this->actingAs($this->user)
            ->post(route('cart.store', $this->product));

        $response->assertSessionHasErrors('stock');

        $this->assertDatabaseMissing('cart_items', [
            'product_id' => $this->product->id,
        ]);
    });

    test('cannot add product without stock record', function () {
        $productWithoutStock = Product::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('cart.store', $productWithoutStock));

        $response->assertSessionHasErrors('stock');
    });

    test('creates cart if user does not have one', function () {
        expect(Cart::where('user_id', $this->user->id)->exists())->toBeFalse();

        $this->actingAs($this->user)->post(route('cart.store', $this->product));

        expect(Cart::where('user_id', $this->user->id)->exists())->toBeTrue();
    });

    test('uses existing cart if user has one', function () {
        $existingCart = Cart::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->post(route('cart.store', $this->product));

        expect(Cart::where('user_id', $this->user->id)->count())->toBe(1);

        $cartItem = CartItem::where('cart_id', $existingCart->id)->first();
        expect($cartItem)->not->toBeNull();
    });
});

describe('update cart item', function () {
    test('guests cannot update cart items', function () {
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $response = $this->patch(route('cart.update', $cartItem), ['quantity' => 5]);
        $response->assertRedirect(route('login'));
    });

    test('can increase cart item quantity with available stock', function () {
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);
        $initialStock = $this->product->stock->quantity;

        $response = $this->actingAs($this->user)
            ->patch(route('cart.update', $cartItem), ['quantity' => 5]);

        $response->assertRedirect();

        $cartItem->refresh();
        expect($cartItem->quantity)->toBe(5);

        // Stock should decrease by the difference (5 - 1 = 4)
        $this->product->stock->refresh();
        expect($this->product->stock->quantity)->toBe($initialStock - 4);
    });

    test('can decrease cart item quantity and stock is restored', function () {
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);
        $initialStock = $this->product->stock->quantity;

        $response = $this->actingAs($this->user)
            ->patch(route('cart.update', $cartItem), ['quantity' => 2]);

        $response->assertRedirect();

        $cartItem->refresh();
        expect($cartItem->quantity)->toBe(2);

        // Stock should increase by the difference (5 - 2 = 3)
        $this->product->stock->refresh();
        expect($this->product->stock->quantity)->toBe($initialStock + 3);
    });

    test('cannot increase quantity beyond available stock', function () {
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);
        $this->product->stock->update(['quantity' => 2]); // Only 2 more available

        $response = $this->actingAs($this->user)
            ->patch(route('cart.update', $cartItem), ['quantity' => 10]);

        $response->assertSessionHasErrors('stock');

        $cartItem->refresh();
        expect($cartItem->quantity)->toBe(1); // Unchanged
    });

    test('cannot update quantity to zero', function () {
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->patch(route('cart.update', $cartItem), ['quantity' => 0]);

        $response->assertSessionHasErrors('quantity');
    });

    test('cannot update quantity to negative', function () {
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->patch(route('cart.update', $cartItem), ['quantity' => -1]);

        $response->assertSessionHasErrors('quantity');
    });

    test('cannot update another users cart item', function () {
        $otherUser = User::factory()->create();
        $otherCart = Cart::factory()->create(['user_id' => $otherUser->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $otherCart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->patch(route('cart.update', $cartItem), ['quantity' => 5]);

        $response->assertForbidden();
    });
});

describe('remove cart item', function () {
    test('guests cannot remove cart items', function () {
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->delete(route('cart.destroy', $cartItem));
        $response->assertRedirect(route('login'));
    });

    test('can remove item from cart and stock is restored', function () {
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
        ]);
        $initialStock = $this->product->stock->quantity;

        $response = $this->actingAs($this->user)
            ->delete(route('cart.destroy', $cartItem));

        $response->assertRedirect();

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);

        // Stock should be restored
        $this->product->stock->refresh();
        expect($this->product->stock->quantity)->toBe($initialStock + 3);
    });

    test('cannot remove another users cart item', function () {
        $otherUser = User::factory()->create();
        $otherCart = Cart::factory()->create(['user_id' => $otherUser->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $otherCart->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('cart.destroy', $cartItem));

        $response->assertForbidden();

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
        ]);
    });
});

describe('clear cart', function () {
    test('guests cannot clear cart', function () {
        $response = $this->delete(route('cart.clear'));
        $response->assertRedirect(route('login'));
    });

    test('can clear all items from cart and stock is restored', function () {
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        $product2 = Product::factory()->create();
        ProductStock::factory()->forProduct($product2)->create(['quantity' => 20]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
        ]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product2->id,
            'quantity' => 5,
        ]);

        $initialStock1 = $this->product->stock->quantity;
        $initialStock2 = $product2->stock->quantity;

        $response = $this->actingAs($this->user)->delete(route('cart.clear'));

        $response->assertRedirect();

        expect(CartItem::where('cart_id', $cart->id)->count())->toBe(0);

        // Stock should be restored for both products
        $this->product->stock->refresh();
        $product2->stock->refresh();
        expect($this->product->stock->quantity)->toBe($initialStock1 + 3);
        expect($product2->stock->quantity)->toBe($initialStock2 + 5);
    });

    test('clearing cart does not affect other users carts', function () {
        $otherUser = User::factory()->create();
        $otherCart = Cart::factory()->create(['user_id' => $otherUser->id]);
        $otherCartItem = CartItem::factory()->create([
            'cart_id' => $otherCart->id,
            'product_id' => $this->product->id,
        ]);

        $myCart = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::factory()->create([
            'cart_id' => $myCart->id,
            'product_id' => $this->product->id,
        ]);

        $this->actingAs($this->user)->delete(route('cart.clear'));

        // Other user's cart item should still exist
        $this->assertDatabaseHas('cart_items', [
            'id' => $otherCartItem->id,
        ]);
    });

    test('clearing empty cart succeeds', function () {
        $response = $this->actingAs($this->user)->delete(route('cart.clear'));

        $response->assertRedirect();
    });
});
