<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Console\Commands;

use Carbon\Carbon;
use EvolutionCMS\eMCP\Services\TokenService;
use EvolutionCMS\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

class eMcpTokenCreateCommand extends Command
{
    protected $signature = 'emcp:token:create
        {username : Manager user the token acts as}
        {--name=cli : Label shown in the token list}
        {--scopes=mcp:read,mcp:call : Comma-separated scopes (mcp:read, mcp:call, mcp:write, mcp:destructive, mcp:code, mcp:admin)}
        {--expires=90 : Days until expiry, or "never"}
        {--json : Print the result as JSON}';

    protected $description = 'Create a personal access token for the MCP API';

    public function handle(TokenService $tokens): int
    {
        $username = trim((string)$this->argument('username'));

        /** @var User|null $user */
        $user = User::query()->with('attributes')->where('username', $username)->first();
        if ($user === null) {
            $this->error("User [{$username}] not found.");

            return self::FAILURE;
        }

        $expires = strtolower(trim((string)$this->option('expires')));
        if ($expires === 'never') {
            $expiresAt = null;
        } elseif (ctype_digit($expires) && (int)$expires > 0) {
            $expiresAt = Carbon::now()->addDays((int)$expires);
        } else {
            $this->error('--expires must be a positive number of days or "never".');

            return self::FAILURE;
        }

        try {
            $issued = $tokens->issue(
                $user,
                (string)$this->option('name'),
                explode(',', (string)$this->option('scopes')),
                $expiresAt
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $token = $issued['token'];

        if ((bool)$this->option('json')) {
            $this->line(json_encode([
                'id' => $token->getKey(),
                'user' => $username,
                'name' => $token->name,
                'scopes' => $token->scopeList(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'token' => $issued['plaintext'],
            ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info("Token #{$token->getKey()} created for [{$username}] with scopes " . implode(', ', $token->scopeList()) . '.');
        $this->line('Copy it now; it is not stored and will not be shown again:');
        $this->newLine();
        $this->line('  ' . $issued['plaintext']);
        $this->newLine();

        return self::SUCCESS;
    }
}
