<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\Rest;

/**
 * A crude per-client rate limit for the public scoring endpoints.
 *
 * v1 exposed an unauthenticated endpoint with no nonce, no capability check and
 * no limit, which could be hammered until Google rate-limited the site and the
 * config was overwritten with empty arrays. These endpoints are genuinely
 * public - the quiz must work for logged-out visitors - so the protection has
 * to be a limit rather than a permission.
 *
 * The client key is a salted hash of the IP. The raw address is never stored,
 * which keeps this consistent with recording no IP in telemetry (spec 7); the
 * hash lives only in a short-lived transient.
 */
final class RateLimiter
{
    private const PREFIX = 'skilitsa_rl_';

    public function __construct(
        private readonly int $maxRequests = 60,
        private readonly int $windowSeconds = 60,
    ) {
    }

    public function allows(string $bucket): bool
    {
        $key   = self::PREFIX . $bucket . '_' . $this->clientHash();
        $count = get_transient($key);

        if ($count === false) {
            set_transient($key, 1, $this->windowSeconds);

            return true;
        }

        $count = (int) $count;
        if ($count >= $this->maxRequests) {
            return false;
        }

        set_transient($key, $count + 1, $this->windowSeconds);

        return true;
    }

    /**
     * A salted, truncated hash of the client address.
     *
     * Salted with wp_salt so the value is meaningless outside this install, and
     * discarded with the transient.
     */
    private function clientHash(): string
    {
        $address = '';
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $header) {
            if (!empty($_SERVER[$header])) {
                $raw = (string) $_SERVER[$header];
                // X-Forwarded-For may carry a chain; the first entry is the client.
                $address = trim(explode(',', $raw)[0]);
                break;
            }
        }

        if ($address === '') {
            return 'unknown';
        }

        return substr(hash_hmac('sha256', $address, wp_salt('nonce')), 0, 16);
    }
}
