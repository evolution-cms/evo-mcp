<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Services;

use EvolutionCMS\eMCP\Contracts\DestructiveSite;
use EvolutionCMS\eMCP\Contracts\ExecutesCode;
use EvolutionCMS\eMCP\Contracts\RequiresPermission;
use EvolutionCMS\eMCP\Contracts\ToolProvider;
use EvolutionCMS\eMCP\Contracts\WritesSite;
use EvolutionCMS\eMCP\Support\BackendCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Primitive;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;

/**
 * Tools contributed by other extras, keyed by server handle.
 *
 * Two ways in: a ToolProvider registered at runtime, or class names listed under
 * `mcp.servers[].extra_tools` in config. The registry also answers policy questions (write,
 * destructive, required permission) for names it knows, so the policy layer needs no naming
 * convention from extras.
 *
 * A tool is accepted only when the policy layer can see it under the same name the MCP server
 * will call it by. Anything else (not a Tool, a name that cannot be read without running the
 * tool, the reserved `evo.*` namespace, a name taken by another tool) is rejected: it throws
 * in debug mode and is logged and left out otherwise (SPEC 6.4, 6.5), never served ungated.
 */
final class ToolRegistry
{
    /** destructiveHint of eMCP's own write tools: true where a call overwrites existing data. */
    private const BUILTIN_DESTRUCTIVE = [
        'evo.write.content.update' => true,
        'evo.write.elements.save' => true,
        'evo.write.content.create' => false,
        'evo.write.content.publish' => false,
        'evo.write.cache.clear' => false,
    ];

    /** @var array<string, array<int, class-string>> handle => classes from providers, in order */
    private array $tools = [];

    /**
     * @var array{names: array<string, class-string>, tools: array<string, array<int, class-string>>}|null
     */
    private ?array $index = null;

    /** @var array<class-string, string> class => reason */
    private array $rejected = [];

    public function register(ToolProvider $provider): void
    {
        $handle = trim($provider->server());
        if ($handle === '') {
            return;
        }

        foreach ($provider->tools() as $class) {
            $this->add($handle, $class);
        }
    }

    /**
     * @param  class-string  $class
     */
    public function add(string $handle, string $class): void
    {
        // A provider may list tools of an optional dependency that is not installed.
        if (!class_exists($class)) {
            return;
        }

        $this->tools[$handle] ??= [];
        if (!in_array($class, $this->tools[$handle], true)) {
            $this->tools[$handle][] = $class;
            $this->index = null;
            // Surface a bad registration where it happens (throws in debug mode).
            $this->index();
        }
    }

    /**
     * Accepted tools of a server: provider tools first, then `extra_tools` from config.
     *
     * @return array<int, class-string>
     */
    public function toolsFor(string $handle): array
    {
        return $this->index()['tools'][$handle] ?? [];
    }

    /**
     * True when the tool is known to change the site: eMCP's own evo.write.* tools, or a
     * contributed tool implementing WritesSite.
     */
    public function isWriteTool(string $toolName): bool
    {
        $toolName = trim($toolName);
        if (str_starts_with($toolName, 'evo.write.')) {
            return true;
        }

        $class = $this->classFor($toolName);

        return $class !== null && is_subclass_of($class, WritesSite::class);
    }

    public function isDestructiveTool(string $toolName): bool
    {
        $class = $this->classFor(trim($toolName));

        return $class !== null && is_subclass_of($class, DestructiveSite::class);
    }

    /**
     * A contributed tool marked ExecutesCode (every call stores backend code).
     */
    public function isCodeTool(string $toolName): bool
    {
        $class = $this->classFor(trim($toolName));

        return $class !== null && is_subclass_of($class, ExecutesCode::class);
    }

    /**
     * Whether this call stores backend code: an ExecutesCode tool, or a built-in write tool whose
     * arguments carry PHP (snippet, plugin) or a code binding (@EVAL, @SELECT, @INCLUDE).
     *
     * @param  array<string, mixed>  $arguments
     */
    public function writesCode(string $toolName, array $arguments = []): bool
    {
        return $this->isCodeTool($toolName) || BackendCode::inArguments($toolName, $arguments);
    }

    /**
     * Whether a destructive tool's effect can be undone (soft delete). Irreversible unless the
     * tool says otherwise through a static reversible() method.
     */
    public function isReversible(string $toolName): bool
    {
        $class = $this->classFor(trim($toolName));
        if ($class === null || !method_exists($class, 'reversible')) {
            return false;
        }

        try {
            return $class::reversible() === true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Permissions a contributed tool requires (any one is enough), or null when it declares none.
     * An empty list means the declaration is unusable and nobody may call the tool.
     *
     * @return array<int, string>|null
     */
    public function permissionFor(string $toolName): ?array
    {
        $class = $this->classFor(trim($toolName));
        if ($class === null || !is_subclass_of($class, RequiresPermission::class)) {
            return null;
        }

        try {
            $declared = $class::permission();
        } catch (\Throwable) {
            return [];
        }

        $permissions = [];
        foreach ((array)$declared as $permission) {
            if (is_string($permission) && trim($permission) !== '') {
                $permissions[] = trim($permission);
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * MCP tool annotations derived from the markers. Only what the markers prove: a contributed
     * tool without WritesSite is not claimed to be read-only, and a WritesSite tool is not claimed
     * to be harmless (MCP reads a missing destructiveHint as true; a tool that only adds can say
     * so itself with #[IsDestructive(false)]).
     *
     * @return array<string, bool>
     */
    public function annotationsFor(string $toolName): array
    {
        $toolName = trim($toolName);

        // Built-in write tools: update and elements.save overwrite without a revision to go back to.
        if (array_key_exists($toolName, self::BUILTIN_DESTRUCTIVE)) {
            return ['readOnlyHint' => false, 'destructiveHint' => self::BUILTIN_DESTRUCTIVE[$toolName]];
        }

        if ($this->isDestructiveTool($toolName) || $this->isCodeTool($toolName)) {
            return ['readOnlyHint' => false, 'destructiveHint' => true];
        }

        if ($this->isWriteTool($toolName)) {
            return ['readOnlyHint' => false];
        }

        if (str_starts_with($toolName, 'evo.')) {
            return ['readOnlyHint' => true];
        }

        return [];
    }

    /**
     * @return class-string|null
     */
    public function classFor(string $toolName): ?string
    {
        return $this->index()['names'][$toolName] ?? null;
    }

    /**
     * Classes that were offered but not accepted, with the reason.
     *
     * @return array<class-string, string>
     */
    public function rejected(): array
    {
        $this->index();

        return $this->rejected;
    }

    /**
     * The name Laravel MCP will expose the tool under (Primitive::name()), read without
     * instantiating it: #[Name], else the `$name` property default, else the kebab-cased class
     * basename. Null when the class overrides name(), since only running it would tell.
     *
     * @param  class-string  $class
     */
    public static function resolveName(string $class): ?string
    {
        $reflection = new ReflectionClass($class);

        $attributes = $reflection->getAttributes(Name::class);
        if ($attributes !== []) {
            $name = $attributes[0]->newInstance()->value;

            return is_string($name) && trim($name) !== '' ? $name : null;
        }

        if ($reflection->hasMethod('name') && $reflection->getMethod('name')->getDeclaringClass()->getName() !== Primitive::class) {
            return null;
        }

        $default = $reflection->getDefaultProperties()['name'] ?? '';
        if (is_string($default) && $default !== '') {
            return $default;
        }

        return Str::kebab($reflection->getShortName());
    }

    /**
     * @return array{names: array<string, class-string>, tools: array<string, array<int, class-string>>}
     */
    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $names = [];
        $tools = [];
        $configured = $this->configuredTools();
        $handles = array_unique(array_merge(array_keys($this->tools), array_keys($configured)));

        foreach ($handles as $handle) {
            $candidates = array_unique(array_merge($this->tools[$handle] ?? [], $configured[$handle] ?? []));
            foreach ($candidates as $class) {
                $name = $this->acceptableName($class);
                if ($name === null) {
                    continue;
                }

                if (isset($names[$name]) && $names[$name] !== $class) {
                    $this->reject($class, "tool name [{$name}] is already used by {$names[$name]}");
                    continue;
                }

                $names[$name] = $class;
                $tools[$handle][] = $class;
            }
        }

        return $this->index = ['names' => $names, 'tools' => $tools];
    }

    /**
     * @param  class-string  $class
     */
    private function acceptableName(string $class): ?string
    {
        if (!is_subclass_of($class, Tool::class)) {
            $this->reject($class, 'not a ' . Tool::class);

            return null;
        }

        $name = self::resolveName($class);
        if ($name === null) {
            $this->reject($class, 'its name cannot be determined without running it; add a #[Name] attribute');

            return null;
        }

        if (str_starts_with($name, 'evo.')) {
            $this->reject($class, "tool name [{$name}] is in the evo.* namespace reserved for eMCP");

            return null;
        }

        return $name;
    }

    /**
     * @return array<string, array<int, class-string>>
     */
    private function configuredTools(): array
    {
        $configured = [];
        foreach ((array)config('mcp.servers', []) as $server) {
            if (!is_array($server)) {
                continue;
            }
            $handle = trim((string)($server['handle'] ?? ''));
            if ($handle === '') {
                continue;
            }
            foreach ((array)($server['extra_tools'] ?? []) as $class) {
                if (is_string($class) && class_exists($class)) {
                    $configured[$handle][] = $class;
                }
            }
        }

        return $configured;
    }

    /**
     * @param  class-string  $class
     */
    private function reject(string $class, string $reason): void
    {
        $message = "eMCP rejected tool {$class}: {$reason}.";

        if ((bool)config('app.debug', false)) {
            throw new InvalidArgumentException($message);
        }

        if (isset($this->rejected[$class])) {
            return;
        }
        $this->rejected[$class] = $reason;

        try {
            $channel = trim((string)config('cms.settings.eMCP.logging.channel', 'emcp')) ?: 'emcp';
            Log::channel($channel)->warning($message);
        } catch (\Throwable) {
            // A logging failure must not take the site down.
        }
    }
}
