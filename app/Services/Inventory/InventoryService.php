<?php

namespace App\Services\Inventory;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every stock change goes through here. Rows are locked with SELECT ... FOR UPDATE
 * inside a transaction, so two customers buying the last unit at the same moment
 * are serialised: the second sees the updated quantity and is refused.
 */
class InventoryService
{
    /**
     * Reserve stock for an order's items. Must be called inside a DB transaction.
     *
     * @param  array<int, int>  $quantities  variant id => quantity
     * @return array<int, ProductVariant> locked variants keyed by id
     *
     * @throws InsufficientStockException
     */
    public function lockAndCheck(array $quantities): array
    {
        $this->assertInTransaction();

        $ids = array_keys($quantities);
        sort($ids); // consistent lock order prevents deadlocks

        $variants = ProductVariant::query()
            ->with('product')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($quantities as $id => $qty) {
            $variant = $variants->get($id);
            if (! $variant || ! $variant->is_active || ! $variant->product?->isVisible()) {
                throw new InsufficientStockException($variant?->displayName() ?? 'An item', 0);
            }
            if ($variant->track_inventory && $variant->stock_quantity < $qty) {
                throw new InsufficientStockException($variant->displayName(), max(0, $variant->stock_quantity));
            }
        }

        return $variants->all();
    }

    /** Decrement stock for a newly created order. Call after lockAndCheck() in the same transaction. */
    public function deductForOrder(Order $order): void
    {
        $this->assertInTransaction();

        foreach ($order->items as $item) {
            $variant = ProductVariant::query()->lockForUpdate()->find($item->product_variant_id);
            if (! $variant || ! $variant->track_inventory) {
                continue;
            }
            $this->apply($variant, -$item->quantity, 'order_placed', $order, null, "Order {$order->number}");
        }
    }

    /**
     * Put an order's stock back (unpaid, failed, expired or cancelled). Idempotent:
     * stock_released_at guarantees it happens at most once per order.
     */
    public function releaseForOrder(Order $order, ?User $user = null, string $note = 'Order cancelled'): bool
    {
        return DB::transaction(function () use ($order, $user, $note) {
            $fresh = Order::query()->lockForUpdate()->find($order->id);
            if (! $fresh || $fresh->stock_released_at) {
                return false;
            }

            foreach ($fresh->items as $item) {
                $variant = $item->product_variant_id
                    ? ProductVariant::query()->lockForUpdate()->find($item->product_variant_id)
                    : null;
                if ($variant && $variant->track_inventory) {
                    $this->apply($variant, $item->quantity, 'order_released', $fresh, $user, "{$note} ({$fresh->number})");
                }
            }

            $fresh->forceFill(['stock_released_at' => now()])->save();
            $order->stock_released_at = $fresh->stock_released_at;

            return true;
        });
    }

    /** Manual adjustment from the admin. $change may be negative. */
    public function adjust(ProductVariant $variant, int $change, string $reason, ?User $user, ?string $note = null): StockMovement
    {
        return DB::transaction(function () use ($variant, $change, $reason, $user, $note) {
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);
            if ($locked->stock_quantity + $change < 0) {
                throw new \InvalidArgumentException("Stock cannot go below zero (currently {$locked->stock_quantity}).");
            }
            $movement = $this->apply($locked, $change, $reason, null, $user, $note);
            $variant->stock_quantity = $locked->stock_quantity;

            return $movement;
        });
    }

    /** Set an absolute stock level (records the difference as a movement). */
    public function setLevel(ProductVariant $variant, int $level, string $reason, ?User $user, ?string $note = null): ?StockMovement
    {
        return DB::transaction(function () use ($variant, $level, $reason, $user, $note) {
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);
            $change = $level - $locked->stock_quantity;
            if ($change === 0) {
                return null;
            }
            $movement = $this->apply($locked, $change, $reason, null, $user, $note);
            $variant->stock_quantity = $locked->stock_quantity;

            return $movement;
        });
    }

    private function apply(ProductVariant $variant, int $change, string $reason, ?Order $order, ?User $user, ?string $note): StockMovement
    {
        $variant->stock_quantity += $change;
        $variant->save();

        return StockMovement::query()->create([
            'product_variant_id' => $variant->id,
            'order_id' => $order?->id,
            'user_id' => $user?->id,
            'quantity_change' => $change,
            'quantity_after' => $variant->stock_quantity,
            'reason' => $reason,
            'note' => $note,
        ]);
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Stock reservation must run inside a database transaction.');
        }
    }
}
