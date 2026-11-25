<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tests\Fixtures;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('fixture.catalog_revision')]
final class CatalogRevisionTool extends Tool
{
    public function shouldRegister(): bool
    {
        return $this->description() !== 'Hidden package tool';
    }

    public function description(): string
    {
        return trim((string)file_get_contents(EVO_STORAGE_PATH . '/emcp-test-catalog-revision.txt'));
    }

    public function handle(Request $request): Response
    {
        return Response::text($this->description());
    }
}
