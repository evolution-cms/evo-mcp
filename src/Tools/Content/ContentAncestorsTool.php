<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Content;

use EvolutionCMS\eMCP\Support\ToolContract;

use EvolutionCMS\Models\SiteContent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.content.ancestors')]
#[Description('Read a visible document’s ancestor path, farthest to nearest and excluding the anchor. A server limit keeps the closest ancestors and sets meta.truncated. Use evo.content.tree to walk down instead.')]
/** Read a bounded ancestor path suitable for breadcrumbs. */
class ContentAncestorsTool extends BaseContentTool
{
    /** Normalize the anchor ID, optional depth and requested TV names. */
    protected function validateStage(Request $request): array
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'depth' => ['nullable', 'integer', 'min:1'],
            'with_tvs' => ['nullable', 'array'],
            'with_tvs.*' => ['string'],
        ]);

        if ($request->get('limit') !== null || $request->get('offset') !== null) {
            throw ValidationException::withMessages(['limit' => 'Ancestors returns a bounded path; omit limit and offset.']);
        }

        return [
            'id' => (int)$validated['id'],
            'depth' => isset($validated['depth']) ? $this->resolveDepth((int)$validated['depth']) : null,
            'limit' => $this->resolveMaxLimit(),
            'tvs' => $this->normalizeWithTvs($validated['with_tvs'] ?? null),
        ];
    }

    /** Retrieve the nearest visible ancestors, fetching one extra to detect truncation. */
    protected function queryStage(array $validated): array
    {
        if ($this->findAnchor($validated['id']) === null) {
            return [];
        }

        $query = SiteContent::query()->select('site_content.*')
            ->ancestorsOf($validated['id'])
            ->where('site_content.deleted', 0)
            ->orderBy('site_content_closure.depth')
            ->orderBy('site_content.id', 'desc');

        if ($validated['depth'] !== null) {
            $query->where('site_content_closure.depth', '<=', $validated['depth']);
        }
        $this->applyManagerAccess($query);
        if ($validated['tvs']['entries'] !== []) {
            $query->withTVs($validated['tvs']['entries']);
        }

        return $query->limit($validated['limit'] + 1)->get()->all();
    }

    /** Keep the nearest ancestors within the limit and return them in root-to-parent order. */
    protected function mapStage(mixed $queryResult, array $validated): array
    {
        $limit = $validated['limit'];

        return [
            'items' => $this->projectItems(array_reverse(array_slice($queryResult, 0, $limit)), $validated['tvs']['names']),
            'truncated' => count($queryResult) > $limit,
        ];
    }

    /** Mark whether the server limit shortened the path requested by the caller. */
    protected function respondStage(array $mapped, array $validated): ResponseFactory
    {
        return $this->respondList($mapped['items'], $validated['limit'], 0, $mapped['truncated']);
    }

    /** Advertise only the anchor ID, optional depth and TV projection. */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->min(1)->required()->description('ID of the visible document whose ancestor path to read.'),
            'depth' => $schema->integer()->min(1)->nullable()
                ->description('Maximum distance from the document in levels. Omit for a path up to the server result limit.'),
            'with_tvs' => $this->tvSchema($schema),
        ];
    }

    /**
     * Advertise the structured envelope required by an MCP client.
     *
     * @return array<string,mixed>
     * @since 0.3.0
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return ToolContract::listing($schema, [
            'truncated' => $schema->boolean()->required()
                ->description('Whether the server result limit shortened the visible ancestor path.'),
        ]);
    }
}
