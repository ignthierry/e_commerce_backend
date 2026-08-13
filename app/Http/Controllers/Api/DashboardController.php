<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Analytics summary (Admin / CS / Warehouse).
     */
    public function stats(Request $request)
    {
        // Total Omset / Revenue (Order yang sudah paid / completed)
        $totalRevenue = Order::whereIn('payment_status', ['paid'])
            ->sum('grand_total');

        // Total Transaksi
        $totalOrders = Order::count();

        // Jumlah Pesanan Pending / Processing
        $pendingOrders = Order::where('status', 'pending')->count();
        $processingOrders = Order::where('status', 'processing')->count();
        $shippedOrders = Order::where('status', 'shipped')->count();

        // Total Pelanggan
        $totalCustomers = User::where('role', 'customer')->count();

        // Total Produk & Produk Stok Menipis (stok <= 10)
        $totalProducts = Product::where('is_active', true)->count();
        $lowStockCount = ProductVariant::where('stock', '<=', 10)->count();

        // Produk Terlaris (Top 5)
        $topProducts = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        // Grafik Penjualan 7 Hari Terakhir
        $recentSales = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(grand_total) as daily_revenue'),
                DB::raw('COUNT(id) as order_count')
            )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => (float) $totalRevenue,
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'processing_orders' => $processingOrders,
                'shipped_orders' => $shippedOrders,
                'total_customers' => $totalCustomers,
                'total_products' => $totalProducts,
                'low_stock_count' => $lowStockCount,
                'top_products' => $topProducts,
                'recent_sales' => $recentSales,
            ],
        ]);
    }
}
