<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use App\Models\Coupon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Users (RBAC roles)
        $admin = User::firstOrCreate(
            ['email' => 'admin@omegatoys.id'],
            [
                'name' => 'Admin Omega Toys',
                'phone_number' => '081234567890',
                'role' => 'admin',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        $warehouse = User::firstOrCreate(
            ['email' => 'gudang@omegatoys.id'],
            [
                'name' => 'Operator Gudang',
                'phone_number' => '081234567891',
                'role' => 'warehouse',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        $cs = User::firstOrCreate(
            ['email' => 'cs@omegatoys.id'],
            [
                'name' => 'Customer Service',
                'phone_number' => '081234567892',
                'role' => 'cs',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        $customer = User::firstOrCreate(
            ['email' => 'customer@gmail.com'],
            [
                'name' => 'Budi Pembeli',
                'phone_number' => '081234567893',
                'role' => 'customer',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        // 2. Seed Categories
        $catAction = Category::create([
            'name' => 'Action Figure',
            'slug' => 'action-figure',
            'image' => 'https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?w=600&q=80',
        ]);

        $catEdukasi = Category::create([
            'name' => 'Mainan Edukasi',
            'slug' => 'mainan-edukasi',
            'image' => 'https://images.unsplash.com/photo-1587654780291-39c9404d746b?w=600&q=80',
        ]);

        $catRC = Category::create([
            'name' => 'Mobil Remote Control',
            'slug' => 'mobil-remote-control',
            'image' => 'https://images.unsplash.com/photo-1594787318286-3d835c1d207f?w=600&q=80',
        ]);

        $catGundam = Category::create([
            'name' => 'Gundam & Model Kit',
            'slug' => 'gundam-model-kit',
            'parent_id' => $catAction->id,
            'image' => 'https://images.unsplash.com/photo-1612036782180-6f0b6cd846fe?w=600&q=80',
        ]);

        // 3. Seed Products
        $p1 = Product::create([
            'name' => 'Gundam RX-78-2 Master Grade 1/100',
            'slug' => 'gundam-rx-78-2-master-grade-1-100',
            'description' => 'Model kit plastik rakitan Gundam ikonik RX-78-2 dalam skala Master Grade 1/100. Detail tinggi dengan artikulasi fleksibel.',
            'base_price' => 650000,
            'weight_grams' => 850,
            'is_active' => true,
        ]);
        $p1->categories()->attach([$catAction->id, $catGundam->id]);

        ProductVariant::create([
            'product_id' => $p1->id,
            'sku' => 'GND-RX78-STD',
            'name' => 'Standard Edition',
            'additional_price' => 0,
            'stock' => 15,
            'image' => 'https://images.unsplash.com/photo-1612036782180-6f0b6cd846fe?w=600&q=80',
        ]);

        ProductVariant::create([
            'product_id' => $p1->id,
            'sku' => 'GND-RX78-CLR',
            'name' => 'Clear Color Edition',
            'additional_price' => 100000,
            'stock' => 5,
            'image' => 'https://images.unsplash.com/photo-1612036782180-6f0b6cd846fe?w=600&q=80',
        ]);

        ProductImage::create([
            'product_id' => $p1->id,
            'image_url' => 'https://images.unsplash.com/photo-1612036782180-6f0b6cd846fe?w=800&q=80',
            'is_primary' => true,
        ]);

        $p2 = Product::create([
            'name' => 'RC Offroad Monster Truck 4WD 1:16',
            'slug' => 'rc-offroad-monster-truck-4wd-1-16',
            'description' => 'Mobil remote control balap outdoor dengan suspensi independen 4 roda. Kecepatan maksimal 25 km/jam.',
            'base_price' => 380000,
            'weight_grams' => 1200,
            'is_active' => true,
        ]);
        $p2->categories()->attach([$catRC->id]);

        ProductVariant::create([
            'product_id' => $p2->id,
            'sku' => 'RC-TRK-RED',
            'name' => 'Merah Fire',
            'additional_price' => 0,
            'stock' => 20,
            'image' => 'https://images.unsplash.com/photo-1594787318286-3d835c1d207f?w=600&q=80',
        ]);

        ProductVariant::create([
            'product_id' => $p2->id,
            'sku' => 'RC-TRK-BLU',
            'name' => 'Biru Electric',
            'additional_price' => 0,
            'stock' => 8,
            'image' => 'https://images.unsplash.com/photo-1594787318286-3d835c1d207f?w=600&q=80',
        ]);

        ProductImage::create([
            'product_id' => $p2->id,
            'image_url' => 'https://images.unsplash.com/photo-1594787318286-3d835c1d207f?w=800&q=80',
            'is_primary' => true,
        ]);

        $p3 = Product::create([
            'name' => 'Balok Kayu Edukasi 100 Pcs Building Blocks',
            'slug' => 'balok-kayu-edukasi-100-pcs-building-blocks',
            'description' => 'Mainan edukatif susun balok kayu warna-warni cat aman non-toxic untuk melatih kreativitas anak usia 3+ tahun.',
            'base_price' => 175000,
            'weight_grams' => 1500,
            'is_active' => true,
        ]);
        $p3->categories()->attach([$catEdukasi->id]);

        ProductVariant::create([
            'product_id' => $p3->id,
            'sku' => 'BLK-100-NAT',
            'name' => 'Natural & Colourful',
            'additional_price' => 0,
            'stock' => 30,
            'image' => 'https://images.unsplash.com/photo-1587654780291-39c9404d746b?w=600&q=80',
        ]);

        ProductImage::create([
            'product_id' => $p3->id,
            'image_url' => 'https://images.unsplash.com/photo-1587654780291-39c9404d746b?w=800&q=80',
            'is_primary' => true,
        ]);

        // 4. Seed Coupons
        Coupon::create([
            'code' => 'OMEGA10',
            'type' => 'percentage',
            'value' => 10, // 10%
            'min_purchase' => 100000,
            'max_discount' => 50000,
            'valid_from' => now()->subDays(5),
            'valid_until' => now()->addDays(30),
            'usage_limit' => 100,
            'used_count' => 0,
        ]);

        Coupon::create([
            'code' => 'DISFORTY',
            'type' => 'fixed',
            'value' => 40000, // Rp 40.000
            'min_purchase' => 200000,
            'max_discount' => 40000,
            'valid_from' => now()->subDays(1),
            'valid_until' => now()->addDays(15),
            'usage_limit' => 50,
            'used_count' => 0,
        ]);
    }
}
