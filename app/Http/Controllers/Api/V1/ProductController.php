<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', 12);
        if ($perPage < 1) {
            $perPage = 12;
        }

        $query = Product::query();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('scientific_name', 'like', "%{$search}%");
            });
        }

        foreach (['type', 'care_level', 'sunlight', 'watering', 'condition', 'size'] as $field) {
            if ($value = $request->string($field)->toString()) {
                $query->where($field, $value);
            }
        }

        if ($request->filled('in_stock')) {
            $query->where('in_stock', filter_var($request->query('in_stock'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('is_rare')) {
            $query->where('is_rare', filter_var($request->query('is_rare'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->query('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->query('max_price'));
        }

        $query->latest('updated_at');

        return ProductResource::collection($query->paginate($perPage));
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }
}
