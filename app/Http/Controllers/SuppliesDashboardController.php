<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Concerns\FormatsSupplies;
use App\Models\InventoryItem;
use App\Models\StudentSupplySale;
use App\Services\SupplyReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
class SuppliesDashboardController extends Controller
{
    use FormatsSupplies;
 
    public function index(Request $request, SupplyReportService $reports): Response
    {
        $user = $request->user();
        abort_unless($user->can('supplies.dashboard.view'), 403);
 
        return Inertia::render('Supplies/Dashboard', [
            'stats' => $reports->dashboard(),
            'recentSales' => $user->can('supplies.sales.view')
                ? StudentSupplySale::query()->with(['student:id,first_name,middle_name,last_name,admission_number', 'schoolClass:id,name', 'academicYear:id,name', 'seller:id,name'])
                    ->latest('sold_at')->limit(8)->get()->map(fn (StudentSupplySale $sale): array => $this->salePayload($sale))->values()
                : [],
            'attention' => $user->can('supplies.items.view')
                ? InventoryItem::query()->active()->whereColumn('quantity_on_hand', '<=', 'reorder_level')->with('category:id,name')
                    ->orderBy('quantity_on_hand')->limit(8)->get()->map(fn (InventoryItem $item): array => $this->itemPayload($item))->values()
                : [],
            'can' => [
                'sell' => $user->can('supplies.sales.create'),
                'receive' => $user->can('supplies.purchases.create'),
                'items' => $user->can('supplies.items.view'),
                'reports' => $user->can('supplies.reports.view'),
            ],
        ]);
    }
}
