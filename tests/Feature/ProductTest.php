<?php

use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('products listing', function () {
    test('guests are redirected to login', function () {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    });

    test('authenticated users can view products', function () {
        Product::factory(5)->create();

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('products.data', 5)
            ->has('filters')
        );
    });

    test('products are paginated', function () {
        Product::factory(15)->create();

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('products.data', 12) // 12 per page
            ->where('products.total', 15)
            ->where('products.last_page', 2)
        );
    });

    test('can navigate to second page', function () {
        Product::factory(15)->create();

        $response = $this->actingAs($this->user)->get(route('dashboard', ['page' => 2]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('products.data', 3) // remaining 3 products
            ->where('products.current_page', 2)
        );
    });
});

describe('product search', function () {
    test('can search products by name', function () {
        Product::factory()->create(['name' => 'Apple iPhone']);
        Product::factory()->create(['name' => 'Samsung Galaxy']);
        Product::factory()->create(['name' => 'Apple Watch']);

        $response = $this->actingAs($this->user)->get(route('dashboard', ['search' => 'Apple']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('products.data', 2)
            ->where('filters.search', 'Apple')
        );
    });

    test('search is case insensitive', function () {
        Product::factory()->create(['name' => 'Apple iPhone']);
        Product::factory()->create(['name' => 'Samsung Galaxy']);

        $response = $this->actingAs($this->user)->get(route('dashboard', ['search' => 'apple']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
        );
    });

    test('empty search returns all products', function () {
        Product::factory(5)->create();

        $response = $this->actingAs($this->user)->get(route('dashboard', ['search' => '']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('products.data', 5)
        );
    });

    test('search with no results returns empty data', function () {
        Product::factory(3)->create();

        $response = $this->actingAs($this->user)->get(route('dashboard', ['search' => 'nonexistent']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('products.data', 0)
            ->where('products.total', 0)
        );
    });

    test('search preserves pagination', function () {
        // Create 15 products with "Test" in name
        Product::factory(15)->create(['name' => fn () => 'Test Product ' . fake()->unique()->numberBetween(1, 100)]);
        Product::factory(5)->create(['name' => 'Other Item']);

        $response = $this->actingAs($this->user)->get(route('dashboard', ['search' => 'Test', 'page' => 2]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('products.current_page', 2)
            ->where('products.total', 15)
        );
    });
});

