<?php

/*
|--------------------------------------------------------------------------
| Interview Exercise: Order Placement
|--------------------------------------------------------------------------
|
| Implement:
|
|     OrderController::placeOrder(array $payload): Response
|
| You may add helper methods/classes if you want.
| You may modify OrderController only unless you clearly explain why.
|
| Focus on:
| - clear backend flow
| - validation
| - idempotency
| - payment handling
| - queueing
| - logging
| - production thinking
|
| Do NOT build a framework.
| Do NOT over-engineer.
|
|--------------------------------------------------------------------------
| Example Payload
|--------------------------------------------------------------------------
|
| [
|     'customerId' => 123,
|     'items' => [
|         ['productId' => 10, 'quantity' => 2],
|         ['productId' => 15, 'quantity' => 1],
|     ],
|     'paymentMethodId' => 'pm_abc123',
|     'idempotencyKey' => 'customer-123-order-001'
| ]
|
|--------------------------------------------------------------------------
| Requirements
|--------------------------------------------------------------------------
|
| 1. Validate input:
|
| Required:
| - customerId
| - items, non-empty array
| - each item must have productId
| - each item must have quantity > 0
| - paymentMethodId
| - idempotencyKey
|
| Invalid payload should return HTTP 400.
|
|
| 2. Prevent duplicates:
|
| If an order already exists with the same idempotencyKey:
| - do not create another order
| - do not charge payment again
| - do not queue another fulfillment job
| - return HTTP 200 or 409
| - explain your choice during discussion
|
|
| 3. Calculate total:
|
| Load products from ProductRepository.
| Total = sum(product price * quantity).
|
| If a product does not exist, return HTTP 400.
|
|
| 4. Create order:
|
| Create an order with:
| - customerId
| - items
| - total
| - status = 'pending_payment'
| - idempotencyKey
|
|
| 5. Charge payment:
|
| Use:
|
| PaymentGateway::charge([
|     'customerId' => ...,
|     'paymentMethodId' => ...,
|     'amount' => ...
| ]);
|
|
| 6. Update order:
|
| If payment succeeds:
| - status = 'paid'
|
| If payment fails:
| - status = 'payment_failed'
| - return HTTP 402 or 400
| - explain your choice during discussion
|
|
| 7. Queue fulfillment:
|
| Only after successful payment:
|
| Queue::push('FulfillOrderJob', [
|     'orderId' => ...
| ]);
|
|
| 8. Return:
|
| New successful order:
| - HTTP 201
|
| Duplicate:
| - HTTP 200 or 409
|
| Invalid:
| - HTTP 400
|
| Payment failed:
| - HTTP 402 or 400
|
| Unexpected error:
| - HTTP 500
|
|
| 9. Add useful logs:
|
| - validation failure
| - duplicate detected
| - order created
| - payment attempted
| - payment succeeded
| - payment failed
| - fulfillment queued
| - unexpected error
|
|
|
*/


class OrderController
{
    public function placeOrder(array $payload): Response
    {
       
    }
}



/*
|--------------------------------------------------------------------------
| Basic Infrastructure
|--------------------------------------------------------------------------
|
| You should not need to modify this section.
|
*/


class Response
{
    public function __construct(
        public int $status,
        public array $body = []
    ) {}
}


class ProductRepository
{
    public static array $products = [
        10 => [
            'id' => 10,
            'name' => 'T-Shirt',
            'price' => 2500, // cents
        ],
        15 => [
            'id' => 15,
            'name' => 'Hat',
            'price' => 1500, // cents
        ],
        20 => [
            'id' => 20,
            'name' => 'Shoes',
            'price' => 8000, // cents
        ],
    ];

    public static function findById(int $productId): ?array
    {
        return self::$products[$productId] ?? null;
    }
}


class OrderRepository
{
    public static array $orders = [];

    public static function create(array $data): array
    {
        $id = count(self::$orders) + 1;

        $order = [
            'id' => $id,
            ...$data,
        ];

        self::$orders[$id] = $order;

        return $order;
    }

    public static function findByIdempotencyKey(string $key): ?array
    {
        foreach (self::$orders as $order) {
            if (($order['idempotencyKey'] ?? null) === $key) {
                return $order;
            }
        }

        return null;
    }

    public static function updateStatus(int $orderId, string $status): void
    {
        if (isset(self::$orders[$orderId])) {
            self::$orders[$orderId]['status'] = $status;
        }
    }
}


class PaymentGateway
{
    public static bool $shouldFail = false;
    public static array $charges = [];

    public static function charge(array $payload): array
    {
        self::$charges[] = $payload;

        if (self::$shouldFail) {
            return [
                'success' => false,
                'error' => 'Card declined',
            ];
        }

        return [
            'success' => true,
            'transactionId' => 'txn_' . (count(self::$charges)),
        ];
    }
}


class Queue
{
    public static array $jobs = [];

    public static function push(string $jobName, array $payload): void
    {
        self::$jobs[] = [
            'job' => $jobName,
            'payload' => $payload,
        ];
    }
}


class Logger
{
    public static array $logs = [];

    public static function info(string $message, array $context = []): void
    {
        self::$logs[] = [
            'level' => 'info',
            'message' => $message,
            'context' => $context,
        ];
    }

    public static function error(string $message, array $context = []): void
    {
        self::$logs[] = [
            'level' => 'error',
            'message' => $message,
            'context' => $context,
        ];
    }
}



/*
|--------------------------------------------------------------------------
| Test Helpers
|--------------------------------------------------------------------------
*/


function resetState(): void
{
    OrderRepository::$orders = [];
    PaymentGateway::$charges = [];
    PaymentGateway::$shouldFail = false;
    Queue::$jobs = [];
    Logger::$logs = [];
}


function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new Exception("FAILED: " . $message);
    }

    echo "PASS: " . $message . PHP_EOL;
}


function assertEquals(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new Exception(
            "FAILED: {$message}. Expected "
            . var_export($expected, true)
            . ", got "
            . var_export($actual, true)
        );
    }

    echo "PASS: " . $message . PHP_EOL;
}



/*
|--------------------------------------------------------------------------
| Tests
|--------------------------------------------------------------------------
|
| These tests should pass after your implementation.
|
*/


$controller = new OrderController();



/*
|--------------------------------------------------------------------------
| Test 1: successful order
|--------------------------------------------------------------------------
*/

resetState();

$response = $controller->placeOrder([
    'customerId' => 123,
    'items' => [
        ['productId' => 10, 'quantity' => 2],
        ['productId' => 15, 'quantity' => 1],
    ],
    'paymentMethodId' => 'pm_abc123',
    'idempotencyKey' => 'customer-123-order-001',
]);

assertEquals(201, $response->status, 'successful order returns HTTP 201');

assertEquals(1, count(OrderRepository::$orders), 'creates one order');

$order = OrderRepository::$orders[1];

assertEquals('paid', $order['status'], 'order status becomes paid');

assertEquals(6500, $order['total'], 'calculates total correctly');

assertEquals(1, count(PaymentGateway::$charges), 'charges payment once');

assertEquals(1, count(Queue::$jobs), 'queues one fulfillment job');

assertEquals('FulfillOrderJob', Queue::$jobs[0]['job'], 'queues correct job');



/*
|--------------------------------------------------------------------------
| Test 2: duplicate idempotency key
|--------------------------------------------------------------------------
*/

$response = $controller->placeOrder([
    'customerId' => 123,
    'items' => [
        ['productId' => 10, 'quantity' => 2],
    ],
    'paymentMethodId' => 'pm_abc123',
    'idempotencyKey' => 'customer-123-order-001',
]);

assertTrue(
    in_array($response->status, [200, 409], true),
    'duplicate returns HTTP 200 or 409'
);

assertEquals(1, count(OrderRepository::$orders), 'duplicate does not create order');

assertEquals(1, count(PaymentGateway::$charges), 'duplicate does not charge again');

assertEquals(1, count(Queue::$jobs), 'duplicate does not queue again');



/*
|--------------------------------------------------------------------------
| Test 3: invalid payload
|--------------------------------------------------------------------------
*/

resetState();

$response = $controller->placeOrder([
    'customerId' => 123,
]);

assertEquals(400, $response->status, 'invalid payload returns HTTP 400');

assertEquals(0, count(OrderRepository::$orders), 'invalid payload creates no order');

assertEquals(0, count(PaymentGateway::$charges), 'invalid payload does not charge');

assertEquals(0, count(Queue::$jobs), 'invalid payload does not queue');



/*
|--------------------------------------------------------------------------
| Test 4: unknown product
|--------------------------------------------------------------------------
*/

resetState();

$response = $controller->placeOrder([
    'customerId' => 123,
    'items' => [
        ['productId' => 999, 'quantity' => 1],
    ],
    'paymentMethodId' => 'pm_abc123',
    'idempotencyKey' => 'bad-product-order',
]);

assertEquals(400, $response->status, 'unknown product returns HTTP 400');

assertEquals(0, count(OrderRepository::$orders), 'unknown product creates no order');

assertEquals(0, count(PaymentGateway::$charges), 'unknown product does not charge');



/*
|--------------------------------------------------------------------------
| Test 5: payment failure
|--------------------------------------------------------------------------
*/

resetState();

PaymentGateway::$shouldFail = true;

$response = $controller->placeOrder([
    'customerId' => 123,
    'items' => [
        ['productId' => 10, 'quantity' => 1],
    ],
    'paymentMethodId' => 'pm_declined',
    'idempotencyKey' => 'payment-failure-order',
]);

assertTrue(
    in_array($response->status, [400, 402], true),
    'payment failure returns HTTP 400 or 402'
);

assertEquals(1, count(OrderRepository::$orders), 'payment failure still creates order');

$order = OrderRepository::$orders[1];

assertEquals('payment_failed', $order['status'], 'failed payment updates order status');

assertEquals(1, count(PaymentGateway::$charges), 'payment was attempted');

assertEquals(0, count(Queue::$jobs), 'failed payment does not queue fulfillment');



/*
|--------------------------------------------------------------------------
| Test 6: logs
|--------------------------------------------------------------------------
*/

assertTrue(
    count(Logger::$logs) > 0,
    'adds useful logs'
);



echo PHP_EOL . "ALL TESTS PASSED" . PHP_EOL;
