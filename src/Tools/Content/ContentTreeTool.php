<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Content;

use EvolutionCMS\Models\SiteContent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.content.tree')]
#[Description('List visible documents below a document or the site root, ordered by parent, menuindex and id. Use depth 1 for direct children and menuindex ranges. Use evo.content.ancestors to walk up or evo.content.siblings for documents under the same parent.')]
/**
 * Read a paginated subtree as a flat list with parent IDs.
 *
 * @since 0.3.0
 */
class ContentTreeTool extends BaseContentTool
{
    /**
     * Normalize paging and check the depth and menuindex range together.
     *
     * @return array<string,mixed>
     * @since 0.3.0
     */
    protected function validateStage(Request $request): array
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:0'],
            'depth' => ['nullable', 'integer', 'min:1'],
            'menuindex_from' => ['nullable', 'integer', 'min:0'],
            'menuindex_to' => ['nullable', 'integer', 'min:0'],
            'with_tvs' => ['nullable', 'array'],
            'with_tvs.*' => ['string'],
            'limit' => ['required', 'integer', 'min:1'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $id = (int)$validated['id'];
        $depth = (int)($validated['depth'] ?? 1);
        $maxDepth = $this->resolveMaxDepth() + ($id === 0 ? 1 : 0);
        if ($depth > $maxDepth) {
            throw ValidationException::withMessages(['depth' => "depth must be between 1 and {$maxDepth} for this anchor."]);
        }

        $from = isset($validated['menuindex_from']) ? (int)$validated['menuindex_from'] : null;
        $to = isset($validated['menuindex_to']) ? (int)$validated['menuindex_to'] : null;
        if ($depth !== 1 && ($from !== null || $to !== null)) {
            throw ValidationException::withMessages(['depth' => 'A menuindex range requires depth 1.']);
        }
        $this->validateMenuindexRange($from, $to);
        [$limit, $offset] = $this->resolveLimitOffset($validated);

        return compact('id', 'depth', 'from', 'to', 'limit', 'offset')
            + ['tvs' => $this->normalizeWithTvs($validated['with_tvs'] ?? null)];
    }

    /**
     * Apply the traversal and access filters before pagination.
     *
     * @since 0.3.0
     */
    protected function queryStage(array $validated): iterable
    {
        ['id' => $id, 'depth' => $depth, 'from' => $from, 'to' => $to, 'tvs' => $tvs] = $validated;

        if ($id !== 0 && $this->findAnchor($id) === null) {
            return [];
        }

        $query = SiteContent::query()->select('site_content.*');
        $table = 'site_content';
        if ($depth === 1) {
            $query->where('site_content.parent', $id);
        } elseif ($id === 0) {
            $query->getRootTree($depth);
            $table = 't2';
        } else {
            $query->descendantsOf($id)->where('site_content_closure.depth', '<=', $depth);
        }

        $query->where($table . '.deleted', 0);
        $this->applyManagerAccess($query, $table);
        $this->applyMenuindexRange($query, $table, $from, $to);
        if ($tvs['entries'] !== []) {
            $query->withTVs($tvs['entries'], ':', $table === 't2');
        }

        return $query->orderBy($table . '.parent')->orderBy($table . '.menuindex')->orderBy($table . '.id')
            ->limit($validated['limit'])->offset($validated['offset'])->get();
    }

    /**
     * Project only the public resource columns and requested TVs.
     *
     * @since 0.3.0
     */
    protected function mapStage(mixed $queryResult, array $validated): array
    {
        return ['items' => $this->projectItems($queryResult, $validated['tvs']['names'])];
    }

    /**
     * Return the page and the paging values applied to it.
     *
     * @since 0.3.0
     */
    protected function respondStage(array $mapped, array $validated): ResponseFactory
    {
        return $this->respondList($mapped['items'], $validated['limit'], $validated['offset']);
    }

    /**
     * Advertise the tree-specific options and common paging and TV arguments.
     *
     * @return array<string,JsonSchema>
     * @since 0.3.0
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->min(0)->required()->description('Document ID, or 0 for the virtual site root.'),
            'depth' => $schema->integer()->min(1)->nullable()->default(1)
                ->description('Levels below the anchor, up to the configured maximum. At 0, depth 1 returns top-level documents.'),
            'menuindex_from' => $schema->integer()->min(0)->nullable()->description('Inclusive lower menuindex bound, only at depth 1.'),
            'menuindex_to' => $schema->integer()->min(0)->nullable()->description('Inclusive upper menuindex bound, only at depth 1.'),
            'with_tvs' => $this->tvSchema($schema),
        ] + $this->pagingSchema($schema);
    }
}
