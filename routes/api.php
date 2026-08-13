<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes - OMEGA TOYS v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ----------------------------------------------------
    // Public Routes (Storefront)
    // ----------------------------------------------------
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{idOrSlug}', [CategoryController::class, 'show']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{idOrSlug}', [ProductController::class, 'show']);

    Route::post('/coupons/check', [CouponController::class, 'check']);

    // ----------------------------------------------------
    // Protected Routes (Authenticated Users)
    // ----------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {

        // Auth User Profile & Logout
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Customer Orders
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/my', [OrderController::class, 'customerOrders']);
        Route::get('/orders/{idOrNumber}', [OrderController::class, 'show']);

        // ----------------------------------------------------
        // Admin / Staff Protected Routes (RBAC)
        // ----------------------------------------------------
        Route::prefix('admin')->group(function () {

            // Shared Staff Routes (Admin, Warehouse, CS)
            Route::middleware('role:admin,warehouse,cs')->group(function () {
                Route::get('/orders', [OrderController::class, 'index']);
                Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
                Route::post('/orders/bulk-awb', [OrderController::class, 'bulkUpdateAwb']);
                Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
                Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
            });

            // Product Management (Admin, Warehouse)
            Route::middleware('role:admin,warehouse')->group(function () {
                Route::post('/products', [ProductController::class, 'store']);
                Route::put('/products/{id}', [ProductController::class, 'update']);
                Route::delete('/products/{id}', [ProductController::class, 'destroy']);
            });

            // Full Admin Only (Admin)
            Route::middleware('role:admin')->group(function () {
                Route::post('/categories', [CategoryController::class, 'store']);
                Route::put('/categories/{id}', [CategoryController::class, 'update']);
                Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

                Route::get('/coupons', [CouponController::class, 'index']);
                Route::post('/coupons', [CouponController::class, 'store']);
                Route::delete('/coupons/{id}', [CouponController::class, 'destroy']);
            });

        });

    });

});
