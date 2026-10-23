<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Contracts;

/**
 * Lets another extra contribute MCP tools to an eMCP server.
 *
 * Register an implementation from your service provider's boot():
 *
 *     if (class_exists(\EvolutionCMS\eMCP\Services\ToolRegistry::class)) {
 *         app(\EvolutionCMS\eMCP\Services\ToolRegistry::class)->register(new MyToolProvider());
 *     }
 *
 * Tools are ordinary Laravel\Mcp\Server\Tool classes and must carry a #[Name] attribute:
 * the policy layer finds a tool by that name, and a tool it cannot name is rejected.
 * A tool that changes the site should implement WritesSite so eMCP applies its write gates
 * (enable_write_tools, mcp:write scope); one that deletes data, DestructiveSite. A tool limited
 * to holders of an Evo permission implements RequiresPermission.
 * Permission checks inside a tool see the impersonated manager user: evo()->hasPermission()
 * and friends answer for the token owner. See DOCS.md, "Extension tool contract".
 */
interface ToolProvider
{
    /**
     * Handle of the eMCP server the tools belong to (`content` for the default server).
     */
    public function server(): string;

    /**
     * @return array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    public function tools(): array;
}
