<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data katalog untuk development (kecil, ±150 produk).
 * Data besar (±100.000 produk) untuk uji performa dibuat terpisah di Tahap 5.
 */
class CatalogSeeder extends Seeder
{
    private const CATEGORIES = [
        'Coffee' => ['KOP', ['Arabica Gayo', 'Robusta Lampung', 'Toraja Kalosi', 'Kintamani Bali', 'Flores Bajawa']],
        'Tea' => ['TEH', ['Jasmine Tea', 'Green Tea', 'Black Tea', 'Oolong Tea', 'Chamomile']],
        'Snacks' => ['SNK', ['Cassava Chips', 'Banana Chips', 'Peanut Brittle', 'Rice Crackers', 'Tempeh Chips']],
        'Bakery' => ['BKR', ['Butter Croissant', 'Banana Bread', 'Cheese Roll', 'Chocolate Muffin', 'Sourdough Loaf']],
        'Dairy' => ['DRY', ['Fresh Milk', 'Greek Yogurt', 'Cheddar Cheese', 'Salted Butter', 'Oat Milk']],
        'Beverages' => ['BEV', ['Mineral Water', 'Orange Juice', 'Coconut Water', 'Sparkling Water', 'Iced Lemon Tea']],
        'Household' => ['HSH', ['Dish Soap', 'Laundry Detergent', 'Floor Cleaner', 'Paper Towel', 'Trash Bags']],
        'Personal Care' => ['PRC', ['Shampoo', 'Body Wash', 'Toothpaste', 'Hand Soap', 'Sunscreen']],
    ];

    private const SIZES = ['100g', '250g', '500g', '1kg', 'Small', 'Medium', 'Large'];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name => [$prefix, $items]) {
            $category = Category::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => "All {$name} products.",
            ]);

            $n = 1;

            foreach ($items as $item) {
                foreach (fake()->randomElements(self::SIZES, 4) as $size) {
                    Product::factory()->for($category)->create([
                        'sku' => sprintf('%s-%03d', $prefix, $n++),
                        'name' => "{$item} {$size}",
                    ]);
                }
            }
        }
    }
}
