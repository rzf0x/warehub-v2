<?php

namespace Tests\Feature\Superadmin;

use App\Models\Konveksi;
use App\Models\Period;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\StockOut;
use App\Models\StockOutItem;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockOutSettlementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $files = glob(database_path('migrations/*.php')) ?: [];
        sort($files);

        foreach ($files as $file) {
            if (basename($file) === '2026_01_01_000031_add_prices_to_variant_template_items_table.php') {
                continue;
            }

            $migration = require $file;
            if ($migration instanceof Migration) {
                $migration->up();
            }
        }
    }

    public function test_settlement_aggregates_hierarchy_konveksi_and_large_resi_summary_with_low_memory(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Gudang Utama', 'location' => 'Bandung']);

        $period1 = Period::create([
            'name' => 'Periode Oktober',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'is_public' => true,
        ]);
        $period2 = Period::create([
            'name' => 'Periode November',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'is_public' => false,
        ]);

        $konveksiA = Konveksi::create(['name' => 'Konveksi Alpha']);
        $konveksiB = Konveksi::create(['name' => 'Konveksi Beta']);

        $seller1 = Seller::create(['seller_name' => 'Mitra Satu']);
        $seller2 = Seller::create(['seller_name' => 'Mitra Dua']);

        $store1 = Store::create([
            'seller_id' => $seller1->id,
            'store_name' => 'Toko Shopee Mitra 1',
            'marketplace' => 'shopee',
        ]);

        $prod1 = Product::create([
            'seller_id' => $seller1->id,
            'konveksi_id' => $konveksiA->id,
            'product_name' => 'Kaos Polos',
            'sku' => 'KP',
            'product_type' => 'single',
        ]);
        $var1 = ProductVariant::create([
            'product_id' => $prod1->id,
            'sku' => 'KP-BLK-L',
            'size' => 'L',
            'color' => 'Hitam',
            'price' => 40000,
            'selling_price' => 80000,
        ]);

        $prod2 = Product::create([
            'seller_id' => $seller2->id,
            'konveksi_id' => $konveksiB->id,
            'product_name' => 'Celana Chino',
            'sku' => 'CC',
            'product_type' => 'single',
        ]);
        $var2 = ProductVariant::create([
            'product_id' => $prod2->id,
            'sku' => 'CC-NVY-XL',
            'size' => 'XL',
            'color' => 'Navy',
            'price' => 60000,
            'selling_price' => 120000,
        ]);

        // Generate a large LONGTEXT resi_summary (> 100KB JSON) with 1,500 valid resis + 50 empty resis
        $largeResiEntries = [];
        for ($i = 1; $i <= 1550; $i++) {
            $largeResiEntries[] = [
                'order_id' => 'ORD-' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'no_resi' => $i <= 1500 ? ('JX' . str_pad((string) $i, 8, '0', STR_PAD_LEFT)) : '',
                'payment' => 85000,
            ];
        }
        $largeResiJson = json_encode($largeResiEntries);
        $this->assertGreaterThan(4096, strlen((string) $largeResiJson));

        // StockOut #1: Store 1 (Mitra 1), Period 1, mixed Konveksi A & B, large resi JSON
        $so1 = StockOut::create([
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'store_id' => $store1->id,
            'period_id' => $period1->id,
            'date' => '2026-10-15',
            'note' => 'Batch Besar Shopee',
            'resi_summary' => $largeResiJson,
            'total_selling_price' => 640000,
            'bruto' => 640000,
        ]);
        StockOutItem::create([
            'stock_out_id' => $so1->id,
            'product_variant_id' => $var1->id,
            'qty' => 5,
            'price' => 40000,
            'selling_price' => 80000,
        ]);
        StockOutItem::create([
            'stock_out_id' => $so1->id,
            'product_variant_id' => $var2->id,
            'konveksi_id' => $konveksiB->id,
            'qty' => 2,
            'price' => 60000,
            'selling_price' => 120000,
        ]);

        // StockOut #2: Direct sale (no store -> fallback seller from item = Mitra 2), Period 2, manual resi
        $so2 = StockOut::create([
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'store_id' => null,
            'period_id' => $period2->id,
            'date' => '2026-11-05',
            'note' => 'Direct Order',
            'resi_summary' => 'JNT-MANUAL-999',
            'total_selling_price' => 360000,
            'bruto' => 360000,
        ]);
        StockOutItem::create([
            'stock_out_id' => $so2->id,
            'product_variant_id' => $var2->id,
            'qty' => 3,
            'price' => 60000,
            'selling_price' => 120000,
        ]);

        $memBefore = memory_get_usage(false);

        // Default visit (no query params) MUST render ONLY the active/public period ($period1)
        $response = $this->actingAs($user)
            ->get(route('superadmin.warehouse.stock-out.settlement'));

        $memDelta = memory_get_usage(false) - $memBefore;

        $response->assertOk();
        $this->assertLessThan(15 * 1024 * 1024, $memDelta, 'Settlement memory usage should stay well below 15MB');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Superadmin/Warehouse/StockOut/StockOutSettlement')
            ->where('filters.period_id', $period1->id)
            ->where('summary.total_periods', 1)
            ->where('summary.grand_total_pcs', 7)
            ->where('summary.grand_total_nominal', 320000)
            ->where('summary.grand_total_fakturs', 1)
            ->where('settlementPeriods.0.period_id', $period1->id)
            ->where('settlementPeriods.0.sellers.0.seller_id', $seller1->id)
            ->where('settlementPeriods.0.sellers.0.stores.0.fakturs.0.resi_info.total_resi', 1500)
            ->where('settlementPeriods.0.sellers.0.stores.0.fakturs.0.resi_info.label', '1500 Resi')
            ->where('settlementPeriods.0.sellers.0.stores.0.fakturs.0.resi_info.preview', 'JX00000001, JX00000002 (+1498 resi)')
            ->where('settlementPeriods.0.sellers.0.stores.0.fakturs.0.pcs', 7)
            ->where('settlementPeriods.0.sellers.0.stores.0.fakturs.0.nominal', 320000)
        );

        // Switching explicitly to Period 2 renders ONLY Period 2 (direct sale fallback seller = Mitra 2)
        $period2Res = $this->actingAs($user)
            ->get(route('superadmin.warehouse.stock-out.settlement', [
                'period_id' => $period2->id,
            ]));

        $period2Res->assertOk();
        $period2Res->assertInertia(fn (Assert $page) => $page
            ->where('filters.period_id', $period2->id)
            ->where('summary.total_periods', 1)
            ->where('summary.grand_total_pcs', 3)
            ->where('summary.grand_total_nominal', 180000)
            ->where('summary.grand_total_fakturs', 1)
            ->where('settlementPeriods.0.period_id', $period2->id)
            ->where('settlementPeriods.0.sellers.0.seller_id', $seller2->id)
            ->where('settlementPeriods.0.sellers.0.stores.0.fakturs.0.resi_info.label', 'Manual')
            ->where('settlementPeriods.0.sellers.0.stores.0.fakturs.0.resi_info.preview', 'JNT-MANUAL-999')
        );

        // Filter by konveksi_id = Konveksi B within default active Period 1 (only 2 pcs @ 60k = 120k)
        $filteredRes = $this->actingAs($user)
            ->get(route('superadmin.warehouse.stock-out.settlement', [
                'konveksi_id' => $konveksiB->id,
            ]));

        $filteredRes->assertOk();
        $filteredRes->assertInertia(fn (Assert $page) => $page
            ->where('filters.period_id', $period1->id)
            ->where('summary.grand_total_pcs', 2)
            ->where('summary.grand_total_nominal', 120000)
            ->where('summary.grand_total_fakturs', 1)
        );

        // Also ensure PDF Surat Jalan handles large resi_summary safely
        $pdfRes = $this->actingAs($user)
            ->get(route('superadmin.warehouse.stock-out.pdf', $so1->id));
        $pdfRes->assertOk();
    }
}
