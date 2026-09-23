<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = $request->session()->get('products', []);

        return response()->json(array_values($products));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $products = $request->session()->get('products', []);

        if (!isset($products[$id])) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json($products[$id]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'nullable|numeric|min:0',
        ]);

        $products = $request->session()->get('products', []);

        $id = $products ? (max(array_keys($products)) + 1) : 1;

        $newProduct = [
            'id'          => $id,
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? '',
            'price'       => $validated['price'] ?? 0,
        ];

        $products[$id] = $newProduct;
        $request->session()->put('products', $products);

        return response()->json($newProduct, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $products = $request->session()->get('products', []);

        if (!isset($products[$id])) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'nullable|numeric|min:0',
        ]);

        $products[$id] = [
            'id'          => $id,
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? '',
            'price'       => $validated['price'] ?? 0,
        ];

        $request->session()->put('products', $products);

        return response()->json($products[$id]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $products = $request->session()->get('products', []);

        if (!isset($products[$id])) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        unset($products[$id]);
        $request->session()->put('products', $products);

        return response()->json(null, 204);
    }
}
