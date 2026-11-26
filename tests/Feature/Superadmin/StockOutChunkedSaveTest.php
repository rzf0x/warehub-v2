<?php

namespace Tests\Feature\Superadmin;

use App\Models\BundleItem;
use App\Models\Konveksi;
use App\Models\Period;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\StockOut;
use App\Models\StockOutItem;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class StockOutChunkedSaveTest extends TestCase
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

    private function seedBaseFixtures(): array
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Gudang Utama', 'location' => 'Bandung']);
        $period = Period::create([
            'name' => 'Periode Test',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'is_public' => true,
        ]);
        $konveksi = Konveksi::create(['name' => 'Konveksi A']);
        $seller = Seller::create(['seller_name' => 'Mitra Test']);
        $store = Store::create([
            'seller_id' => $seller->id,
            'store_name' => 'Toko Shopee Test',
            'marketplace' => 'shopee',
        ]);

        $singleProduct = Product::create([
            'seller_id' => $seller->id,
            'konveksi_id' => $konveksi->id,
            'product_name' => 'Kaos Polos',
            'sku' => 'KP',
            'product_type' => 'single',
        ]);
        $varA = ProductVariant::create([
            'product_id' => $singleProduct->id,
            'sku' => 'KP-M',
            'size' => 'M',
            'color' => 'Hitam',
            'price' => 30000,
            'selling_price' => 60000,
        ]);
        $varB = ProductVariant::create([
            'product_id' => $singleProduct->id,
            'sku' => 'KP-L',
            'size' => 'L',
            'color' => 'Hitam',
            'price' => 35000,
            'selling_price' => 70000,
        ]);

        $bundleProduct = Product::create([
            'seller_id' => $seller->id,
            'konveksi_id' => $konveksi->id,
            'product_name' => 'Paket Hemat 2 Kaos',
            'sku' => 'PKT-2',
            'product_type' => 'bundle',
        ]);
        $bundleVar = ProductVariant::create([
            'product_id' => $bundleProduct->id,
            'sku' => 'PKT-2-ML',
            'size' => 'M+L',
            'color' => 'Hitam',
            'price' => 65000,
            'selling_price' => 120000,
        ]);
        BundleItem::create([
            'bundle_variant_id' => $bundleVar->id,
            'component_variant_id' => $varA->id,
            'quantity' => 1,
        ]);
        BundleItem::create([
            'bundle_variant_id' => $bundleVar->id,
            'component_variant_id' => $varB->id,
            'quantity' => 2,
        ]);

        Stock::create(['warehouse_id' => $warehouse->id, 'product_variant_id' => $varA->id, 'qty' => 100, 'allocated_qty' => 0]);
        Stock::create(['warehouse_id' => $warehouse->id, 'product_variant_id' => $varB->id, 'qty' => 100, 'allocated_qty' => 0]);

        return compact('user', 'warehouse', 'period', 'konveksi', 'seller', 'store', 'varA', 'varB', 'bundleVar');
    }

    public function test_staged_chunked_save_creates_stock_out_atomically_and_deducts_single_and_bundle_stocks(): void
    {
        $fx = $this->seedBaseFixtures();

        // 1. Init staging session
        $initRes = $this->actingAs($fx['user'])->postJson(route('superadmin.warehouse.stock-out.save-init'), [
            'warehouse_id' => $fx['warehouse']->id,
            'store_id' => $fx['store']->id,
            'period_id' => $fx['period']->id,
            'date' => '2026-11-26',
            'note' => 'Batch Besar Test',
            'bruto' => 1200000,
            'total_selling_price' => 1000000,
            'fallback_resi_summary' => 'Fallback',
        ]);

        $initRes->assertOk()->assertJson(['success' => true]);
        $token = $initRes->json('token');
        $this->assertNotEmpty($token);

        // Zero DB rows before commit
        $this->assertSame(0, StockOut::count());

        // 2. Append items in 2 chunks (single + bundle)
        $this->actingAs($fx['user'])->postJson(route('superadmin.warehouse.stock-out.save-chunk'), [
            'token' => $token,
            'type' => 'items',
            'chunk' => [
                [
                    'product_variant_id' => $fx['varA']->id,
                    'konveksi_id' => $fx['konveksi']->id,
                    'qty' => 4,
                    'price' => 30000,
                    'selling_price' => 60000,
                ],
            ],
        ])->assertOk()->assertJson(['success' => true, 'received' => 1]);

        $this->actingAs($fx['user'])->postJson(route('superadmin.warehouse.stock-out.save-chunk'), [
            'token' => $token,
            'type' => 'items',
            'chunk' => [
                [
                    'product_variant_id' => $fx['bundleVar']->id,
                    'konveksi_id' => $fx['konveksi']->id,
                    'qty' => 3,
                    'price' => 65000,
                    'selling_price' => 120000,
                ],
            ],
        ])->assertOk()->assertJson(['success' => true, 'received' => 1]);

        // 3. Append 600 resis in 2 chunks of 300
        $resisChunk1 = [];
        $resisChunk2 = [];
        for ($i = 1; $i <= 300; $i++) {
            $resisChunk1[] = ['order_id' => "ORD-{$i}", 'no_resi' => "JX{$i}", 'payment' => 1000];
        }
        for ($i = 301; $i <= 600; $i++) {
            $resisChunk2[] = ['order_id' => "ORD-{$i}", 'no_resi' => "JX{$i}", 'payment' => 1000];
        }

        $this->actingAs($fx['user'])->postJson(route('superadmin.warehouse.stock-out.save-chunk'), [
            'token' => $token,
            'type' => 'resis',
            'chunk' => $resisChunk1,
        ])->assertOk();

        $this->actingAs($fx['user'])->postJson(route('superadmin.warehouse.stock-out.save-chunk'), [
            'token' => $token,
            'type' => 'resis',
            'chunk' => $resisChunk2,
        ])->assertOk();

        // Still 0 DB rows before commit
        $this->assertSame(0, StockOut::count());

        // 4. Commit atomically
        $commitRes = $this->actingAs($fx['user'])->postJson(route('superadmin.warehouse.stock-out.save-commit'), [
            'token' => $token,
        ]);

        $commitRes->assertOk()->assertJson(['success' => true]);

        $stockOut = StockOut::with('items')->findOrFail($commitRes->json('stock_out_id'));
        // Total HPP: 4*30000 + 3*65000 = 120000 + 195000 = 315000
        $computedHpp = $stockOut->items->sum(fn ($it) => (float) $it->price * (int) $it->qty);
        $this->assertEquals(315000.0, (float) $computedHpp);
        // Total selling from 600 resis * 1000 = 600000
        $this->assertEquals(600000.0, (float) $stockOut->total_selling_price);
        $this->assertEquals(1200000.0, (float) $stockOut->bruto);
        $this->assertCount(2, $stockOut->items);

        $decodedResis = json_decode((string) $stockOut->resi_summary, true);
        $this->assertCount(600, $decodedResis);

        // Stock deductions:
        // varA: 100 - 4 (single) - 3*1 (bundle) = 93
        // varB: 100 - 3*2 (bundle) = 94
        $this->assertSame(93, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['varA']->id)->value('qty'));
        $this->assertSame(94, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['varB']->id)->value('qty'));

        // Stock logs recorded (1 for single varA + 2 for bundle components varA & varB = 3)
        $this->assertSame(3, StockLog::where('reference_id', $stockOut->id)->count());

        // Staging dir cleaned up
        $this->assertFalse(File::isDirectory(storage_path('app/private/stock-out-staging/' . $token)));
    }

    public function test_abort_mid_upload_leaves_database_completely_untouched(): void
    {
        $fx = $this->seedBaseFixtures();

        $token = $this->actingAs($fx['user'])->postJson(route('superadmin.warehouse.stock-out.save-init'), [
            'warehouse_id' => $fx['warehouse']->id,
            'date' => '2026-11-26',
        ])->json('token');

        $this->actingAs($fx['user'])->postJson(route('superadmin.warehouse.stock-out.save-chunk'), [
            'token' => $token,
            'type' => 'items',
            'chunk' => [
                [
                    'product_variant_id' => $fx['varA']->id,
                    'qty' => 10,
                    'price' => 30000,
                    'selling_price' => 60000,
                ],
            ],
        ])->assertOk();

        $this->actingAs($fx['user'])->postJson(route('superadmin.warehouse.stock-out.save-abort'), [
            'token' => $token,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(0, StockOut::count());
        $this->assertSame(0, StockOutItem::count());
        $this->assertSame(0, StockLog::count());
        $this->assertSame(100, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['varA']->id)->value('qty'));
        $this->assertFalse(File::isDirectory(storage_path('app/private/stock-out-staging/' . $token)));
    }

    public function test_staged_chunked_save_updates_existing_stock_out_and_restores_old_stock(): void
    {
        $fx = $this->seedBaseFixtures();

        // Create initial stock out via standard store()
        $this->actingAs($fx['user'])->post(route('superadmin.warehouse.stock-out.store'), [
            'warehouse_id' => $fx['warehouse']->id,
            'store_id' => $fx['store']->id,
            'period_id' => $fx['period']->id,
            'date' => '2026-11-26',
            'resi_summary' => 'RESI-INIT-1',
            'items' => [
                [
                    'product_variant_id' => $fx['varA']->id,
                    'qty' => 10,
                    'price' => 30000,
                    'selling_price' => 60000,
                ],
            ],
        ])->assertRedirect(route('superadmin.warehouse.stock-out.index'));

        $stockOut = StockOut::firstOrFail();
        $this->assertSame(90, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['varA']->id)->value('qty'));

        // Now update via staged chunked save: change varA qty from 10 -> 2, add varB qty 5
        $token = $this->actingAs($fx['user'])->postJson(route('superadmin.warehouse.stock-out.save-init'), [
            'stock_out_id' => $stockOut->id,
            'warehouse_id' => $fx['warehouse']->id,
            'store_id' => $fx['store']->id,
            'period_id' => $fx['period']->id,
            'date' => '2026-11-26',
            'fallback_resi_summary' => 'RESI-UPDATED-1',
        ])->json('token');

        $this->actingAs($fx['user'])->postJson(route('superadmin.warehouse.stock-out.save-chunk'), [
            'token' => $token,
            'type' => 'items',
            'chunk' => [
                ['product_variant_id' => $fx['varA']->id, 'qty' => 2, 'price' => 30000, 'selling_price' => 60000],
                ['product_variant_id' => $fx['varB']->id, 'qty' => 5, 'price' => 35000, 'selling_price' => 70000],
            ],
        ])->assertOk();

        $this->actingAs($fx['user'])->postJson(route('superadmin.warehouse.stock-out.save-commit'), [
            'token' => $token,
        ])->assertOk()->assertJson(['success' => true, 'stock_out_id' => $stockOut->id]);

        // varA restored +10 then deducted -2 => 98
        // varB deducted -5 => 95
        $this->assertSame(98, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['varA']->id)->value('qty'));
        $this->assertSame(95, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['varB']->id)->value('qty'));
        $this->assertSame(2, StockOutItem::where('stock_out_id', $stockOut->id)->count());
    }

    public function test_destroy_restores_single_and_bundle_component_stocks_in_bulk_and_logs_cancellation(): void
    {
        $fx = $this->seedBaseFixtures();

        $this->actingAs($fx['user'])->post(route('superadmin.warehouse.stock-out.store'), [
            'warehouse_id' => $fx['warehouse']->id,
            'store_id' => $fx['store']->id,
            'period_id' => $fx['period']->id,
            'date' => '2026-11-26',
            'resi_summary' => 'RESI-BUNDLE-CANCEL-1',
            'items' => [
                ['product_variant_id' => $fx['varA']->id, 'qty' => 4, 'price' => 30000, 'selling_price' => 60000],
                ['product_variant_id' => $fx['bundleVar']->id, 'qty' => 3, 'price' => 65000, 'selling_price' => 120000],
            ],
        ])->assertRedirect(route('superadmin.warehouse.stock-out.index'));

        $stockOut = StockOut::firstOrFail();
        // After store: varA = 100 - 4 - 3*1 = 93, varB = 100 - 3*2 = 94
        $this->assertSame(93, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['varA']->id)->value('qty'));
        $this->assertSame(94, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['varB']->id)->value('qty'));

        // Destroy / cancel stock out
        $this->actingAs($fx['user'])
            ->delete(route('superadmin.warehouse.stock-out.destroy', $stockOut->id))
            ->assertRedirect();

        $this->assertSame(0, StockOut::count());
        $this->assertSame(0, StockOutItem::count());
        // Both single and bundle component stocks must be fully restored to 100
        $this->assertSame(100, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['varA']->id)->value('qty'));
        $this->assertSame(100, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['varB']->id)->value('qty'));
        // 3 positive restoration logs (single varA + bundle components varA & varB)
        $this->assertSame(
            3,
            StockLog::where('reference_id', $stockOut->id)
                ->where('qty_change', '>', 0)
                ->where('note', 'like', 'Pembatalan Stock Out%')
                ->count()
        );
    }

    public function test_index_defaults_to_active_period_without_pagination(): void
    {
        $fx = $this->seedBaseFixtures();

        $oldPeriod = Period::create([
            'name' => 'Periode Lama',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'is_public' => false,
        ]);

        // 1 transaction in active period, 1 in old period
        StockOut::create([
            'warehouse_id' => $fx['warehouse']->id,
            'user_id' => $fx['user']->id,
            'store_id' => $fx['store']->id,
            'period_id' => $fx['period']->id,
            'date' => '2026-11-26',
            'resi_summary' => 'RESI-ACTIVE',
            'total_selling_price' => 200000,
            'bruto' => 220000,
        ]);
        StockOut::create([
            'warehouse_id' => $fx['warehouse']->id,
            'user_id' => $fx['user']->id,
            'store_id' => $fx['store']->id,
            'period_id' => $oldPeriod->id,
            'date' => '2026-10-15',
            'resi_summary' => 'RESI-OLD',
            'total_selling_price' => 150000,
            'bruto' => 150000,
        ]);

        // Default index without period_id -> scoped to active period, returns flat array (no pagination)
        $this->actingAs($fx['user'])
            ->get(route('superadmin.warehouse.stock-out.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Superadmin/Warehouse/StockOut/Index')
                ->has('stockOuts', 1)
                ->where('filters.period_id', (string) $fx['period']->id)
                ->where('metrics.penjualan_bersih', 200000)
            );

        // Switching to period_id=all returns both
        $this->actingAs($fx['user'])
            ->get(route('superadmin.warehouse.stock-out.index', ['period_id' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('stockOuts', 2)
                ->where('filters.period_id', 'all')
                ->where('metrics.penjualan_bersih', 350000)
            );
    }

    public function test_show_renders_readonly_stock_out_detail_page(): void
    {
        $fx = $this->seedBaseFixtures();

        // Create unsorted SKU variants across sizes M, L, XL, XXL, 3XL, 4XL, 5XL and two base SKUs (A and B)
        $prodA = Product::create([
            'seller_id' => $fx['seller']->id,
            'konveksi_id' => $fx['konveksi']->id,
            'product_name' => 'Kaos A',
            'sku' => 'SKU-A',
            'product_type' => 'single',
        ]);
        $sizesUnsorted = ['5XL', 'M', 'XXL', '3XL', 'XL', '4XL', 'L'];
        $createdVarsA = [];
        foreach ($sizesUnsorted as $sz) {
            $createdVarsA[$sz] = ProductVariant::create([
                'product_id' => $prodA->id,
                'sku' => "SKU-A-{$sz}",
                'size' => $sz,
                'color' => 'Hitam',
                'price' => 30000,
                'selling_price' => 60000,
            ]);
        }

        $stockOut = StockOut::create([
            'warehouse_id' => $fx['warehouse']->id,
            'user_id' => $fx['user']->id,
            'store_id' => $fx['store']->id,
            'period_id' => $fx['period']->id,
            'date' => '2026-11-26',
            'note' => 'Detail Read-Only Test',
            'resi_summary' => json_encode([
                ['order_id' => 'ORD-1', 'no_resi' => 'JX0001', 'payment' => 240000],
                ['order_id' => 'ORD-2', 'no_resi' => 'JX0002', 'payment' => 240000],
            ]),
            'total_selling_price' => 480000,
            'bruto' => 500000,
        ]);

        // Insert items in intentionally scrambled order (KP-L, SKU-A-5XL, SKU-A-M, ..., KP-M)
        StockOutItem::create([
            'stock_out_id' => $stockOut->id,
            'product_variant_id' => $fx['varB']->id, // KP-L
            'konveksi_id' => $fx['konveksi']->id,
            'qty' => 1,
            'price' => 35000,
            'selling_price' => 70000,
        ]);
        foreach ($sizesUnsorted as $sz) {
            StockOutItem::create([
                'stock_out_id' => $stockOut->id,
                'product_variant_id' => $createdVarsA[$sz]->id,
                'konveksi_id' => $fx['konveksi']->id,
                'qty' => 1,
                'price' => 30000,
                'selling_price' => 60000,
            ]);
        }
        StockOutItem::create([
            'stock_out_id' => $stockOut->id,
            'product_variant_id' => $fx['varA']->id, // KP-M
            'konveksi_id' => $fx['konveksi']->id,
            'qty' => 1,
            'price' => 30000,
            'selling_price' => 60000,
        ]);

        // 1. Raw sequential numeric ID must be rejected (404)
        $this->actingAs($fx['user'])
            ->get('/superadmin/warehouse/stock-out/' . $stockOut->id)
            ->assertNotFound();

        // 2. Tampered / invalid token must be rejected (404)
        $this->actingAs($fx['user'])
            ->get('/superadmin/warehouse/stock-out/invalid-token-12345')
            ->assertNotFound();

        // 3. Valid opaque route key succeeds and returns items sorted ascending by base SKU + logical size order
        $detailKey = StockOut::encodeRouteKey((int) $stockOut->id);
        $this->assertFalse(ctype_digit($detailKey));

        $expectedOrder = [
            'KP-M',
            'KP-L',
            'SKU-A-M',
            'SKU-A-L',
            'SKU-A-XL',
            'SKU-A-XXL',
            'SKU-A-3XL',
            'SKU-A-4XL',
            'SKU-A-5XL',
        ];

        $this->actingAs($fx['user'])
            ->get(route('superadmin.warehouse.stock-out.show', $detailKey))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Superadmin/Warehouse/StockOut/Show')
                ->where('stockOut.id', $stockOut->id)
                ->where('stockOut.detail_key', $detailKey)
                ->has('items', 9)
                ->has('resis', 2)
                ->where('metrics.total_qty', 9)
                ->where('metrics.penjualan_bersih', 480000)
                ->where('metrics.biaya_marketplace', 20000)
                ->where('items', fn ($items) => collect($items)->pluck('sku')->all() === $expectedOrder)
            );
    }
}
