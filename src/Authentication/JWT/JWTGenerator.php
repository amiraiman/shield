<?php

declare(strict_types=1);

namespace CodeIgniter\Shield\Authentication\JWT;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\JWT\Adapters\FirebaseAdapter;
use CodeIgniter\Shield\Config\AuthJWT;
use CodeIgniter\Shield\Entities\User;

class JWTGenerator
{
    private Time $clock;
    private JWTAdapterInterface $jwtAdapter;

    public function __construct(?Time $clock = null, ?JWTAdapterInterface $jwtAdapter = null)
    {
        $this->clock      = $clock ?? new Time();
        $this->jwtAdapter = $jwtAdapter ?? new FirebaseAdapter();
    }

    /**
     * Issues JWT (JWS) for a User
     *
     * @param array                      $claims  The payload items.
     * @param int|null                   $ttl     Time to live in seconds.
     * @param string                     $keyset  The key group.
     *                                            The array key of Config\AuthJWT::$keys.
     * @param array<string, string>|null $headers An array with header elements to attach.
     */
    public function generateAccessToken(
        User $user,
        array $claims = [],
        ?int $ttl = null,
        $keyset = 'default',
        ?array $headers = null
    ): string {
        $payload = array_merge(
            $claims,
            [
                'sub' => (string) $user->id, // subject
            ],
        );

        return $this->generate($payload, $ttl, $keyset, $headers);
    }

    /**
     * Issues JWT (JWS)
     *
     * @param array                      $claims  The payload items.
     * @param int|null                   $ttl     Time to live in seconds.
     * @param string                     $keyset  The key group.
     *                                            The array key of Config\AuthJWT::$keys.
     * @param array<string, string>|null $headers An array with header elements to attach.
     */
    public function generate(
        array $claims,
        ?int $ttl = null,
        $keyset = 'default',
        ?array $headers = null
    ): string {
        assert(
            (array_key_exists('exp', $claims) && ($ttl !== null)) === false,
            'Cannot pass $claims[\'exp\'] and $ttl at the same time.'
        );

        $config = config(AuthJWT::class);

        $payload = array_merge(
            $config->defaultClaims,
            $claims
        );

        if (! array_key_exists('iat', $claims)) {
            $payload['iat'] = $this->clock->now()->getTimestamp();
        }

        if (! array_key_exists('exp', $claims)) {
            $payload['exp'] = $payload['iat'] + $config->timeToLive;
        }

        if ($ttl !== null) {
            $payload['exp'] = $payload['iat'] + $ttl;
        }

        return $this->jwtAdapter->encode(
            $payload,
            $keyset,
            $headers
        );
    }
}
