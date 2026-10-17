<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\StudentSupplyPayment;
use App\Models\StudentSupplySale;
use App\Models\StudentSupplySaleItem;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only figures for the dashboard and reports. "Net sales" is what was sold minus what came back, so a cancelled
 * sale counts as zero. "Collected" is payments minus refunds, by the date the money moved.
 */
class SupplyReportService
{
    public const BREAKDOWNS = ['item', 'category', 'class', 'student', 'cashier', 'method'];

    public const GRANULARITIES = ['day', 'week', 'month', 'term', 'year'];

    public function __construct(private SupplyRequirementService $requirements) {}

    /** @return array{0: Carbon, 1: Carbon} */
    public function period(array $filters): array
    {
        $to = filled($filters['to'] ?? null) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();
        $from = filled($filters['from'] ?? null) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth();

        return $from->gt($to) ? [$to->copy()->startOfDay(), $from->copy()->endOfDay()] : [$from, $to];
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $valuation = InventoryItem::query()
            ->selectRaw('COUNT(*) as items, COALESCE(SUM(quantity_on_hand * cost_price), 0) as cost, COALESCE(SUM(quantity_on_hand * selling_price), 0) as retail')
            ->first();

        $salesBetween = fn (Carbon $from, Carbon $to) => StudentSupplySale::query()
            ->whereBetween('sold_at', [$from, $to])
            ->selectRaw('COUNT(*) as sales, COALESCE(SUM(total_amount - returned_amount), 0) as net')
            ->first();
        $collectedBetween = fn (Carbon $from, Carbon $to) => (float) StudentSupplyPayment::query()
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE -amount END), 0) as collected")
            ->value('collected');

        $today = $salesBetween(now()->startOfDay(), now()->endOfDay());
        $month = $salesBetween(now()->startOfMonth(), now()->endOfDay());

        $outstanding = StudentSupplySale::query()->outstanding()
            ->selectRaw('COUNT(*) as sales, COALESCE(SUM((total_amount - returned_amount) - (paid_amount - refunded_amount)), 0) as balance')
            ->first();

        return [
            'items' => (int) $valuation->items,
            'stockCost' => (float) $valuation->cost,
            'stockRetail' => (float) $valuation->retail,
            'lowStock' => InventoryItem::query()->active()->lowStock()->count(),
            'outOfStock' => InventoryItem::query()->active()->outOfStock()->count(),
            'salesToday' => ['count' => (int) $today->sales, 'net' => (float) $today->net, 'collected' => $collectedBetween(now()->startOfDay(), now()->endOfDay())],
            'salesMonth' => ['count' => (int) $month->sales, 'net' => (float) $month->net, 'collected' => $collectedBetween(now()->startOfMonth(), now()->endOfDay())],
            'outstanding' => ['count' => (int) $outstanding->sales, 'balance' => (float) $outstanding->balance],
        ];
    }

    /**
     * Net sales and money collected, rolled up by day, week, month, term or year.
     *
     * @return array{rows: list<array<string, mixed>>, totals: array<string, float|int>}
     */
    public function sales(array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $granularity = in_array($filters['granularity'] ?? '', self::GRANULARITIES, true) ? $filters['granularity'] : 'day';
        $terms = $granularity === 'term' ? Term::query()->orderBy('start_date')->get(['id', 'name', 'start_date', 'end_date']) : collect();

        $sold = StudentSupplySale::query()
            ->whereBetween('sold_at', [$from, $to])
            ->selectRaw('DATE(sold_at) as day, COUNT(*) as sales, SUM(total_amount - returned_amount) as net, SUM(discount_amount) as discounts')
            ->groupByRaw('DATE(sold_at)')
            ->get();
        $paid = StudentSupplyPayment::query()
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw("DATE(paid_at) as day, SUM(CASE WHEN type = 'payment' THEN amount ELSE -amount END) as collected")
            ->groupByRaw('DATE(paid_at)')
            ->get();

        $rows = [];
        foreach ($sold as $row) {
            $key = $this->bucket((string) $row->day, $granularity, $terms);
            $rows[$key]['period'] = $key;
            $rows[$key]['sales'] = ($rows[$key]['sales'] ?? 0) + (int) $row->sales;
            $rows[$key]['net'] = ($rows[$key]['net'] ?? 0) + (float) $row->net;
            $rows[$key]['discounts'] = ($rows[$key]['discounts'] ?? 0) + (float) $row->discounts;
        }
        foreach ($paid as $row) {
            $key = $this->bucket((string) $row->day, $granularity, $terms);
            $rows[$key]['period'] = $key;
            $rows[$key]['collected'] = ($rows[$key]['collected'] ?? 0) + (float) $row->collected;
        }

        ksort($rows);
        $rows = collect($rows)->map(fn (array $row): array => ['period' => $row['period'], 'sales' => $row['sales'] ?? 0, 'net' => round($row['net'] ?? 0, 2), 'discounts' => round($row['discounts'] ?? 0, 2), 'collected' => round($row['collected'] ?? 0, 2)])->values();

        return [
            'rows' => $rows->all(),
            'totals' => ['sales' => (int) $rows->sum('sales'), 'net' => round($rows->sum('net'), 2), 'discounts' => round($rows->sum('discounts'), 2), 'collected' => round($rows->sum('collected'), 2)],
        ];
    }

    /**
     * Sales split by item, category, class, student, cashier or payment method.
     * Item and category revenue is at list price before any sale-level discount.
     *
     * @return list<array<string, mixed>>
     */
    public function breakdown(string $by, array $filters): array
    {
        [$from, $to] = $this->period($filters);

        return match ($by) {
            'item' => StudentSupplySaleItem::query()
                ->join('student_supply_sales as s', 's.id', '=', 'student_supply_sale_items.sale_id')
                ->whereBetween('s.sold_at', [$from, $to])
                ->groupBy('student_supply_sale_items.item_id', 'student_supply_sale_items.item_name', 'student_supply_sale_items.sku')
                ->selectRaw('student_supply_sale_items.item_name as label, student_supply_sale_items.sku as detail, SUM(student_supply_sale_items.quantity - student_supply_sale_items.returned_quantity) as units, SUM((student_supply_sale_items.quantity - student_supply_sale_items.returned_quantity) * student_supply_sale_items.unit_price) as amount, SUM((student_supply_sale_items.quantity - student_supply_sale_items.returned_quantity) * student_supply_sale_items.unit_cost) as cost')
                ->orderByDesc('amount')->get()->map(fn ($r): array => $this->line($r->label, $r->detail, (int) $r->units, (float) $r->amount, (float) $r->cost))->all(),
            'category' => StudentSupplySaleItem::query()
                ->join('student_supply_sales as s', 's.id', '=', 'student_supply_sale_items.sale_id')
                ->join('inventory_items as i', 'i.id', '=', 'student_supply_sale_items.item_id')
                ->join('inventory_categories as c', 'c.id', '=', 'i.category_id')
                ->whereBetween('s.sold_at', [$from, $to])
                ->groupBy('c.id', 'c.name')
                ->selectRaw('c.name as label, SUM(student_supply_sale_items.quantity - student_supply_sale_items.returned_quantity) as units, SUM((student_supply_sale_items.quantity - student_supply_sale_items.returned_quantity) * student_supply_sale_items.unit_price) as amount, SUM((student_supply_sale_items.quantity - student_supply_sale_items.returned_quantity) * student_supply_sale_items.unit_cost) as cost')
                ->orderByDesc('amount')->get()->map(fn ($r): array => $this->line($r->label, null, (int) $r->units, (float) $r->amount, (float) $r->cost))->all(),
            'class' => StudentSupplySale::query()
                ->leftJoin('classes as c', 'c.id', '=', 'student_supply_sales.class_id')
                ->whereBetween('student_supply_sales.sold_at', [$from, $to])
                ->groupBy('c.id', 'c.name')
                ->selectRaw("COALESCE(c.name, 'No class') as label, COUNT(*) as sales, SUM(student_supply_sales.total_amount - student_supply_sales.returned_amount) as amount")
                ->orderByDesc('amount')->get()->map(fn ($r): array => $this->line($r->label, null, (int) $r->sales, (float) $r->amount))->all(),
            'student' => StudentSupplySale::query()
                ->join('students as st', 'st.id', '=', 'student_supply_sales.student_id')
                ->whereBetween('student_supply_sales.sold_at', [$from, $to])
                ->groupBy('st.id', 'st.first_name', 'st.last_name', 'st.admission_number')
                ->selectRaw('st.first_name as first_name, st.last_name as last_name, st.admission_number as detail, COUNT(*) as sales, SUM(student_supply_sales.total_amount - student_supply_sales.returned_amount) as amount')
                ->orderByDesc('amount')->limit(100)->get()->map(fn ($r): array => $this->line(trim($r->first_name.' '.$r->last_name), $r->detail, (int) $r->sales, (float) $r->amount))->all(),
            'cashier' => StudentSupplySale::query()
                ->join('users as u', 'u.id', '=', 'student_supply_sales.sold_by')
                ->whereBetween('student_supply_sales.sold_at', [$from, $to])
                ->groupBy('u.id', 'u.name')
                ->selectRaw('u.name as label, COUNT(*) as sales, SUM(student_supply_sales.total_amount - student_supply_sales.returned_amount) as amount')
                ->orderByDesc('amount')->get()->map(fn ($r): array => $this->line($r->label, null, (int) $r->sales, (float) $r->amount))->all(),
            'method' => StudentSupplyPayment::query()
                ->whereBetween('paid_at', [$from, $to])
                ->groupBy('payment_method')
                ->selectRaw("payment_method as label, SUM(CASE WHEN type = 'payment' THEN 1 ELSE 0 END) as payments, SUM(CASE WHEN type = 'payment' THEN amount ELSE -amount END) as amount")
                ->orderByDesc('amount')->get()->map(fn ($r): array => $this->line(config('supplies.payment_methods.'.$r->label, $r->label), null, (int) $r->payments, (float) $r->amount))->all(),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    public function stock(array $filters): array
    {
        [$from, $to] = $this->period($filters);

        $movements = InventoryStockMovement::query()
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('type')
            ->selectRaw('type, COUNT(*) as movements, SUM(ABS(quantity_change)) as units')
            ->get()
            ->keyBy('type');

        $summary = collect(InventoryService::TYPE_LABELS)->map(fn (string $label, string $type): array => [
            'type' => $type,
            'label' => $label,
            'movements' => (int) ($movements[$type]->movements ?? 0),
            'units' => (int) ($movements[$type]->units ?? 0),
        ])->values();

        $listing = fn ($query) => $query->with('category:id,name')->orderBy('quantity_on_hand')->orderBy('name')->limit(200)->get()->map(fn (InventoryItem $item): array => [
            'id' => $item->id, 'sku' => $item->sku, 'name' => $item->name, 'category' => $item->category->name,
            'quantity' => $item->quantity_on_hand, 'reorderLevel' => $item->reorder_level, 'unit' => $item->unit,
        ])->all();

        $valuation = InventoryItem::query()->selectRaw('COALESCE(SUM(quantity_on_hand * cost_price), 0) as cost, COALESCE(SUM(quantity_on_hand * selling_price), 0) as retail')->first();

        return [
            'summary' => $summary->all(),
            'low' => $listing(InventoryItem::query()->active()->lowStock()),
            'out' => $listing(InventoryItem::query()->active()->outOfStock()),
            'valuation' => ['cost' => (float) $valuation->cost, 'retail' => (float) $valuation->retail],
        ];
    }

    /** @return array{rows: list<array<string, mixed>>, total: float} */
    public function outstanding(array $filters): array
    {
        $rows = StudentSupplySale::query()->outstanding()
            ->with(['student:id,first_name,middle_name,last_name,admission_number', 'schoolClass:id,name'])
            ->orderBy('sold_at')
            ->limit(500)
            ->get()
            ->map(fn (StudentSupplySale $sale): array => [
                'id' => $sale->id,
                'saleNumber' => $sale->sale_number,
                'student' => trim(implode(' ', array_filter([$sale->student->first_name, $sale->student->middle_name, $sale->student->last_name]))),
                'admissionNumber' => $sale->student->admission_number,
                'class' => $sale->schoolClass?->name,
                'soldAt' => $sale->sold_at->toDateString(),
                'total' => $sale->netDue(),
                'paid' => $sale->netPaid(),
                'balance' => $sale->balance(),
            ]);

        return ['rows' => $rows->all(), 'total' => round($rows->sum('balance'), 2)];
    }

    /** Completion of class requirements across the year, class by class. */
    public function requirementCompletion(AcademicYear $year): array
    {
        return $year->classes()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
            ->map(function ($class) use ($year): ?array {
                $result = $this->requirements->completion($year, $class);
                if ($result['requirements'] === []) {
                    return null;
                }

                $students = collect($result['students']);
                $complete = $students->where('complete', true)->count();

                return [
                    'classId' => $class->id,
                    'class' => $class->name,
                    'requirements' => count($result['requirements']),
                    'students' => $students->count(),
                    'complete' => $complete,
                    'percent' => $students->count() > 0 ? round($complete / $students->count() * 100, 1) : 0.0,
                ];
            })->filter()->values()->all();
    }

    /**
     * Headings and rows for the CSV download of a report.
     *
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    public function export(string $report, array $filters, ?AcademicYear $year = null): array
    {
        if ($report === 'sales') {
            $data = $this->sales($filters);

            return [['Period', 'Sales', 'Net sales', 'Discounts', 'Collected'], collect($data['rows'])->map(fn ($r): array => [$r['period'], $r['sales'], $r['net'], $r['discounts'], $r['collected']])->all()];
        }
        if (in_array($report, self::BREAKDOWNS, true)) {
            return [['Name', 'Detail', 'Count / units', 'Amount', 'Cost'], collect($this->breakdown($report, $filters))->map(fn ($r): array => [$r['label'], $r['detail'], $r['count'], $r['amount'], $r['cost']])->all()];
        }
        if ($report === 'stock') {
            $data = $this->stock($filters);

            return [['Movement', 'Count', 'Units'], collect($data['summary'])->map(fn ($r): array => [$r['label'], $r['movements'], $r['units']])->all()];
        }
        if ($report === 'low-stock') {
            $data = $this->stock($filters);

            return [['SKU', 'Item', 'Category', 'On hand', 'Reorder level'], collect($data['out'])->merge($data['low'])->map(fn ($r): array => [$r['sku'], $r['name'], $r['category'], $r['quantity'], $r['reorderLevel']])->all()];
        }
        if ($report === 'outstanding') {
            $data = $this->outstanding($filters);

            return [['Sale', 'Student', 'Admission no.', 'Class', 'Sold', 'Total', 'Paid', 'Balance'], collect($data['rows'])->map(fn ($r): array => [$r['saleNumber'], $r['student'], $r['admissionNumber'], $r['class'], $r['soldAt'], $r['total'], $r['paid'], $r['balance']])->all()];
        }
        if ($report === 'requirements' && $year) {
            return [['Class', 'Requirements', 'Students', 'Complete', 'Percent'], collect($this->requirementCompletion($year))->map(fn ($r): array => [$r['class'], $r['requirements'], $r['students'], $r['complete'], $r['percent']])->all()];
        }

        return [[], []];
    }

    // ------------------------------------------------------------------ helpers

    private function line(string $label, ?string $detail, int $count, float $amount, ?float $cost = null): array
    {
        return ['label' => $label, 'detail' => $detail, 'count' => $count, 'amount' => round($amount, 2), 'cost' => $cost === null ? null : round($cost, 2)];
    }

    private function bucket(string $day, string $granularity, Collection $terms): string
    {
        $date = Carbon::parse($day);

        return match ($granularity) {
            'week' => 'Week of '.$date->copy()->startOfWeek()->toDateString(),
            'month' => $date->format('Y-m'),
            'year' => $date->format('Y'),
            'term' => $terms->first(fn (Term $term): bool => $date->betweenIncluded($term->start_date->copy()->startOfDay(), $term->end_date->copy()->endOfDay()))?->name ?? 'Outside any term',
            default => $day,
        };
    }
}