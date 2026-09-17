<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\VariantTemplate;
use App\Models\VariantTemplateItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class VariantTemplateController extends Controller
{
    public function index()
    {
        $templates = VariantTemplate::with('items')->latest()->get();

        return Inertia::render('Superadmin/Warehouse/Products/BulkVariant', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.size' => ['required', 'string', 'max:50'],
            'items.*.color' => ['nullable', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($validated) {
            $template = VariantTemplate::create([
                'template_name' => $validated['name'],
                'name' => $validated['name'],
            ]);

            foreach ($validated['items'] as $item) {
                VariantTemplateItem::create([
                    'variant_template_id' => $template->id,
                    'size' => $item['size'],
                    'color' => $item['color'] ?? 'Standard',
                ]);
            }
        });

        return redirect()->back()->with('success', 'Template Ukuran & Varian Massal berhasil dibuat.');
    }

    public function destroy(VariantTemplate $template)
    {
        DB::transaction(function () use ($template) {
            $template->items()->delete();
            $template->delete();
        });

        return redirect()->back()->with('success', 'Template varian berhasil dihapus.');
    }
}
