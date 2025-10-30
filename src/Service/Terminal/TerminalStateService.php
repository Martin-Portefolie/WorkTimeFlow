<?php
namespace App\Service\Terminal;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

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
