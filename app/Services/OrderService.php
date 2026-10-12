<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    /** Transaction retries for deadlocks / lock timeouts detected by Laravel. */
    private const TRANSACTION_ATTEMPTS = 3;

    /**
     * Create an order atomically. Stock is reserved first with conditional UPDATEs, which take
     * the write/row lock before anything is read; prices are then read from the locked rows.
     * Any failure rolls back the order, its items and every stock change.
     *
     * @param  array{customer_name: string, notes?: ?string, items: list<array{product_id: int, quantity: int}>}  $data
     */
    public function create(User $user, array $data): Order
    {
        $lines = collect($data['items'])->values();

        $order = DB::transaction(function () use ($user, $data, $lines) {
            $this->reserveStock($lines);

            $products = Product::whereIn('id', $lines->pluck('product_id'))->get()->keyBy('id');

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $user->id,
                'customer_name' => $data['customer_name'],
                'notes' => $data['notes'] ?? null,
                'status' => OrderStatus::Pending,
                'total' => 0,
            ]);

            $totalCents = 0;

            foreach ($lines as $line) {
                $product = $products[$line['product_id']];
                $quantity = (int) $line['quantity'];
                $lineCents = Money::toCents($product->price) * $quantity;
                $totalCents += $lineCents;

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'line_total' => Money::fromCents($lineCents),
                ]);
            }

            $order->update(['total' => Money::fromCents($totalCents)]);

            return $order;
        }, self::TRANSACTION_ATTEMPTS);

        return $order->load(['items', 'user']);
    }

    /**
     * Move an order to a new status. The order row is locked and re-read so concurrent
     * cancellations cannot restore stock twice; cancelled is terminal.
     */
    public function updateStatus(Order $order, OrderStatus $target): Order
    {
        $updated = DB::transaction(function () use ($order, $target) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo($target)) {
                throw new InvalidStatusTransitionException($locked->status, $target);
            }

            if ($target === OrderStatus::Cancelled) {
                $this->restoreStock($locked);
                $locked->cancelled_at = now();
            }

            $locked->status = $target;
            $locked->save();

            return $locked;
        }, self::TRANSACTION_ATTEMPTS);

        return $updated->load(['items', 'user']);
    }

    /**
     * Atomically decrement stock for every line, in ascending product id order so concurrent
     * orders lock rows in the same sequence (avoids deadlocks). The WHERE clause makes the
     * check-and-decrement a single statement, so stock can never go below zero. On InnoDB each
     * UPDATE holds an exclusive row lock until commit, equivalent to SELECT ... FOR UPDATE.
     */
    private function reserveStock(Collection $lines): void
    {
        $rejected = [];

        foreach ($lines->sortBy('product_id') as $index => $line) {
            $reserved = Product::whereKey($line['product_id'])
                ->where('status', ProductStatus::Active)
                ->where('stock_quantity', '>=', (int) $line['quantity'])
                ->decrement('stock_quantity', (int) $line['quantity']);

            if ($reserved === 0) {
                $rejected[$index] = $line;
            }
        }

        if ($rejected) {
            $this->throwReservationFailure($rejected);
        }
    }

    /**
     * Explain every rejected line at once so the client can show all problems together.
     * The surrounding transaction rolls back any reservations already made.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $rejected
     */
    private function throwReservationFailure(array $rejected): never
    {
        $products = Product::whereIn('id', array_column($rejected, 'product_id'))->get()->keyBy('id');
        $unavailable = [];
        $insufficient = [];

        ksort($rejected);

        foreach ($rejected as $index => $line) {
            $product = $products->get($line['product_id']);

            if (! $product) {
                $unavailable["items.{$index}.product_id"] = ['The selected product does not exist.'];
            } elseif ($product->status !== ProductStatus::Active) {
                $unavailable["items.{$index}.product_id"] = ["{$product->name} is not available for ordering."];
            } else {
                $insufficient["items.{$index}.quantity"] = [
                    "Only {$product->stock_quantity} of {$product->name} in stock.",
                ];
            }
        }

        if ($unavailable) {
            throw new BusinessRuleException('One or more products cannot be ordered.', $unavailable + $insufficient);
        }

        throw new InsufficientStockException($insufficient);
    }

    private function restoreStock(Order $order): void
    {
        $order->items()->orderBy('product_id')->get()->each(function ($item) {
            Product::whereKey($item->product_id)->increment('stock_quantity', $item->quantity);
        });
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }
}
