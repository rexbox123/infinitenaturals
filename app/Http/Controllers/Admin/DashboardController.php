<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\SetupChecklist;

class DashboardController extends Controller
{
    public function index(SetupChecklist $checklist)
    {
        $sales = fn ($from) => Order::query()->sales()->where('paid_at', '>=', $from);

        $stats = [
            'today' => (int) $sales(now()->startOfDay())->sum('total'),
            'week' => (int) $sales(now()->subDays(7))->sum('total'),
            'month' => (int) $sales(now()->subDays(30))->sum('total'),
            'orders_month' => $sales(now()->subDays(30))->count(),
            'to_fulfil' => Order::query()
                ->where(fn ($q) => $q->whereIn('payment_status', [PaymentStatus::Paid])
                    ->orWhere(fn ($c) => $c->where('payment_method', 'cod')->where('payment_status', PaymentStatus::Pending)))
                ->whereIn('fulfillment_status', [FulfillmentStatus::Unfulfilled, FulfillmentStatus::Processing])->count(),
            'needs_review' => Order::query()->where('needs_review', true)->count(),
            'unread_messages' => ContactMessage::query()->whereNull('read_at')->count(),
        ];
        $stats['aov'] = $stats['orders_month'] ? intdiv($stats['month'], $stats['orders_month']) : 0;

        $daily = Order::query()->sales()->where('paid_at', '>=', now()->subDays(13)->startOfDay())->get(['paid_at', 'total'])
            ->groupBy(fn ($o) => $o->paid_at->toDateString())->map->sum('total');
        $chart = collect(range(13, 0))->map(fn ($d) => now()->subDays($d)->toDateString())
            ->mapWithKeys(fn ($date) => [$date => (int) ($daily[$date] ?? 0)]);

        return view('admin.dashboard', [
            'stats' => $stats,
            'chart' => $chart,
            'recentOrders' => Order::query()->latest('id')->take(8)->get(),
            'lowStock' => ProductVariant::query()->with('product')
                ->where('track_inventory', true)->where('is_active', true)
                ->where('stock_quantity', '<=', (int) setting('inventory.low_stock_threshold', 5))
                ->whereHas('product', fn ($q) => $q->where('status', 'active'))
                ->orderBy('stock_quantity')->take(10)->get(),
            'checklist' => auth()->user()->can('settings.manage') ? $checklist->items() : [],
        ]);
    }
}
