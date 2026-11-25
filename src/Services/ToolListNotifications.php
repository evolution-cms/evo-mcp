<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Services;

use Illuminate\Cache\FileStore;
use Illuminate\Http\Request;
use Laravel\Mcp\Server;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ToolListNotifications
{
    private const TTL_SECONDS = 86400;
    // Past the TTL, so clock differences with shared storage cannot remove a live record.
    private const CLEANUP_AGE_SECONDS = self::TTL_SECONDS + 3600;
    private const CLEANUP_LIMIT = 500;
    private const NOTIFICATION = '{"jsonrpc":"2.0","method":"notifications/tools/list_changed"}';

    public function __construct(
        private readonly FileStore $store,
        private readonly ToolRegistry $registry
    ) {
    }

    public static function handles(Request $request, string $method): bool
    {
        return in_array($method, ['initialize', 'tools/list', 'tools/call'], true)
            && in_array($request->attributes->get('emcp.context'), ['api', 'mgr'], true)
            && $request->route() !== null
            && (bool)config('cms.settings.eMCP.tool_catalog.notify_changes', true);
    }

    public function register(Request $request, string $server, Response $response, array $result): bool
    {
        $key = $this->sessionKey($request, $server, $response->headers->get('MCP-Session-Id'));
        $version = $result['protocolVersion'] ?? '';

        $registered = $key !== null
            && $response->isSuccessful()
            && is_string($version)
            && $version >= '2025-03-26'
            && isset($result['capabilities']['tools'])
            && $this->store->put($key, [], self::TTL_SECONDS);

        if ($registered) {
            ToolCatalogCleanup::run($this->store->getDirectory(), self::CLEANUP_AGE_SECONDS, self::CLEANUP_LIMIT);
        }

        return $registered;
    }

    public function apply(
        Request $request,
        string $server,
        string $method,
        Response $response,
        Server $mcpServer,
        string $files
    ): Response {
        $session = $this->sessionKey($request, $server, $request->headers->get('MCP-Session-Id'));
        if ($session === null || !$response->isSuccessful()) {
            return $response;
        }

        $payload = $request->json()->all();
        $body = $response instanceof StreamedResponse ? null : json_decode((string)$response->getContent(), true);
        $id = $payload['id'] ?? null;
        if ((!is_string($id) && !is_int($id)) || ($body !== null && ($body['id'] ?? null) !== $id)) {
            return $response;
        }

        $listed = $method === 'tools/list'
            && is_array($body['result']['tools'] ?? null)
            && !isset($payload['params']['cursor']);
        if (!$listed && ($method !== 'tools/call' || !$this->acceptsEvents($request))) {
            return $response;
        }
        if (!$listed && $body === null && !$response instanceof StreamedResponse) {
            return $response;
        }

        $state = $this->store->get($session);
        if (!is_array($state) || (!$listed && !isset($state['catalog']))) {
            return $response;
        }

        $tools = $mcpServer->createContext()->tools();
        $revision = ToolsRevision::candidates($files, $tools);
        if (!$listed && $state['revision'] === $revision) {
            return $response;
        }
        $catalog = $listed && ($state['revision'] ?? null) === $revision
            ? ($state['notified'] ?? $state['catalog'])
            : ToolsRevision::catalog($tools, $this->registry);

        $notify = $this->store->lock($session . ':update', 5)->get(function () use (
            $session,
            $state,
            $revision,
            $catalog,
            $listed
        ): bool {
            if ($this->store->get($session) !== $state) {
                return false;
            }

            if ($listed || $state['catalog'] === $catalog) {
                $this->store->put($session, ['revision' => $revision, 'catalog' => $catalog], self::TTL_SECONDS);

                return false;
            }

            $notify = ($state['notified'] ?? null) !== $catalog;
            $state['revision'] = $revision;
            $state['notified'] = $catalog;

            return $this->store->put($session, $state, self::TTL_SECONDS) && $notify;
        });

        return $notify ? $this->withNotification($response) : $response;
    }

    private function sessionKey(Request $request, string $server, ?string $session): ?string
    {
        if ($session === null || preg_match('/\A[\x21-\x7e]{1,128}\z/', $session) !== 1) {
            return null;
        }

        $parts = [
            $session,
            $server,
            strtolower($request->getHttpHost()),
            $request->attributes->get('emcp.context'),
            $request->attributes->get('emcp.actor_user_id'),
            $request->attributes->get('emcp.auth.token_id'),
        ];

        return 'emcp:tool-catalog:' . hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
    }

    private function acceptsEvents(Request $request): bool
    {
        $accept = AcceptHeader::fromString(implode(', ', $request->headers->all('Accept')));

        return ($accept->all()['text/event-stream'] ?? null)?->getQuality() > 0;
    }

    private function withNotification(Response $response): Response
    {
        $event = 'data: ' . self::NOTIFICATION . "\n\n";
        if ($response instanceof StreamedResponse) {
            $stream = new StreamedResponse(static function () use ($response, $event): void {
                echo $event;
                $response->sendContent();
            }, $response->getStatusCode(), $response->headers->all());
            $response = $stream;
        } else {
            $body = preg_replace('/\r\n|\r|\n/', "\ndata: ", trim((string)$response->getContent()));
            $response->setContent($event . 'data: ' . $body . "\n\n");
        }

        $response->headers->remove('Content-Length');
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
