<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Tests\Support;

use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Result\ApiResponse;

/**
 * In-memory spy transport for job tests.
 *
 * Queue responses per entity.action so that multiple calls to the same endpoint
 * return different values (e.g. Tag.get before EntityTag.save). When the queue
 * is exhausted an empty {@see ApiResponse} is returned.
 */
final class TestTransport implements TransportInterface
{
    /** @var array<string, list<ApiResponse>> */
    private array $queues = [];

    /** @var list<array{entity: string, action: string, params: array<string, mixed>}> */
    public array $calls = [];

    /**
     * Enqueues a canned response for the next `send($entity, $action, ...)` call.
     *
     * @param array<array-key, mixed> $values
     */
    public function addResponse(string $entity, string $action, array $values = [], int $count = -1): void
    {
        $key = $entity . '.' . $action;
        $this->queues[$key][] = new ApiResponse(
            4,
            $count === -1 ? count($values) : $count,
            $values,
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws \RuntimeException Never thrown — always returns a response.
     */
    #[\Override]
    public function send(string $entity, string $action, array $params = []): ApiResponse
    {
        $this->calls[] = ['entity' => $entity, 'action' => $action, 'params' => $params];

        $key = $entity . '.' . $action;

        if (isset($this->queues[$key]) && $this->queues[$key] !== []) {
            return array_shift($this->queues[$key]);
        }

        return new ApiResponse(4, 0, []);
    }

    /**
     * Returns all recorded calls for the given entity and action.
     *
     * @return list<array{entity: string, action: string, params: array<string, mixed>}>
     */
    public function callsFor(string $entity, string $action): array
    {
        return array_values(array_filter(
            $this->calls,
            static fn(array $c): bool => $c['entity'] === $entity && $c['action'] === $action,
        ));
    }
}
