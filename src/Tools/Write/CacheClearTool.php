<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Write;

use EvolutionCMS\eMCP\Support\ToolContract;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.write.cache.clear')]
#[Description('Clear the full site cache, as Refresh site does in the manager. Requires the Evo empty_cache permission. Takes no arguments and returns cleared on success.')]
/** Refresh the complete site cache for an authorized manager. */
class CacheClearTool extends BaseWriteTool
{
    public function handle(Request $request): ResponseFactory
    {
        $this->requirePermission('empty_cache');

        evo()->clearCache('full');
        $this->logManagerAction(26, 'Refreshed site via MCP', '-', '-');

        return $this->respondItem(['cleared' => true]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Advertise the structured envelope required by an MCP client.
     *
     * @return array<string,mixed>
     * @since 0.3.0
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return ToolContract::item($schema, ['cleared' => $schema->boolean()->required()], nullable: false);
    }
}
