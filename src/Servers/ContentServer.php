<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Servers;

use EvolutionCMS\eMCP\Tools\Content\ContentChildrenTool;
use EvolutionCMS\eMCP\Tools\Content\ContentChildrenRangeTool;
use EvolutionCMS\eMCP\Tools\Content\ContentGetTool;
use EvolutionCMS\eMCP\Tools\Content\ContentRootTreeTool;
use EvolutionCMS\eMCP\Tools\Content\ContentSearchTool;
use EvolutionCMS\eMCP\Tools\Content\ContentDescendantsTool;
use EvolutionCMS\eMCP\Tools\Content\ContentAncestorsTool;
use EvolutionCMS\eMCP\Tools\Content\ContentNeighborsTool;
use EvolutionCMS\eMCP\Tools\Content\ContentNextSiblingsTool;
use EvolutionCMS\eMCP\Tools\Content\ContentPrevSiblingsTool;
use EvolutionCMS\eMCP\Tools\Content\ContentSiblingsTool;
use EvolutionCMS\eMCP\Tools\Content\ContentSiblingsRangeTool;
use EvolutionCMS\eMCP\Tools\Elements\ElementsGetTool;
use EvolutionCMS\eMCP\Tools\Elements\ElementsListTool;
use EvolutionCMS\eMCP\Tools\ModelCatalog\ModelGetTool;
use EvolutionCMS\eMCP\Tools\ModelCatalog\ModelListTool;
use EvolutionCMS\eMCP\Tools\Write\CacheClearTool;
use EvolutionCMS\eMCP\Tools\Write\ContentCreateTool;
use EvolutionCMS\eMCP\Tools\Write\ContentPublishTool;
use EvolutionCMS\eMCP\Tools\Write\ContentUpdateTool;
use EvolutionCMS\eMCP\Tools\Write\ElementsSaveTool;
use EvolutionCMS\eMCP\Services\SecurityPolicy;
use EvolutionCMS\eMCP\Services\ToolRegistry;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('eMCP Content Server')]
#[Version('1.0.0')]
#[Instructions('Evolution CMS content and elements. Every call runs as the token owner with their manager permissions; evo.write.* tools change the site and need the mcp:write scope.')]
class ContentServer extends Server
{
    protected array $tools = [
        ContentSearchTool::class,
        ContentGetTool::class,
        ContentRootTreeTool::class,
        ContentDescendantsTool::class,
        ContentAncestorsTool::class,
        ContentChildrenTool::class,
        ContentSiblingsTool::class,
        ContentNeighborsTool::class,
        ContentPrevSiblingsTool::class,
        ContentNextSiblingsTool::class,
        ContentChildrenRangeTool::class,
        ContentSiblingsRangeTool::class,
        ModelListTool::class,
        ModelGetTool::class,
        ElementsListTool::class,
        ElementsGetTool::class,
        // evo.write.* tools are listed but refused unless security.enable_write_tools is on
        // and the token carries the write scope; each one re-checks the user's manager permissions.
        ContentUpdateTool::class,
        ContentCreateTool::class,
        ContentPublishTool::class,
        ElementsSaveTool::class,
        CacheClearTool::class,
    ];

    /** Handle under which extras contribute tools (ToolProvider::server()). */
    public const HANDLE = 'content';

    public function __construct(Transport $transport)
    {
        // Tools other extras registered through ToolRegistry / mcp.servers[].extra_tools.
        foreach (app(ToolRegistry::class)->toolsFor(self::HANDLE) as $class) {
            if (!in_array($class, $this->tools, true)) {
                $this->tools[] = $class;
            }
        }

        // Leave out what this user may not call (deny_tools, disabled destructive tools, missing
        // RequiresPermission). This also covers the local transport, which has no HTTP policy layer.
        $policy = app(SecurityPolicy::class);
        $this->tools = array_values(array_filter(
            $this->tools,
            static fn(string $class): bool => !$policy->isToolHidden(self::HANDLE, (string)ToolRegistry::resolveName($class))
        ));

        parent::__construct($transport);
    }

    protected array $resources = [];

    protected array $prompts = [];
}
