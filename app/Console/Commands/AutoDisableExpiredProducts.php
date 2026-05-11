<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProductBatches;
use App\Models\Product;


class AutoDisableExpiredProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:auto-disable-expired-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $products = Product::where('is_active', true)->get();

        foreach ($products as $product) {
            $stokAman = ProductBatches::where('product_id', $product->id)
                ->where('expiry_date', '>', now())
                ->where('quantity', '>', 0)
                ->exists();

            if (!$stokAman) {
                $product->update(['is_active' => false]);
                $this->info("Produk {$product->name} dinonaktifkan karena stok kadaluwarsa.");
            }
        }
    }
}
