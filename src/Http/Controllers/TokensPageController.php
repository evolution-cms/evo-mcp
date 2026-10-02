<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Http\Controllers;

use Carbon\Carbon;
use EvolutionCMS\eMCP\Http\Controllers\Concerns\ManagerPage;
use EvolutionCMS\eMCP\Models\EmcpToken;
use EvolutionCMS\eMCP\Services\TokenService;
use EvolutionCMS\eMCP\Support\ManagerUrl;
use EvolutionCMS\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use InvalidArgumentException;

/**
 * "My MCP tokens" page: a manager user creates and revokes their own personal access tokens.
 *
 * Only the caller's own tokens are ever listed or touched; admins issue tokens for other
 * users from the CLI (emcp:token:create).
 */
class TokensPageController
{
    use ManagerPage;

    private const FLASH_KEY = 'emcp_tokens_flash';

    /** @var array<int, string> */
    private const EXPIRY_CHOICES = ['30', '90', '180', '365', 'never'];

    public function __construct(
        private readonly TokenService $tokens
    ) {
    }

    public function index(Request $request)
    {
        $userId = $this->currentUserId();
        if ($userId === null) {
            return $this->forbidden();
        }
        $this->applyManagerLocale();

        $tokens = EmcpToken::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();

        return view('eMCP::manager.tokens', [
            'tokens' => $tokens,
            'scopes' => TokenService::KNOWN_SCOPES,
            'expiryChoices' => self::EXPIRY_CHOICES,
            'createUrl' => ManagerUrl::to('emcp/tokens'),
            'revokeBaseUrl' => ManagerUrl::to('emcp/tokens'),
            'endpointUrl' => ManagerUrl::siteUrl(trim((string)config('cms.settings.eMCP.route.api_prefix', 'mcp'), '/') . '/content'),
            'authMode' => strtolower(trim((string)config('cms.settings.eMCP.auth.mode', 'pat'))),
            'plaintext' => $this->pullFlash('plaintext'),
            'message' => $this->pullFlash('message'),
            'error' => $this->pullFlash('error'),
            'darkTheme' => $this->isDarkTheme(),
            'settingsUrl' => evo()->hasPermission('settings', 'mgr') ? ManagerUrl::to('emcp/settings') : '',
        ]);
    }

    public function store(Request $request): Response
    {
        $userId = $this->currentUserId();

        /** @var User|null $user */
        $user = $userId === null ? null : User::query()->with('attributes')->find($userId);
        if ($user === null) {
            return $this->forbidden();
        }

        $name = trim((string)$request->input('name', ''));
        $scopes = (array)$request->input('scopes', []);
        $expires = trim((string)$request->input('expires', '90'));

        try {
            $expiresAt = $this->resolveExpiry($expires);
            $issued = $this->tokens->issue($user, $name, $scopes, $expiresAt);
        } catch (InvalidArgumentException $e) {
            return $this->back('error', $e->getMessage());
        }

        $this->logManagerAction('Created MCP token "' . $issued['token']->name . '"');
        $this->flash('plaintext', $issued['plaintext']);

        return $this->back('message', 'Token created. Copy it now: it will not be shown again.');
    }

    public function revoke(Request $request, int $id): Response
    {
        $userId = $this->currentUserId();
        if ($userId === null) {
            return $this->forbidden();
        }

        /** @var EmcpToken|null $token */
        $token = EmcpToken::query()->where('user_id', $userId)->find($id);
        if ($token === null) {
            return $this->back('error', 'Token not found.');
        }

        $this->tokens->revoke($token);
        $this->logManagerAction('Revoked MCP token "' . $token->name . '"');

        return $this->back('message', 'Token "' . $token->name . '" revoked.');
    }

    private function currentUserId(): ?int
    {
        if (!function_exists('evo') || !evo()->isLoggedIn('mgr')) {
            return null;
        }

        if (!evo()->hasPermission((string)config('cms.settings.eMCP.acl.permission', 'emcp'), 'mgr')) {
            return null;
        }

        return (int)evo()->getLoginUserID('mgr');
    }

    private function resolveExpiry(string $choice): ?Carbon
    {
        if (!in_array($choice, self::EXPIRY_CHOICES, true)) {
            throw new InvalidArgumentException('Invalid expiry choice.');
        }

        return $choice === 'never' ? null : Carbon::now()->addDays((int)$choice);
    }

    private function back(?string $flashKey = null, string $flashValue = ''): RedirectResponse
    {
        if ($flashKey !== null) {
            $this->flash($flashKey, $flashValue);
        }

        return redirect()->to(ManagerUrl::to('emcp/tokens'));
    }
}
