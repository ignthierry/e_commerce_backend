<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Display a listing of the categories (Public).
     */
    public function index()
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->withCount('products')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Display the specified category with products (Public).
     */
    public function show($idOrSlug)
    {
        $category = Category::where('id', $idOrSlug)
            ->orWhere('slug', $idOrSlug)
            ->with(['children', 'products' => function ($q) {
                $q->where('is_active', true)->with(['variants', 'images']);
            }])
            ->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    /**
     * Store a newly created category in storage (Admin).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . Str::random(4),
            'parent_id' => $request->parent_id,
            'image' => $request->image,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dibuat.',
            'data' => $category,
        ], 201);
    }

    /**
     * Update the specified category in storage (Admin).
     */
    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('name')) {
            $category->name = $request->name;
            $category->slug = Str::slug($request->name) . '-' . Str::random(4);
        }
        if ($request->has('parent_id')) {
            $category->parent_id = $request->parent_id;
        }
        if ($request->has('image')) {
            $category->image = $request->image;
        }

        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil diperbarui.',
            'data' => $category,
        ]);
    }

    /**
     * Remove the specified category from storage (Admin).
     */
    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan.',
            ], 404);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus.',
        ]);
    }
}
