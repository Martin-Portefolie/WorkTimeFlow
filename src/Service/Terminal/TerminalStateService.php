<?php
namespace App\Service\Terminal;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;


/**
 * TerminalStateService
 *
 * Lightweight state manager for interactive (“wizard”) terminal commands.
 *
 * This service lets the terminal remember temporary, per-user state between
 * sequential prompts — for example, when adding a new user or client through
 * a step-by-step question flow.
 *
 *  Key features:
 *  - Persists transient state per user (via PSR-6 cache backend).
 *  - Automatically expires states (default TTL = 15 minutes).
 *  - Each state stores arbitrary data like:
 *      [
 *          'mode' => 'clients:add', // which wizard is active
 *          'step' => 3,             // which question we’re on
 *          'data' => [ ... ],       // collected answers so far
 *      ]
 *
 * Typical usage flow:
 *  1. `set($userId, $state)` — start or advance a wizard.
 *  2. `get($userId)` — retrieve current wizard progress.
 *  3. `clear($userId)` — cancel or complete a wizard.
 *
 * Example scenario:
 *   Terminal > c.a
 *   WorkTimeFlow > What name should the client have?
 *   Terminal > ACME Corp
 *   WorkTimeFlow > Client email?
 *   ...
 *
 * The state is cleared automatically when finished or canceled.
 */
final readonly class TerminalStateService
{

    public function __construct(private CacheItemPoolInterface $cache) {}

    private function key(string $userId): string
    {
        // Disallow forbidden chars by hashing the user id.
        // Keep it short to play nice with backends that have key length limits.
        $hid = substr(hash('sha1', $userId), 0, 20); // only [0-9a-f]
        return 'twz_' . $hid;
    }

    /** @return array|null */
    public function get(string $userId): ?array
    {
        $it = $this->cache->getItem($this->key($userId));
        return $it->isHit() ? $it->get() : null;
    }

    public function set(string $userId, array $state, int $ttlSeconds = 900): void
    {
        $it = $this->cache->getItem($this->key($userId));
        $it->set($state);
        $it->expiresAfter($ttlSeconds);
        $this->cache->save($it);
    }

    public function clear(string $userId): void
    {
        $this->cache->deleteItem($this->key($userId));
    }

}
