<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'name' => 'Electronics & Gadgets',
                'slug' => 'electronics-gadgets',
                'theme_preset' => 'tech_dark',
                'accent_color' => '#38BDF8',
                'description' => 'Gadget, audio, aksesoris smartphone, smart home, komputer, dan perangkat elektronik.',
                'subcategories' => ['Audio & TWS', 'Aksesoris Smartphone', 'Smart Home & Lighting', 'Komputer & Kerja', 'Kamera & Konten Kreator'],
            ],
            [
                'name' => 'Fashion & Apparel',
                'slug' => 'fashion-apparel',
                'theme_preset' => 'fashion_editorial',
                'accent_color' => '#E2B49A',
                'description' => 'Pakaian harian, outerwear, tas, sepatu, jam tangan, dan aksesoris fashion.',
                'subcategories' => ['Atasan & Outerwear', 'Bawahan & Denim', 'Sepatu & Sandal', 'Tas & Dompet', 'Aksesoris Fashion'],
            ],
            [
                'name' => 'Beauty & Skincare',
                'slug' => 'beauty-skincare',
                'theme_preset' => 'beauty_soft',
                'accent_color' => '#F472B6',
                'description' => 'Skincare, makeup, haircare, parfum, dan alat kecantikan.',
                'subcategories' => ['Skincare Wajah', 'Sunscreen & Serum', 'Makeup & Lip', 'Haircare & Bodycare', 'Alat Kecantikan'],
            ],
            [
                'name' => 'Health & Personal Care',
                'slug' => 'health-personal-care',
                'theme_preset' => 'health_clean',
                'accent_color' => '#2DD4BF',
                'description' => 'Perawatan diri, kebersihan harian, oral care, dan perlengkapan kebugaran ringan.',
                'subcategories' => ['Personal Care Harian', 'Oral & Dental Care', 'Alat Pijat & Relaksasi', 'Suplemen & Nutrisi (Data Resmi)'],
            ],
            [
                'name' => 'Home & Living',
                'slug' => 'home-living',
                'theme_preset' => 'home_warm',
                'accent_color' => '#F59E0B',
                'description' => 'Dekorasi rumah, penyimpanan, bedding, pencahayaan, dan kenyamanan ruang.',
                'subcategories' => ['Storage & Organizer', 'Dekorasi Ruangan', 'Lampu & Ambience', 'Bedding & Tekstil'],
            ],
            [
                'name' => 'Kitchen & Household',
                'slug' => 'kitchen-household',
                'theme_preset' => 'home_warm',
                'accent_color' => '#F59E0B',
                'description' => 'Peralatan masak, alat kebersihan, tumbler, dan perlengkapan dapur praktis.',
                'subcategories' => ['Alat Masak & Bakeware', 'Elektronik Dapur', 'Alat Kebersihan Rumah', 'Botol Minum & Wadah Makan'],
            ],
            [
                'name' => 'Automotive & Accessories',
                'slug' => 'automotive-accessories',
                'theme_preset' => 'auto_carbon',
                'accent_color' => '#EF4444',
                'description' => 'Aksesoris mobil, motor, perawatan bodi/interior, dan perlengkapan berkendara.',
                'subcategories' => ['Aksesoris Mobil', 'Aksesoris Motor', 'Perawatan Kendaraan', 'Dashcam & Keamanan'],
            ],
            [
                'name' => 'Sports & Outdoor',
                'slug' => 'sports-outdoor',
                'theme_preset' => 'sports_energy',
                'accent_color' => '#F97316',
                'description' => 'Alat olahraga, fitness rumahan, camping, lari, dan aktivitas luar ruang.',
                'subcategories' => ['Fitness & Gym Rumahan', 'Lari & Bersepeda', 'Camping & Hiking', 'Apparel Olahraga'],
            ],
            [
                'name' => 'Baby & Kids',
                'slug' => 'baby-kids',
                'theme_preset' => 'beauty_soft',
                'accent_color' => '#38BDF8',
                'description' => 'Perlengkapan bayi, mainan edukasi anak, pakaian anak, dan kebutuhan parenting.',
                'subcategories' => ['Perlengkapan Bayi', 'Pakaian Anak', 'Mainan Edukasi', 'Kebutuhan Sekolah'],
            ],
            [
                'name' => 'Pet Supplies',
                'slug' => 'pet-supplies',
                'theme_preset' => 'home_warm',
                'accent_color' => '#FB923C',
                'description' => 'Kebutuhan kucing, anjing, grooming, mainan, dan perlengkapan hewan peliharaan.',
                'subcategories' => ['Makanan & Snack Hewan', 'Grooming & Kebersihan', 'Kandang & Tempat Tidur', 'Mainan & Aksesoris Hewan'],
            ],
            [
                'name' => 'Food & Beverage',
                'slug' => 'food-beverage',
                'theme_preset' => 'food_terracotta',
                'accent_color' => '#FB923C',
                'description' => 'Camilan, kopi, teh, bumbu masakan, dan minuman kemasan.',
                'subcategories' => ['Kopi & Teh', 'Snack & Camilan', 'Bumbu & Bahan Masak', 'Minuman Siap Saji'],
            ],
            [
                'name' => 'Books & Education',
                'slug' => 'books-education',
                'theme_preset' => 'neutral_studio',
                'accent_color' => '#6366F1',
                'description' => 'Buku, alat tulis, perlengkapan belajar, dan produktivitas.',
                'subcategories' => ['Buku Pengembangan Diri', 'Alat Tulis & Stationery', 'Perlengkapan Belajar'],
            ],
            [
                'name' => 'Toys & Hobbies',
                'slug' => 'toys-hobbies',
                'theme_preset' => 'sports_energy',
                'accent_color' => '#A855F7',
                'description' => 'Koleksi hobi, board game, kerajinan DIY, dan mainan kreatif.',
                'subcategories' => ['DIY & Craft', 'Board Game & Puzzle', 'Koleksi & Figur'],
            ],
            [
                'name' => 'Plants & Gardening',
                'slug' => 'plants-gardening',
                'theme_preset' => 'botanical_green',
                'accent_color' => '#34D399',
                'description' => 'Tanaman hias, aroid, media tanam, pot, pupuk, grow light, dan perlengkapan berkebun.',
                'subcategories' => ['Tanaman Hias & Aroid', 'Media Tanam & Pupuk', 'Pot & Rak Tanaman', 'Alat Berkebun & Grow Light'],
            ],
            [
                'name' => 'Other / General',
                'slug' => 'other-general',
                'theme_preset' => 'neutral_studio',
                'accent_color' => '#6366F1',
                'description' => 'Kategori umum untuk produk lain di luar daftar utama TikTok Shop.',
                'subcategories' => ['General Product', 'Seasonal & Gift'],
            ],
        ];

        foreach ($catalog as $idx => $item) {
            $parent = ProductCategory::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'parent_id' => null,
                    'name' => $item['name'],
                    'theme_preset' => $item['theme_preset'],
                    'accent_color' => $item['accent_color'],
                    'description' => $item['description'],
                    'sort_order' => ($idx + 1) * 10,
                    'is_active' => true,
                ]
            );

            foreach ($item['subcategories'] as $subIdx => $subName) {
                $subSlug = $item['slug'] . '-' . Str::slug($subName);
                ProductCategory::updateOrCreate(
                    ['slug' => $subSlug],
                    [
                        'parent_id' => $parent->id,
                        'name' => $subName,
                        'theme_preset' => $item['theme_preset'],
                        'accent_color' => $item['accent_color'],
                        'sort_order' => ($subIdx + 1) * 10,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
