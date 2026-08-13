<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of products with search, filter, and pagination (Public).
     */
    public function index(Request $request)
    {
        $query = Product::with(['categories', 'variants', 'images'])
            ->where('is_active', true);

        // Filter: Pencarian Nama / Deskripsi
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter: Kategori (ID atau Slug)
        if ($request->filled('category')) {
            $catParam = $request->category;
            $query->whereHas('categories', function ($q) use ($catParam) {
                $q->where('id', $catParam)->orWhere('slug', $catParam);
            });
        }

        // Filter: Rentang Harga
        if ($request->filled('min_price')) {
            $query->where('base_price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('base_price', '<=', $request->max_price);
        }

        // Sorting
        $sort = $request->get('sort', 'latest');
        switch ($sort) {
            case 'price_low':
                $query->orderBy('base_price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('base_price', 'desc');
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            default:
                $query->latest();
                break;
        }

        $limit = $request->get('per_page', 12);
        $products = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Display the specified product details (Public).
     */
    public function show($idOrSlug)
    {
        $product = Product::where('id', $idOrSlug)
            ->orWhere('slug', $idOrSlug)
            ->with(['categories', 'variants', 'images'])
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Store a newly created product (Admin / Warehouse).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'weight_grams' => 'required|integer|min:0',
            'category_ids' => 'required|array',
            'category_ids.*' => 'exists:categories,id',
            'variants' => 'required|array|min:1',
            'variants.*.sku' => 'required|string|distinct',
            'variants.*.name' => 'required|string',
            'variants.*.additional_price' => 'nullable|numeric|min:0',
            'variants.*.stock' => 'required|integer|min:0',
            'variants.*.image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*.image_url' => 'required|string',
            'images.*.is_primary' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $product = Product::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name) . '-' . Str::random(5),
                'description' => $request->description,
                'base_price' => $request->base_price,
                'weight_grams' => $request->weight_grams,
                'is_active' => $request->get('is_active', true),
            ]);

            $product->categories()->sync($request->category_ids);

            foreach ($request->variants as $variantData) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $variantData['sku'],
                    'name' => $variantData['name'],
                    'additional_price' => $variantData['additional_price'] ?? 0,
                    'stock' => $variantData['stock'],
                    'image' => $variantData['image'] ?? null,
                ]);
            }

            if ($request->has('images')) {
                foreach ($request->images as $imageData) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_url' => $imageData['image_url'],
                        'is_primary' => $imageData['is_primary'] ?? false,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil ditambahkan.',
                'data' => $product->load(['categories', 'variants', 'images']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat produk: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update specified product (Admin / Warehouse).
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'base_price' => 'sometimes|required|numeric|min:0',
            'weight_grams' => 'sometimes|required|integer|min:0',
            'is_active' => 'boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('name')) {
            $product->name = $request->name;
            $product->slug = Str::slug($request->name) . '-' . Str::random(5);
        }
        if ($request->has('description')) $product->description = $request->description;
        if ($request->has('base_price')) $product->base_price = $request->base_price;
        if ($request->has('weight_grams')) $product->weight_grams = $request->weight_grams;
        if ($request->has('is_active')) $product->is_active = $request->is_active;

        $product->save();

        if ($request->has('category_ids')) {
            $product->categories()->sync($request->category_ids);
        }

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diperbarui.',
            'data' => $product->load(['categories', 'variants', 'images']),
        ]);
    }

    /**
     * Delete product (Admin).
     */
    public function destroy($id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan.',
            ], 404);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus.',
        ]);
    }

    /**
     * Alert Stok Menipis (Admin / Warehouse).
     */
    public function lowStock(Request $request)
    {
        $threshold = $request->get('threshold', 10);

        $lowStockVariants = ProductVariant::with('product')
            ->where('stock', '<=', $threshold)
            ->orderBy('stock', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'threshold' => (int) $threshold,
            'data' => $lowStockVariants,
        ]);
    }
}
