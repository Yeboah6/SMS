<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Concerns\FormatsSupplies;
use App\Http\Requests\CancelSaleRequest;
use App\Http\Requests\RecordSupplyPaymentRequest;
use App\Http\Requests\ReturnSaleItemsRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Models\AcademicYear;
use App\Models\InventoryItem;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentSupplySale;
use App\Models\StudentSupplySaleItem;
use App\Services\SupplyPaymentService;
use App\Services\SupplySaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    use FormatsSupplies;

    private const VIEWS = ['outstanding', 'paid', 'cancelled', 'returned'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StudentSupplySale::class);
        $user = $request->user();

        $search = $request->string('search')->trim()->toString();
        $view = $request->string('view')->toString();
        $from = $request->filled('from') ? $request->date('from')?->startOfDay() : null;
        $to = $request->filled('to') ? $request->date('to')?->endOfDay() : null;

        $sales = StudentSupplySale::query()
            ->with(['student:id,first_name,middle_name,last_name,admission_number', 'schoolClass:id,name', 'academicYear:id,name', 'seller:id,name'])
            ->when($view === 'outstanding', fn ($query) => $query->outstanding())
            ->when($view === 'paid', fn ($query) => $query->where('payment_status', 'paid'))
            ->when($view === 'cancelled', fn ($query) => $query->where('status', 'cancelled'))
            ->when($view === 'returned', fn ($query) => $query->whereIn('status', ['returned', 'partially_returned']))
            ->when($from, fn ($query) => $query->where('sold_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('sold_at', '<=', $to))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('sale_number', 'like', "%{$search}%")
                ->orWhereHas('student', fn ($students) => $students->where('admission_number', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))))
            ->latest('sold_at')->latest('id')
            ->paginate(20)->withQueryString()
            ->through(fn (StudentSupplySale $sale): array => $this->salePayload($sale));

        return Inertia::render('Supplies/Sales/Index', [
            'sales' => $sales,
            'filters' => ['search' => $search, 'view' => in_array($view, self::VIEWS, true) ? $view : '', 'from' => $from?->toDateString() ?? '', 'to' => $to?->toDateString() ?? ''],
            'counts' => [
                'outstanding' => StudentSupplySale::query()->outstanding()->count(),
                'cancelled' => StudentSupplySale::query()->where('status', 'cancelled')->count(),
            ],
            'can' => ['create' => $user->can('create', StudentSupplySale::class), 'export' => $user->can('supplies.reports.export')],
        ]);
    }

    /** The multi-item sale screen. */
    public function create(Request $request): Response
    {
        $this->authorize('create', StudentSupplySale::class);
        $user = $request->user();

        $student = $request->filled('student')
            ? Student::query()->where('status', 'active')->find($request->integer('student'))
            : null;

        return Inertia::render('Supplies/Sales/Create', [
            'items' => InventoryItem::query()->active()->with('category:id,name')->orderBy('name')->get()->map(fn (InventoryItem $item): array => $this->itemPayload($item))->values(),
            'student' => $student ? ['id' => $student->id, 'name' => $this->studentName($student), 'admissionNumber' => $student->admission_number, 'className' => null] : null,
            'paymentMethods' => $this->paymentMethods(),
            'policy' => [
                'allowPartial' => (bool) config('supplies.allow_partial_payment'),
                'canDiscount' => $user->can('supplies.sales.discount'),
                'canOverride' => (bool) config('supplies.allow_stock_override') && $user->can('supplies.stock.override'),
                'hasActiveYear' => AcademicYear::active()->exists(),
            ],
            // One key per page load: submitting twice can only ever record one payment.
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(StoreSaleRequest $request, SupplySaleService $service): RedirectResponse
    {
        $sale = $service->create($request->validated(), $request->user());

        return to_route('supplies.sales.show', $sale)->with('status', "Sale {$sale->sale_number} recorded.");
    }

    public function show(Request $request, StudentSupplySale $sale): Response
    {
        $this->authorize('view', $sale);
        $user = $request->user();
        $this->loadDetails($sale);

        return Inertia::render('Supplies/Sales/Show', [
            'sale' => $this->salePayload($sale, true),
            'paymentMethods' => $this->paymentMethods(),
            'policy' => ['allowPartial' => (bool) config('supplies.allow_partial_payment')],
            'idempotencyKey' => (string) Str::uuid(),
            'today' => today()->toDateString(),
            'can' => [
                'pay' => $user->can('recordPayment', $sale),
                'refund' => $user->can('refund', $sale),
                'cancel' => $user->can('cancel', $sale),
                'deliver' => $user->can('supplies.sales.create') && ! in_array($sale->status, ['cancelled', 'returned'], true),
            ],
        ]);
    }

    public function receipt(StudentSupplySale $sale): Response
    {
        $this->authorize('view', $sale);
        $this->loadDetails($sale);

        return Inertia::render('Supplies/Sales/Receipt', [
            'sale' => $this->salePayload($sale, true),
            'schoolName' => SchoolSetting::query()->where('singleton_key', 'primary')->value('school_name') ?? config('app.name'),
        ]);
    }

    public function storePayment(RecordSupplyPaymentRequest $request, StudentSupplySale $sale, SupplyPaymentService $payments): RedirectResponse
    {
        $payment = $payments->record($sale, $request->validated(), $request->user());

        return to_route('supplies.sales.show', $sale)->with('status', "Payment recorded. Receipt {$payment->receipt_number}.");
    }

    public function returnItems(ReturnSaleItemsRequest $request, StudentSupplySale $sale, SupplySaleService $service): RedirectResponse
    {
        $service->returnItems($sale, $request->validated(), $request->user());

        return to_route('supplies.sales.show', $sale)->with('status', 'Return recorded.');
    }

    public function cancel(CancelSaleRequest $request, StudentSupplySale $sale, SupplySaleService $service): RedirectResponse
    {
        $service->cancel($sale, $request->validated(), $request->user());

        return to_route('supplies.sales.show', $sale)->with('status', 'Sale cancelled and stock restored.');
    }

    public function deliver(Request $request, StudentSupplySale $sale, StudentSupplySaleItem $saleItem, SupplySaleService $service): RedirectResponse
    {
        abort_unless($saleItem->sale_id === $sale->id, 404);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:10000']]);

        $service->deliverBackorder($saleItem, (int) $data['quantity'], $request->user());

        return back()->with('status', 'Backordered items handed over.');
    }

    private function loadDetails(StudentSupplySale $sale): void
    {
        $sale->load(['student:id,first_name,middle_name,last_name,admission_number', 'schoolClass:id,name', 'academicYear:id,name', 'seller:id,name', 'canceller:id,name', 'items', 'payments.recorder:id,name']);
    }
}