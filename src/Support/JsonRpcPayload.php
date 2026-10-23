<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Support;

use Illuminate\Http\Request;

/**
 * The JSON-RPC body of a request, decoded once and shared by the middleware that needs it
 * (scope check, rate limit, permission gate).
 */
final class JsonRpcPayload
{
    private const ATTRIBUTE = 'emcp.jsonrpc.payload';

    /**
     * @return array<string, mixed>|null null when the body is empty or not a JSON object
     */
    public static function of(Request $request): ?array
    {
        $cached = $request->attributes->get(self::ATTRIBUTE);
        if (is_array($cached)) {
            return $cached;
        }
        if ($cached === false) {
            return null;
        }

        $rawBody = $request->getContent();
        $payload = is_string($rawBody) && trim($rawBody) !== '' ? json_decode($rawBody, true) : null;
        $payload = is_array($payload) ? $payload : null;

        $request->attributes->set(self::ATTRIBUTE, $payload ?? false);

        return $payload;
    }

    public static function method(Request $request): ?string
    {
        $method = trim((string)(self::of($request)['method'] ?? ''));

        return $method !== '' ? $method : null;
    }

    /**
     * Tool name of a `tools/call`, null for any other method.
     */
    public static function toolName(Request $request): ?string
    {
        $payload = self::of($request);
        if ($payload === null || trim((string)($payload['method'] ?? '')) !== 'tools/call') {
            return null;
        }

        $name = $payload['params']['name'] ?? null;
        $name = is_string($name) ? trim($name) : '';

        return $name !== '' ? $name : null;
    }

    /**
     * Arguments of a `tools/call` (empty for any other method).
     *
     * @return array<string, mixed>
     */
    public static function toolArguments(Request $request): array
    {
        if (self::toolName($request) === null) {
            return [];
        }

        return self::argumentsOf(self::of($request) ?? []);
    }

    /**
     * @param  array<string, mixed>  $jsonRpc
     * @return array<string, mixed>
     */
    public static function argumentsOf(array $jsonRpc): array
    {
        $arguments = $jsonRpc['params']['arguments'] ?? null;

        return is_array($arguments) ? $arguments : [];
    }
}
