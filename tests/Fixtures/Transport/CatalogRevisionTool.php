<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tests\Fixtures;

use EvolutionCMS\eMCP\Contracts\HasDefinitionRevision;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('fixture.catalog_revision')]
final class CatalogRevisionTool extends Tool implements HasDefinitionRevision
{
    public function shouldRegister(): bool
    {
        return $this->description() !== 'Hidden package tool';
    }

    public function description(): string
    {
        return trim((string)file_get_contents(EVO_STORAGE_PATH . '/emcp-test-catalog-revision.txt'));
    }

    public function definitionRevision(): string
    {
        return $this->runtimeSchema()['revision'];
    }

    public function schema(JsonSchema $schema): array
    {
        return ['value' => $this->runtimeSchema()['type'] === 'integer' ? $schema->integer() : $schema->string()];
    }

    public function handle(Request $request): Response
    {
        return Response::text($this->description());
    }

    /** @return array{type: string, revision: string} */
    private function runtimeSchema(): array
    {
        $path = EVO_STORAGE_PATH . '/emcp-test-catalog-schema.json';
        $schema = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;

        return ($schema ?? []) + ['type' => 'string', 'revision' => ''];
    }
}
