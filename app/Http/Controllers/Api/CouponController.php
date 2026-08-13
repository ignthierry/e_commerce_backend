<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    /**
     * Validate and apply a coupon (Public/Customer).
     */
    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $code = strtoupper(trim($request->code));
        $subtotal = $request->subtotal;

        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Kupon diskon tidak ditemukan.',
            ], 404);
        }

        $now = now();

        if ($coupon->valid_from && $now->lt($coupon->valid_from)) {
            return response()->json([
                'success' => false,
                'message' => 'Kupon diskon belum berlaku.',
            ], 400);
        }

        if ($coupon->valid_until && $now->gt($coupon->valid_until)) {
            return response()->json([
                'success' => false,
                'message' => 'Kupon diskon telah kedaluwarsa.',
            ], 400);
        }

        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            return response()->json([
                'success' => false,
                'message' => 'Batas kuota penggunaan kupon telah habis.',
            ], 400);
        }

        if ($subtotal < $coupon->min_purchase) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal pembelian untuk menggunakan kupon ini adalah Rp ' . number_format($coupon->min_purchase, 0, ',', '.'),
            ], 400);
        }

        // Calculate discount amount
        $discountAmount = 0;
        if ($coupon->type === 'percentage') {
            $discountAmount = ($subtotal * $coupon->value) / 100;
            if ($coupon->max_discount && $discountAmount > $coupon->max_discount) {
                $discountAmount = $coupon->max_discount;
            }
        } else {
            $discountAmount = min($coupon->value, $subtotal);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kupon berhasil diterapkan!',
            'data' => [
                'coupon_id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'discount_amount' => (float) $discountAmount,
            ],
        ]);
    }

    /**
     * Display a listing of coupons (Admin).
     */
    public function index()
    {
        $coupons = Coupon::latest()->get();

        return response()->json([
            'success' => true,
            'data' => $coupons,
        ]);
    }

    /**
     * Store a newly created coupon (Admin).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:coupons,code',
            'type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'usage_limit' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $coupon = Coupon::create([
            'code' => strtoupper(trim($request->code)),
            'type' => $request->type,
            'value' => $request->value,
            'min_purchase' => $request->min_purchase ?? 0,
            'max_discount' => $request->max_discount,
            'valid_from' => $request->valid_from,
            'valid_until' => $request->valid_until,
            'usage_limit' => $request->usage_limit,
            'used_count' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kupon diskon berhasil dibuat.',
            'data' => $coupon,
        ], 201);
    }

    /**
     * Remove the specified coupon (Admin).
     */
    public function destroy($id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Kupon tidak ditemukan.',
            ], 404);
        }

        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kupon berhasil dihapus.',
        ]);
    }
}
