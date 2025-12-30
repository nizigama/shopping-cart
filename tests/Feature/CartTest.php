<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->product = Product::factory()->create(['price' => '29.99']);
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

    test('adding same product increments quantity', function () {
        // Add first time
        $this->actingAs($this->user)->post(route('cart.store', $this->product));

        // Add second time
        $this->actingAs($this->user)->post(route('cart.store', $this->product));

        $cart = Cart::where('user_id', $this->user->id)->first();
        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $this->product->id)
            ->first();

        expect($cartItem->quantity)->toBe(2);
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

    test('can update cart item quantity', function () {
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->patch(route('cart.update', $cartItem), ['quantity' => 5]);

        $response->assertRedirect();

        $cartItem->refresh();
        expect($cartItem->quantity)->toBe(5);
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

    test('can remove item from cart', function () {
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('cart.destroy', $cartItem));

        $response->assertRedirect();

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);
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

    test('can clear all items from cart', function () {
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        $product2 = Product::factory()->create();

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
        ]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product2->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('cart.clear'));

        $response->assertRedirect();

        expect(CartItem::where('cart_id', $cart->id)->count())->toBe(0);
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

