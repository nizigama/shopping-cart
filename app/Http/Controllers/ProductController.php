<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Display the products listing page (Dashboard).
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        $products = Product::query()
            ->with('stock')
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Dashboard', [
            'products' => $products,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
