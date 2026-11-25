<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    /** Categorías activas del menú, en orden. */
    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::where('active', true)->orderBy('position')->orderBy('name')->get()
        );
    }

    /**
     * Platillos del menú con sus modificadores.
     *
     * Incluye los agotados (`available: false`) para mostrarlos deshabilitados.
     */
    public function products(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'category_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:80'],
        ]);
        $search = $request->string('search')->trim()->value();

        $products = Product::query()
            ->with('modifiers')
            ->whereHas('category', fn ($q) => $q->where('active', true))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->get();

        return ProductResource::collection($products);
    }
}
