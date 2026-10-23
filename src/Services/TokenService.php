<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Services;

use Carbon\CarbonInterface;
use EvolutionCMS\eMCP\Models\EmcpToken;
use EvolutionCMS\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Issues, resolves and revokes personal access tokens.
 *
 * The plaintext token is returned exactly once at creation; only its sha256 hash is stored.
 */
final class TokenService
{
    public const PREFIX = 'emcp_';

    /** @var array<int, string> */
    public const KNOWN_SCOPES = ['mcp:read', 'mcp:call', 'mcp:write', 'mcp:destructive', 'mcp:code', 'mcp:admin'];

    /**
     * @param  array<int, string>  $scopes
     * @return array{token: EmcpToken, plaintext: string}
     */
    public function issue(User $user, string $name, array $scopes, ?CarbonInterface $expiresAt = null): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Token name is required.');
        }

        $scopes = $this->normalizeScopes($scopes);
        if ($scopes === []) {
            throw new InvalidArgumentException('At least one scope is required.');
        }

        $plaintext = self::PREFIX . Str::random(40);

        $token = EmcpToken::query()->create([
            'user_id' => (int)$user->getKey(),
            'name' => Str::limit($name, 100, ''),
            'token_prefix' => substr($plaintext, 0, 12),
            'token_hash' => self::hash($plaintext),
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $token, 'plaintext' => $plaintext];
    }

    /**
     * Resolves a usable token from its plaintext form; null when unknown, revoked or expired.
     */
    public function resolve(string $plaintext): ?EmcpToken
    {
        $plaintext = trim($plaintext);
        if ($plaintext === '' || !str_starts_with($plaintext, self::PREFIX)) {
            return null;
        }

        /** @var EmcpToken|null $token */
        $token = EmcpToken::query()->where('token_hash', self::hash($plaintext))->first();
        if ($token === null || !$token->isUsable()) {
            return null;
        }

        return $token;
    }

    public function touch(EmcpToken $token): void
    {
        // Avoid a write on every call: one update per minute per token is enough for "last used".
        if ($token->last_used_at !== null && $token->last_used_at->diffInSeconds(now()) < 60) {
            return;
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();
    }

    public function revoke(EmcpToken $token): void
    {
        if ($token->isRevoked()) {
            return;
        }

        $token->forceFill(['revoked_at' => now()])->save();
    }

    /**
     * @param  array<int, mixed>  $scopes
     * @return array<int, string>
     */
    public function normalizeScopes(array $scopes): array
    {
        $normalized = [];
        foreach ($scopes as $scope) {
            $scope = strtolower(trim((string)$scope));
            if ($scope === '') {
                continue;
            }

            if (!in_array($scope, self::KNOWN_SCOPES, true)) {
                throw new InvalidArgumentException("Unknown scope [{$scope}]. Allowed: " . implode(', ', self::KNOWN_SCOPES) . '.');
            }

            $normalized[] = $scope;
        }

        // Calling tools without being able to list them is useless, so mcp:call implies mcp:read.
        // Destructive and code tools are write tools, so mcp:destructive and mcp:code imply mcp:write.
        if (in_array('mcp:destructive', $normalized, true) || in_array('mcp:code', $normalized, true)) {
            $normalized[] = 'mcp:write';
        }
        if (in_array('mcp:call', $normalized, true) || in_array('mcp:write', $normalized, true)) {
            $normalized[] = 'mcp:read';
        }
        if (in_array('mcp:write', $normalized, true)) {
            $normalized[] = 'mcp:call';
        }

        return array_values(array_unique($normalized));
    }

    public static function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }
}
