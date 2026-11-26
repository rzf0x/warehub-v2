<?php

namespace Tests\Feature\Superadmin;

use App\Models\Konveksi;
use App\Models\Period;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Stock;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Models\StockLog;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Tests\TestCase;

class StockInOptimizationTest extends TestCase
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

    private function seedFixtures(): array
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Gudang Utama', 'location' => 'Bandung']);
        $activePeriod = Period::create([
            'name' => 'Periode Aktif Nov',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'is_public' => true,
        ]);
        $oldPeriod = Period::create([
            'name' => 'Periode Lama Okt',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'is_public' => false,
        ]);
        $konveksi = Konveksi::create(['name' => 'Konveksi Jaya']);
        $seller = Seller::create(['seller_name' => 'Mitra Alpha']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'konveksi_id' => $konveksi->id,
            'product_name' => 'Kaos ATG06',
            'sku' => 'ATG06',
            'product_type' => 'single',
        ]);

        $sizes = ['5XL', 'M', 'XXL', '3XL', 'XL', '4XL', 'L'];
        $variants = [];
        foreach ($sizes as $sz) {
            $variants[$sz] = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => "ATG06-{$sz}",
                'size' => $sz,
                'color' => 'Hitam',
                'price' => 30000,
                'selling_price' => 60000,
            ]);
        }

        return compact('user', 'warehouse', 'activePeriod', 'oldPeriod', 'konveksi', 'seller', 'product', 'variants', 'sizes');
    }

    public function test_index_defaults_to_active_period_without_pagination_and_computes_aggregates(): void
    {
        $fx = $this->seedFixtures();

        $siActive = StockIn::create([
            'warehouse_id' => $fx['warehouse']->id,
            'user_id' => $fx['user']->id,
            'period_id' => $fx['activePeriod']->id,
            'invoice_number' => 'WHB/IN/20261126/0001',
            'date' => '2026-11-26',
        ]);
        StockInItem::create([
            'stock_in_id' => $siActive->id,
            'product_variant_id' => $fx['variants']['M']->id,
            'konveksi_id' => $fx['konveksi']->id,
            'qty' => 10,
            'price' => 30000,
        ]);

        $siOld = StockIn::create([
            'warehouse_id' => $fx['warehouse']->id,
            'user_id' => $fx['user']->id,
            'period_id' => $fx['oldPeriod']->id,
            'invoice_number' => 'WHB/IN/20261010/0002',
            'date' => '2026-10-10',
        ]);
        StockInItem::create([
            'stock_in_id' => $siOld->id,
            'product_variant_id' => $fx['variants']['L']->id,
            'konveksi_id' => $fx['konveksi']->id,
            'qty' => 5,
            'price' => 30000,
        ]);

        $this->actingAs($fx['user'])
            ->get(route('superadmin.warehouse.stock-in.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Superadmin/Warehouse/StockIn/Index')
                ->has('stockIns', 1)
                ->where('filters.period_id', (string) $fx['activePeriod']->id)
                ->where('summary.total_transactions', 1)
                ->where('summary.total_qty', 10)
                ->where('summary.total_nominal', 300000)
                ->where('stockIns.0.detail_key', StockIn::encodeRouteKey((int) $siActive->id))
                ->where('stockIns.0.sellers.0', 'Mitra Alpha')
                ->where('stockIns.0.konveksis.0', 'Konveksi Jaya')
            );

        $this->actingAs($fx['user'])
            ->get(route('superadmin.warehouse.stock-in.index', ['period_id' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('stockIns', 2)
                ->where('summary.total_qty', 15)
                ->where('summary.total_nominal', 450000)
            );
    }

    public function test_show_uses_opaque_key_and_sorts_items_by_base_sku_and_logical_size(): void
    {
        $fx = $this->seedFixtures();

        $stockIn = StockIn::create([
            'warehouse_id' => $fx['warehouse']->id,
            'user_id' => $fx['user']->id,
            'period_id' => $fx['activePeriod']->id,
            'invoice_number' => 'WHB/IN/20261126/0099',
            'date' => '2026-11-26',
            'note' => 'Kiriman konveksi batch 1',
        ]);

        // Insert in scrambled size order
        foreach ($fx['sizes'] as $sz) {
            StockInItem::create([
                'stock_in_id' => $stockIn->id,
                'product_variant_id' => $fx['variants'][$sz]->id,
                'konveksi_id' => $fx['konveksi']->id,
                'qty' => 2,
                'price' => 30000,
            ]);
        }

        // Raw numeric ID and invalid token must return 404
        $this->actingAs($fx['user'])
            ->get('/superadmin/warehouse/stock-in/' . $stockIn->id)
            ->assertNotFound();

        $this->actingAs($fx['user'])
            ->get('/superadmin/warehouse/stock-in/invalid-key-xyz')
            ->assertNotFound();

        $detailKey = StockIn::encodeRouteKey((int) $stockIn->id);
        $expectedOrder = ['ATG06-M', 'ATG06-L', 'ATG06-XL', 'ATG06-XXL', 'ATG06-3XL', 'ATG06-4XL', 'ATG06-5XL'];

        $this->actingAs($fx['user'])
            ->get(route('superadmin.warehouse.stock-in.show', $detailKey))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Superadmin/Warehouse/StockIn/Show')
                ->where('stockIn.detail_key', $detailKey)
                ->has('items', 7)
                ->where('metrics.total_qty', 14)
                ->where('metrics.total_hpp', 420000)
                ->where('items', fn ($items) => collect($items)->pluck('sku')->all() === $expectedOrder)
            );
    }

    public function test_bulk_store_update_and_destroy_keep_stocks_and_logs_consistent(): void
    {
        $fx = $this->seedFixtures();

        // 1. Bulk Store
        $this->actingAs($fx['user'])->post(route('superadmin.warehouse.stock-in.store'), [
            'warehouse_id' => $fx['warehouse']->id,
            'period_id' => $fx['activePeriod']->id,
            'invoice_number' => 'WHB/IN/20261126/7777',
            'date' => '2026-11-26',
            'items' => [
                ['product_variant_id' => $fx['variants']['M']->id, 'konveksi_id' => $fx['konveksi']->id, 'qty' => 20, 'price' => 30000],
                ['product_variant_id' => $fx['variants']['L']->id, 'konveksi_id' => $fx['konveksi']->id, 'qty' => 15, 'price' => 30000],
            ],
        ])->assertRedirect(route('superadmin.warehouse.stock-in.index'));

        $stockIn = StockIn::firstOrFail();
        $this->assertSame(20, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['variants']['M']->id)->value('qty'));
        $this->assertSame(15, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['variants']['L']->id)->value('qty'));
        $this->assertSame(2, StockLog::where('reference_id', $stockIn->id)->count());

        // 2. Bulk Update (M: 20 -> 12, L: 15 -> removed, XL: +8)
        $this->actingAs($fx['user'])->put(route('superadmin.warehouse.stock-in.update', $stockIn->id), [
            'warehouse_id' => $fx['warehouse']->id,
            'period_id' => $fx['activePeriod']->id,
            'date' => '2026-11-26',
            'items' => [
                ['product_variant_id' => $fx['variants']['M']->id, 'konveksi_id' => $fx['konveksi']->id, 'qty' => 12, 'price' => 30000],
                ['product_variant_id' => $fx['variants']['XL']->id, 'konveksi_id' => $fx['konveksi']->id, 'qty' => 8, 'price' => 32000],
            ],
        ])->assertRedirect(route('superadmin.warehouse.stock-in.index'));

        $this->assertSame(12, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['variants']['M']->id)->value('qty'));
        $this->assertSame(0, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['variants']['L']->id)->value('qty'));
        $this->assertSame(8, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['variants']['XL']->id)->value('qty'));

        // 3. Bulk Destroy -> reverts M & XL back to 0
        $this->actingAs($fx['user'])
            ->delete(route('superadmin.warehouse.stock-in.destroy', $stockIn->id))
            ->assertRedirect();

        $this->assertSame(0, StockIn::count());
        $this->assertSame(0, StockInItem::count());
        $this->assertSame(0, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['variants']['M']->id)->value('qty'));
        $this->assertSame(0, (int) Stock::where('warehouse_id', $fx['warehouse']->id)->where('product_variant_id', $fx['variants']['XL']->id)->value('qty'));
    }
}
