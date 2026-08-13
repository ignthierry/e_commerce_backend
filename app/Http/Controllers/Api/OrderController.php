<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Store new order / Checkout (Authenticated Customer).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipping_address' => 'required|string',
            'courier' => 'required|string',
            'shipping_cost' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'coupon_code' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        DB::beginTransaction();
        try {
            $totalAmount = 0;
            $itemsToCreate = [];

            foreach ($request->items as $itemData) {
                $variant = ProductVariant::with('product')->lockForUpdate()->find($itemData['product_variant_id']);

                if (!$variant) {
                    throw new \Exception("Varian produk dengan ID {$itemData['product_variant_id']} tidak ditemukan.");
                }

                if ($variant->stock < $itemData['quantity']) {
                    throw new \Exception("Stok produk '{$variant->product->name} - {$variant->name}' tidak mencukupi (Tersisa: {$variant->stock}).");
                }

                $unitPrice = $variant->product->base_price + $variant->additional_price;
                $itemSubtotal = $unitPrice * $itemData['quantity'];
                $totalAmount += $itemSubtotal;

                // Kurangi stok varian
                $variant->decrement('stock', $itemData['quantity']);

                $itemsToCreate[] = [
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'quantity' => $itemData['quantity'],
                    'price_at_purchase' => $unitPrice,
                ];
            }

            // Hitung diskon jika ada kupon
            $discount = 0;
            if ($request->filled('coupon_code')) {
                $coupon = Coupon::where('code', strtoupper($request->coupon_code))->first();
                if ($coupon && ($coupon->valid_until == null || now()->lte($coupon->valid_until)) && $totalAmount >= $coupon->min_purchase) {
                    if ($coupon->type === 'percentage') {
                        $discount = ($totalAmount * $coupon->value) / 100;
                        if ($coupon->max_discount && $discount > $coupon->max_discount) {
                            $discount = $coupon->max_discount;
                        }
                    } else {
                        $discount = min($coupon->value, $totalAmount);
                    }
                    $coupon->increment('used_count');
                }
            }

            $shippingCost = (float) $request->shipping_cost;
            $grandTotal = max(0, $totalAmount - $discount) + $shippingCost;

            // Generate Order Number unik OMG-YYYYMMDD-XXXX
            $orderNumber = 'OMG-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => $orderNumber,
                'total_amount' => $totalAmount,
                'shipping_cost' => $shippingCost,
                'grand_total' => $grandTotal,
                'status' => 'pending',
                'payment_method' => $request->payment_method,
                'payment_status' => 'unpaid',
                'courier' => $request->courier,
                'shipping_address' => $request->shipping_address,
            ]);

            foreach ($itemsToCreate as $item) {
                $item['order_id'] = $order->id;
                OrderItem::create($item);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat.',
                'data' => $order->load(['items.product', 'items.variant']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pesanan: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Customer order list.
     */
    public function customerOrders(Request $request)
    {
        $user = $request->user();
        $orders = Order::where('user_id', $user->id)
            ->with(['items.product', 'items.variant'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Detail pesanan (Customer/Admin).
     */
    public function show(Request $request, $idOrNumber)
    {
        $user = $request->user();

        $query = Order::where('id', $idOrNumber)->orWhere('order_number', $idOrNumber);

        // Jika bukan admin/cs/warehouse, batasi ke user yang bersangkutan
        if ($user->role === 'customer') {
            $query->where('user_id', $user->id);
        }

        $order = $query->with(['user', 'items.product', 'items.variant'])->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Admin/Warehouse: Management Order List.
     */
    public function index(Request $request)
    {
        $query = Order::with(['user', 'items.product', 'items.variant']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $orders = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Update status pesanan / payment status (Admin/Warehouse/CS).
     */
    public function updateStatus(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,processing,shipped,completed,cancelled',
            'payment_status' => 'sometimes|in:unpaid,paid,failed,expired',
            'awb_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('status')) $order->status = $request->status;
        if ($request->has('payment_status')) $order->payment_status = $request->payment_status;
        if ($request->has('awb_number')) $order->awb_number = $request->awb_number;

        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Status pesanan berhasil diperbarui.',
            'data' => $order,
        ]);
    }

    /**
     * Update resi massal (Warehouse / Admin).
     */
    public function bulkUpdateAwb(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipments' => 'required|array|min:1',
            'shipments.*.order_id' => 'required|exists:orders,id',
            'shipments.*.awb_number' => 'required|string',
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
            $updatedCount = 0;
            foreach ($request->shipments as $shipment) {
                $order = Order::find($shipment['order_id']);
                if ($order) {
                    $order->awb_number = $shipment['awb_number'];
                    $order->status = 'shipped';
                    $order->save();
                    $updatedCount++;
                }
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil memperbarui resi untuk {$updatedCount} pesanan.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui resi massal: ' . $e->getMessage(),
            ], 500);
        }
    }
}
