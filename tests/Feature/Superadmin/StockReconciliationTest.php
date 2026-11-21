<?php

namespace Tests\Feature\Superadmin;

use App\Models\BundleItem;
use App\Models\Konveksi;
use App\Models\Period;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerDebtAdjustment;
use App\Models\Stock;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Models\StockLog;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StockOut;
use App\Models\StockOutItem;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SellerStockReconciliationService;
use Illuminate\Database\Migrations\Migration;
use Tests\TestCase;

class StockReconciliationTest extends TestCase
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

    public function test_extract_sku_prefix_handles_standard_and_edge_cases(): void
    {
        $this->assertSame('AYD', SellerStockReconciliationService::extractSkuPrefix('AYD-HITAM-L'));
        $this->assertSame('RTG', SellerStockReconciliationService::extractSkuPrefix('rtg_01_m'));
        $this->assertSame('STG', SellerStockReconciliationService::extractSkuPrefix('STG01-XL'));
        $this->assertSame('PTG', SellerStockReconciliationService::extractSkuPrefix('PTG/BIRU/S'));
        $this->assertSame('DTG', SellerStockReconciliationService::extractSkuPrefix('DTG MERAH M'));
        $this->assertSame('TANPA PREFIX', SellerStockReconciliationService::extractSkuPrefix(''));
    }

    public function test_reconciliation_page_calculates_per_variant_debt_with_bundle_unpacking_and_prefix_grouping(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Gudang Utama', 'location' => 'Jakarta']);
        $period = Period::create([
            'name' => 'Periode Test',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'is_public' => true,
        ]);
        $konveksi = Konveksi::create(['name' => 'Konveksi Maju']);
        $seller = Seller::create(['seller_name' => 'Mitra Alpha', 'phone' => '0812345']);
        $store = Store::create([
            'seller_id' => $seller->id,
            'store_name' => 'Toko Alpha',
            'marketplace' => 'shopee',
        ]);

        // Single products: AYD & RTG
        $prodAyd = Product::create([
            'seller_id' => $seller->id,
            'konveksi_id' => $konveksi->id,
            'product_name' => 'Kaos AYD',
            'sku' => 'AYD',
            'product_type' => 'single',
        ]);
        $varAyd = ProductVariant::create([
            'product_id' => $prodAyd->id,
            'sku' => 'AYD-BLK-L',
            'size' => 'L',
            'color' => 'Black',
            'price' => 40000,
            'selling_price' => 80000,
        ]);

        $prodRtg = Product::create([
            'seller_id' => $seller->id,
            'konveksi_id' => $konveksi->id,
            'product_name' => 'Celana RTG',
            'sku' => 'RTG',
            'product_type' => 'single',
        ]);
        $varRtg = ProductVariant::create([
            'product_id' => $prodRtg->id,
            'sku' => 'RTG-NVY-M',
            'size' => 'M',
            'color' => 'Navy',
            'price' => 60000,
            'selling_price' => 110000,
        ]);

        // Bundle product combining 1 AYD + 1 RTG
        $prodBundle = Product::create([
            'seller_id' => $seller->id,
            'product_name' => 'Setelan AYD+RTG',
            'sku' => 'BND-SET-01',
            'product_type' => 'bundle',
        ]);
        $varBundle = ProductVariant::create([
            'product_id' => $prodBundle->id,
            'sku' => 'BND-SET-01',
            'size' => 'BUNDLE',
            'color' => 'BUNDLE',
            'price' => 100000,
            'selling_price' => 180000,
        ]);
        BundleItem::create([
            'bundle_variant_id' => $varBundle->id,
            'component_variant_id' => $varAyd->id,
            'quantity' => 1,
        ]);
        BundleItem::create([
            'bundle_variant_id' => $varBundle->id,
            'component_variant_id' => $varRtg->id,
            'quantity' => 1,
        ]);

        // Stock In: 10 AYD @ 40k = 400k; 6 RTG @ 60k = 360k
        $stockIn = StockIn::create([
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'period_id' => $period->id,
            'invoice_number' => 'INV-IN-001',
            'date' => '2026-01-05',
        ]);
        StockInItem::create([
            'stock_in_id' => $stockIn->id,
            'product_variant_id' => $varAyd->id,
            'konveksi_id' => $konveksi->id,
            'qty' => 10,
            'price' => 40000,
        ]);
        StockInItem::create([
            'stock_in_id' => $stockIn->id,
            'product_variant_id' => $varRtg->id,
            'konveksi_id' => $konveksi->id,
            'qty' => 6,
            'price' => 60000,
        ]);

        // Stock Out: 3 single AYD + 2 bundle (unpacks 2 AYD + 2 RTG)
        $stockOut = StockOut::create([
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'store_id' => $store->id,
            'period_id' => $period->id,
            'date' => '2026-01-10',
        ]);
        StockOutItem::create([
            'stock_out_id' => $stockOut->id,
            'product_variant_id' => $varAyd->id,
            'qty' => 3,
            'price' => 40000,
            'selling_price' => 80000,
        ]);
        StockOutItem::create([
            'stock_out_id' => $stockOut->id,
            'product_variant_id' => $varBundle->id,
            'qty' => 2,
            'price' => 100000,
            'selling_price' => 180000,
        ]);

        // Physical stock
        Stock::create([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $varAyd->id,
            'qty' => 4,
            'allocated_qty' => 0,
        ]);
        Stock::create([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $varRtg->id,
            'qty' => 4,
            'allocated_qty' => 0,
        ]);

        SellerDebtAdjustment::create([
            'seller_id' => $seller->id,
            'period_id' => $period->id,
            'amount' => -50000,
            'reason' => 'Potongan awal',
            'date' => '2026-01-12',
        ]);

        $response = $this->actingAs($user)->get(
            route('superadmin.warehouse.stocks.reconciliation', [
                'seller_id' => $seller->id,
                'warehouse_id' => $warehouse->id,
            ])
        );

        $response->assertOk();

        /** @var SellerStockReconciliationService $service */
        $service = app(SellerStockReconciliationService::class);
        $report = $service->buildReport($seller, $warehouse->id);

        $this->assertSame(2, $report['summary']['total_prefixes']);
        $this->assertSame(2, $report['summary']['total_variants']);
        // AYD: In 10 (400k), Out 3+2=5 (200k) => Sisa 5 (200k)
        // RTG: In 6 (360k), Out 0+2=2 (120k) => Sisa 4 (240k)
        $this->assertSame(9, $report['summary']['sisa_debt_qty']);
        $this->assertEquals(440000.0, $report['summary']['sisa_debt_hpp']);
        $this->assertEquals(390000.0, $report['summary']['net_debt_with_adjustment']);
        $this->assertSame(4, $report['summary']['bundle_out_qty']);
        $this->assertSame(8, $report['summary']['warehouse_stock_qty']);
    }

    public function test_exports_csv_template_and_pdf_return_ok(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Gudang Utama']);
        $seller = Seller::create(['seller_name' => 'Mitra Export']);
        $product = Product::create([
            'seller_id' => $seller->id,
            'product_name' => 'Baju STG',
            'sku' => 'STG',
            'product_type' => 'single',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'STG-WHT-XL',
            'size' => 'XL',
            'color' => 'White',
            'price' => 50000,
            'selling_price' => 95000,
        ]);
        Stock::create([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'qty' => 7,
            'allocated_qty' => 0,
        ]);

        $this->actingAs($user);

        $csvRes = $this->get(route('superadmin.warehouse.stocks.reconciliation.export-csv', [
            'seller_id' => $seller->id,
            'warehouse_id' => $warehouse->id,
        ]));
        $csvRes->assertOk();
        $this->assertStringContainsString('STG-WHT-XL', $csvRes->streamedContent());

        $tplRes = $this->get(route('superadmin.warehouse.stocks.reconciliation.template-csv', [
            'seller_id' => $seller->id,
            'warehouse_id' => $warehouse->id,
        ]));
        $tplRes->assertOk();
        $this->assertStringContainsString('stok_fisik_opname', $tplRes->streamedContent());

        $pdfRes = $this->get(route('superadmin.warehouse.stocks.reconciliation.export-pdf', [
            'seller_id' => $seller->id,
            'warehouse_id' => $warehouse->id,
        ]));
        $pdfRes->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfRes->headers->get('content-type'));
    }

    public function test_reset_zero_and_apply_opname_only_mutate_stocks_and_audit_logs_without_touching_debt_items(): void
    {
        $user = User::factory()->create();
        $warehouseMain = Warehouse::create(['name' => 'Gudang A']);
        $warehouseOther = Warehouse::create(['name' => 'Gudang B']);

        $sellerTarget = Seller::create(['seller_name' => 'Mitra Target']);
        $sellerOther = Seller::create(['seller_name' => 'Mitra Lain']);

        $prodAyd = Product::create([
            'seller_id' => $sellerTarget->id,
            'product_name' => 'Produk AYD',
            'sku' => 'AYD',
            'product_type' => 'single',
        ]);
        $varAyd = ProductVariant::create([
            'product_id' => $prodAyd->id,
            'sku' => 'AYD-001',
            'size' => 'M',
            'color' => 'Hitam',
            'price' => 30000,
            'selling_price' => 60000,
        ]);

        $prodPtg = Product::create([
            'seller_id' => $sellerTarget->id,
            'product_name' => 'Produk PTG',
            'sku' => 'PTG',
            'product_type' => 'single',
        ]);
        $varPtg = ProductVariant::create([
            'product_id' => $prodPtg->id,
            'sku' => 'PTG-001',
            'size' => 'L',
            'color' => 'Putih',
            'price' => 45000,
            'selling_price' => 85000,
        ]);

        $prodOther = Product::create([
            'seller_id' => $sellerOther->id,
            'product_name' => 'Produk Mitra Lain',
            'sku' => 'DTG',
            'product_type' => 'single',
        ]);
        $varOther = ProductVariant::create([
            'product_id' => $prodOther->id,
            'sku' => 'DTG-999',
            'size' => 'S',
            'color' => 'Biru',
            'price' => 25000,
            'selling_price' => 50000,
        ]);

        // Historical StockIn & StockOut that must remain untouched
        $stockIn = StockIn::create([
            'warehouse_id' => $warehouseMain->id,
            'user_id' => $user->id,
            'invoice_number' => 'IN-1',
            'date' => '2026-01-01',
        ]);
        StockInItem::create([
            'stock_in_id' => $stockIn->id,
            'product_variant_id' => $varAyd->id,
            'qty' => 20,
            'price' => 30000,
        ]);
        $stockOut = StockOut::create([
            'warehouse_id' => $warehouseMain->id,
            'user_id' => $user->id,
            'date' => '2026-01-02',
        ]);
        StockOutItem::create([
            'stock_out_id' => $stockOut->id,
            'product_variant_id' => $varAyd->id,
            'qty' => 5,
            'price' => 30000,
            'selling_price' => 60000,
        ]);

        // Initial Stocks
        $stockAydMain = Stock::create([
            'warehouse_id' => $warehouseMain->id,
            'product_variant_id' => $varAyd->id,
            'qty' => 15,
            'allocated_qty' => 0,
        ]);
        $stockPtgMain = Stock::create([
            'warehouse_id' => $warehouseMain->id,
            'product_variant_id' => $varPtg->id,
            'qty' => 8,
            'allocated_qty' => 0,
        ]);
        $stockAydOtherWh = Stock::create([
            'warehouse_id' => $warehouseOther->id,
            'product_variant_id' => $varAyd->id,
            'qty' => 12,
            'allocated_qty' => 0,
        ]);
        $stockOtherSeller = Stock::create([
            'warehouse_id' => $warehouseMain->id,
            'product_variant_id' => $varOther->id,
            'qty' => 9,
            'allocated_qty' => 0,
        ]);

        $this->actingAs($user);

        // 1. Reset zero all stocks of sellerTarget in warehouseMain
        $resetRes = $this->from(route('superadmin.warehouse.stocks.reconciliation'))
            ->post(route('superadmin.warehouse.stocks.reconciliation.reset-zero'), [
                'seller_id' => $sellerTarget->id,
                'warehouse_id' => $warehouseMain->id,
                'date' => '2026-02-01',
                'note' => 'Reset nol pra opname',
            ]);

        $resetRes->assertRedirect(route('superadmin.warehouse.stocks.reconciliation'));
        $resetRes->assertSessionHas('success');

        $this->assertSame(0, $stockAydMain->fresh()->qty);
        $this->assertSame(0, $stockPtgMain->fresh()->qty);
        // Other warehouse and other seller untouched
        $this->assertSame(12, $stockAydOtherWh->fresh()->qty);
        $this->assertSame(9, $stockOtherSeller->fresh()->qty);

        // StockOpname, StockOpnameItem, StockLog recorded
        $this->assertSame(1, StockOpname::count());
        $this->assertSame(2, StockOpnameItem::count());
        $this->assertSame(2, StockLog::count());

        // 2. Apply new opname values (AYD -> 11, PTG -> 0 unchanged)
        $applyRes = $this->from(route('superadmin.warehouse.stocks.reconciliation'))
            ->post(route('superadmin.warehouse.stocks.reconciliation.apply-opname'), [
                'seller_id' => $sellerTarget->id,
                'warehouse_id' => $warehouseMain->id,
                'date' => '2026-02-01',
                'note' => 'Input hasil opname terbaru',
                'items' => [
                    ['product_variant_id' => $varAyd->id, 'physical_qty' => 11],
                    ['product_variant_id' => $varPtg->id, 'physical_qty' => 0],
                ],
            ]);

        $applyRes->assertRedirect(route('superadmin.warehouse.stocks.reconciliation'));
        $applyRes->assertSessionHas('success');

        $this->assertSame(11, $stockAydMain->fresh()->qty);
        $this->assertSame(0, $stockPtgMain->fresh()->qty);
        // Second opname only records the 1 changed variant (varAyd)
        $this->assertSame(2, StockOpname::count());
        $this->assertSame(3, StockOpnameItem::count());
        $this->assertSame(3, StockLog::count());

        // Reject variant belonging to another seller
        $badRes = $this->post(route('superadmin.warehouse.stocks.reconciliation.apply-opname'), [
            'seller_id' => $sellerTarget->id,
            'warehouse_id' => $warehouseMain->id,
            'date' => '2026-02-01',
            'items' => [
                ['product_variant_id' => $varOther->id, 'physical_qty' => 5],
            ],
        ]);
        $badRes->assertSessionHasErrors('items');

        // Debt source tables untouched!
        $this->assertSame(1, StockInItem::count());
        $this->assertSame(1, StockOutItem::count());
        $this->assertSame(20, (int) StockInItem::sum('qty'));
        $this->assertSame(5, (int) StockOutItem::sum('qty'));
    }
}
