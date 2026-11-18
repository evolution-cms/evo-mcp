<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Write;

use EvolutionCMS\eMCP\Support\ToolContract;

use EvolutionCMS\eMCP\Contracts\ToolResponses\ItemToolResponse;
use EvolutionCMS\Legacy\Permissions;
use EvolutionCMS\Models\ManagerLog;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Shared guards for tools that change the site.
 *
 * Every write goes through the same checks the corresponding manager action performs:
 * a manager identity, the specific `save_*`/`new_*` permission, document-group access for
 * resources, and element locks. Permission checks read the impersonated session, so a token
 * can never do more than its owner can in the browser.
 */
abstract class BaseWriteTool extends Tool
{
    protected function requireManager(): int
    {
        if (!function_exists('evo') || !evo()->isLoggedIn('mgr') || is_cli()) {
            throw ValidationException::withMessages(['auth' => 'A manager identity is required for write tools.']);
        }

        return (int)evo()->getLoginUserID('mgr');
    }

    protected function requirePermission(string $permission): void
    {
        $this->requireManager();

        if (!evo()->hasPermission($permission, 'mgr')) {
            throw ValidationException::withMessages([
                'permission' => "This action requires the [{$permission}] permission.",
            ]);
        }
    }

    /**
     * Document-group check for a resource (or, with $document = 0, for creating under $parent).
     */
    protected function requireDocumentAccess(int $document, ?int $parent = null): void
    {
        $role = (int)($_SESSION['mgrRole'] ?? 0);
        if ($role === 1 || (int)evo()->getConfig('use_udperms') !== 1) {
            return;
        }

        // Legacy\Permissions reads this global; the manager bootstrap sets it from the same setting.
        global $udperms_allowroot;
        $udperms_allowroot = (int)evo()->getConfig('udperms_allowroot');

        $udperms = new Permissions();
        $udperms->user = $this->requireManager();
        $udperms->role = $role;
        $udperms->document = $document > 0 ? $document : (int)$parent;

        if (!$udperms->checkPermissions()) {
            throw ValidationException::withMessages([
                'permission' => 'You do not have access to this document.',
            ]);
        }
    }

    /**
     * @param  int  $type  1=template, 2=tv, 3=chunk, 4=snippet, 5=plugin, 6=module, 7=resource
     */
    protected function requireUnlocked(int $type, int $id): void
    {
        $lock = evo()->elementIsLocked($type, $id, false);
        if (is_array($lock) && $lock !== []) {
            $who = trim((string)($lock['username'] ?? ''));
            throw ValidationException::withMessages([
                'lock' => 'Element is currently being edited' . ($who !== '' ? " by {$who}" : '') . '.',
            ]);
        }
    }

    protected function findDocument(int $id): SiteContent
    {
        /** @var SiteContent|null $document */
        $document = SiteContent::query()->withTrashed()->find($id);
        if ($document === null) {
            throw ValidationException::withMessages(['id' => "Document [{$id}] not found."]);
        }

        return $document;
    }

    /**
     * Same table the manager's own actions log to, so an admin sees API writes next to browser ones.
     */
    protected function logManagerAction(int $action, string $message, int|string $itemId, string $itemName): void
    {
        try {
            ManagerLog::query()->create([
                'timestamp' => time(),
                'internalKey' => (int)evo()->getLoginUserID('mgr'),
                'username' => (string)evo()->getLoginUserName('mgr'),
                'action' => $action,
                'itemid' => (string)$itemId,
                'itemname' => $itemName !== '' ? $itemName : '-',
                'message' => $message,
                'ip' => (string)(request()?->ip() ?? ''),
                'useragent' => substr((string)(request()?->userAgent() ?? 'mcp'), 0, 255),
            ]);
        } catch (\Throwable) {
            // Logging must not undo a successful write.
        }
    }

    /**
     * @param  array<string, mixed>|null  $item
     */
    protected function respondItem(?array $item): ResponseFactory
    {
        return Response::structured(
            (new ItemToolResponse(
                $item,
                ToolContract::VERSION
            ))->toArray()
        );
    }

    /**
     * Keeps only allow-listed keys of a caller-supplied field map.
     *
     * @param  array<string, mixed>  $fields
     * @param  array<int, string>  $allowed
     * @return array<string, mixed>
     */
    protected function pickFields(array $fields, array $allowed): array
    {
        $picked = [];
        foreach ($fields as $key => $value) {
            $key = trim((string)$key);
            if (!in_array($key, $allowed, true)) {
                throw ValidationException::withMessages([
                    'fields' => "Field [{$key}] is not writable. Allowed: " . implode(', ', $allowed) . '.',
                ]);
            }
            if (is_array($value) || is_object($value)) {
                throw ValidationException::withMessages(['fields' => "Field [{$key}] must be a scalar."]);
            }
            $picked[$key] = $value;
        }

        return $picked;
    }
}
