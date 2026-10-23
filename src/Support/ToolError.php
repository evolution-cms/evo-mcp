<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Support;

use InvalidArgumentException;
use Laravel\Mcp\Response;

/**
 * Structured error result for contributed tools.
 *
 * Returns a tool result with `isError: true` whose text block is one JSON object:
 *
 *     {"ok": false, "error": "not_found", "message": "...", "status": 404[, "errors": {...}][, "candidates": [...]]}
 *
 * so an agent can branch on `error` instead of parsing prose. Codes are the shared set below or
 * an extension's own, prefixed with its namespace (`aimage.quota_exceeded`).
 *
 * eMCP's built-in tools keep their ValidationException messages for now (golden fixtures).
 */
final class ToolError
{
    /** @var array<int, string> */
    public const CODES = ['forbidden', 'not_found', 'ambiguous', 'invalid', 'refused', 'store_failure'];

    public const MAX_CANDIDATES = 10;

    /**
     * @param  array<string, mixed>  $extra  `errors` (field => messages), `candidates`
     *                                       (id + title only, at most 10 are kept), or other keys
     */
    public static function make(string $code, string $message, int $status = 422, array $extra = []): Response
    {
        return Response::error(self::encode(self::payload($code, $message, $status, $extra)));
    }

    /**
     * The JSON object make() puts in the text block.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function payload(string $code, string $message, int $status = 422, array $extra = []): array
    {
        $code = trim($code);
        if (!self::isValidCode($code)) {
            throw new InvalidArgumentException(
                "Unknown tool error code [{$code}]. Use one of " . implode(', ', self::CODES) . ' or a prefixed code like vendor.code.'
            );
        }

        $payload = ['ok' => false, 'error' => $code, 'message' => $message, 'status' => $status];

        if (isset($extra['errors']) && is_array($extra['errors']) && $extra['errors'] !== []) {
            $payload['errors'] = $extra['errors'];
        }

        if (isset($extra['candidates']) && is_array($extra['candidates'])) {
            $candidates = self::candidates($extra['candidates']);
            $payload['candidates'] = $candidates;
            if (count($extra['candidates']) > count($candidates)) {
                $payload['candidates_truncated'] = true;
            }
        }

        unset($extra['errors'], $extra['candidates']);
        foreach ($extra as $key => $value) {
            if (is_string($key) && !array_key_exists($key, $payload)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * `invalid` from a ValidationException, keeping its per-field messages.
     */
    public static function fromValidation(\Illuminate\Validation\ValidationException $exception): Response
    {
        $errors = $exception->errors();
        $first = '';
        foreach ($errors as $messages) {
            $first = (string)(is_array($messages) ? ($messages[0] ?? '') : $messages);
            break;
        }

        return self::make('invalid', $first !== '' ? $first : $exception->getMessage(), 422, ['errors' => $errors]);
    }

    public static function isValidCode(string $code): bool
    {
        if (in_array($code, self::CODES, true)) {
            return true;
        }

        return (bool)preg_match('/^[a-z0-9][a-z0-9_-]*(\.[a-z0-9][a-z0-9_-]*)+$/', $code);
    }

    /**
     * Keeps id and title of each candidate (a `name` counts as title), at most MAX_CANDIDATES,
     * so an error never carries more than the caller needs to pick one.
     *
     * @param  array<int|string, mixed>  $candidates
     * @return array<int, array{id: int|string, title: string}>
     */
    private static function candidates(array $candidates): array
    {
        $result = [];
        foreach ($candidates as $candidate) {
            if (count($result) >= self::MAX_CANDIDATES) {
                break;
            }

            $candidate = is_object($candidate) ? get_object_vars($candidate) : $candidate;
            if (!is_array($candidate) || !isset($candidate['id']) || !(is_int($candidate['id']) || is_string($candidate['id']))) {
                continue;
            }

            $result[] = [
                'id' => $candidate['id'],
                'title' => (string)($candidate['title'] ?? $candidate['name'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function encode(array $payload): string
    {
        return (string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
