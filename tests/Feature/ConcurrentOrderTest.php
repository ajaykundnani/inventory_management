<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * Forks real OS processes that order the same product at the same moment against the
 * MySQL test database, exercising InnoDB row locks. Child processes can only see committed
 * data, so this test commits its own fixtures instead of using RefreshDatabase and wipes
 * the (dedicated) test tables afterwards.
 */
class ConcurrentOrderTest extends TestCase
{
    private const WORKERS = 12;

    private const INITIAL_STOCK = 5;

    private const EXIT_SUCCESS = 0;

    private const EXIT_INSUFFICIENT = 10;

    private const EXIT_OTHER_ERROR = 20;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension is required for the concurrency test.');
        }

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('The concurrency test requires the MySQL test database.');
        }

        Artisan::call('migrate', ['--force' => true]);
        $this->wipeTables();
    }

    protected function tearDown(): void
    {
        DB::reconnect();
        $this->wipeTables();
        parent::tearDown();
    }

    public function test_concurrent_orders_never_oversell(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => self::INITIAL_STOCK]);

        // Children must not inherit the parent's open MySQL connection.
        DB::disconnect();

        $startAt = microtime(true) + 1.0;
        $pids = [];

        for ($i = 0; $i < self::WORKERS; $i++) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'fork failed');

            if ($pid === 0) {
                $this->runWorker($user->id, $product->id, $startAt);
            }

            $pids[] = $pid;
        }

        $outcomes = [self::EXIT_SUCCESS => 0, self::EXIT_INSUFFICIENT => 0, self::EXIT_OTHER_ERROR => 0];
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            $code = pcntl_wexitstatus($status);
            $outcomes[$code] = ($outcomes[$code] ?? 0) + 1;
        }

        DB::reconnect();
        $finalStock = $product->fresh()->stock_quantity;
        $orders = Order::count();
        $sold = (int) DB::table('order_items')->sum('quantity');

        $this->assertSame(self::WORKERS, array_sum($outcomes));
        $this->assertSame(self::INITIAL_STOCK, $finalStock + $sold, 'stock + sold must equal initial stock');
        $this->assertSame($outcomes[self::EXIT_SUCCESS], $orders);

        // Exactly the available stock is sold; every other buyer gets a clean insufficient-stock error.
        $this->assertSame(self::INITIAL_STOCK, $outcomes[self::EXIT_SUCCESS], json_encode($outcomes));
        $this->assertSame(self::WORKERS - self::INITIAL_STOCK, $outcomes[self::EXIT_INSUFFICIENT], json_encode($outcomes));
        $this->assertSame(0, $outcomes[self::EXIT_OTHER_ERROR], json_encode($outcomes));
        $this->assertSame(0, $finalStock);
    }

    private function wipeTables(): void
    {
        foreach (['order_items', 'orders', 'products', 'users'] as $table) {
            DB::table($table)->delete();
        }
    }

    /**
     * Runs inside the forked child. pcntl_exec replaces the process so PHPUnit's
     * shutdown handlers never run in the child; only the exit code is reported.
     */
    private function runWorker(int $userId, int $productId, float $startAt): never
    {
        $code = self::EXIT_OTHER_ERROR;

        try {
            DB::purge();
            $user = User::findOrFail($userId);

            while (microtime(true) < $startAt) {
                usleep(1000);
            }

            app(OrderService::class)->create($user, [
                'customer_name' => 'Concurrent buyer',
                'items' => [['product_id' => $productId, 'quantity' => 1]],
            ]);
            $code = self::EXIT_SUCCESS;
        } catch (InsufficientStockException) {
            $code = self::EXIT_INSUFFICIENT;
        } catch (Throwable) {
            $code = self::EXIT_OTHER_ERROR;
        }

        pcntl_exec('/bin/sh', ['-c', 'exit '.$code]);
        exit($code);
    }
}
