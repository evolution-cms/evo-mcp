<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Content;

use EvolutionCMS\Models\SiteContent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.content.siblings')]
#[Description('List visible documents sharing the anchor’s parent, excluding the anchor. Select all, before, after, or the closest document in each direction. Use evo.content.tree for children or deeper descendants.')]
/** Navigate within the anchor’s sibling set by menuindex and document ID. */
class ContentSiblingsTool extends BaseContentTool
{
    private const DIRECTIONS = ['all', 'before', 'after', 'adjacent'];

    /** Require pagination for list modes and restrict menuindex bounds to all. */
    protected function validateStage(Request $request): array
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'direction' => ['sometimes', 'required', 'in:' . implode(',', self::DIRECTIONS)],
            'menuindex_from' => ['nullable', 'integer', 'min:0'],
            'menuindex_to' => ['nullable', 'integer', 'min:0'],
            'with_tvs' => ['nullable', 'array'],
            'with_tvs.*' => ['string'],
            'limit' => ['sometimes', 'required', 'integer', 'min:1'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $direction = $validated['direction'] ?? 'all';
        $from = isset($validated['menuindex_from']) ? (int)$validated['menuindex_from'] : null;
        $to = isset($validated['menuindex_to']) ? (int)$validated['menuindex_to'] : null;
        if ($direction !== 'all' && ($from !== null || $to !== null)) {
            throw ValidationException::withMessages(['direction' => 'Menuindex bounds require direction all.']);
        }
        $this->validateMenuindexRange($from, $to);

        if ($direction === 'adjacent') {
            if (isset($validated['limit']) || isset($validated['offset'])) {
                throw ValidationException::withMessages(['direction' => 'Adjacent results do not support limit or offset.']);
            }
            [$limit, $offset] = [2, 0];
        } else {
            if (!isset($validated['limit'])) {
                throw ValidationException::withMessages(['limit' => 'A limit is required for all, before and after.']);
            }
            [$limit, $offset] = $this->resolveLimitOffset($validated);
        }

        return compact('direction', 'from', 'to', 'limit', 'offset') + [
            'id' => (int)$validated['id'],
            'tvs' => $this->normalizeWithTvs($validated['with_tvs'] ?? null),
        ];
    }

    /** Scope both the anchor and the returned siblings to documents visible to the caller. */
    protected function queryStage(array $validated): iterable
    {
        $anchor = $this->findAnchor($validated['id']);
        if ($anchor === null) {
            return [];
        }

        $query = SiteContent::query()->select('site_content.*')
            ->where('site_content.parent', $anchor->parent)
            ->where('site_content.id', '!=', $anchor->id)
            ->where('site_content.deleted', 0);
        $this->applyManagerAccess($query);
        if ($validated['tvs']['entries'] !== []) {
            $query->withTVs($validated['tvs']['entries']);
        }

        $direction = $validated['direction'];
        if ($direction === 'adjacent') {
            $previous = $this->inDirection(clone $query, $anchor, 'before')->first();
            $next = $this->inDirection($query, $anchor, 'after')->first();

            return array_values(array_filter([$previous, $next]));
        }

        if ($direction === 'all') {
            $this->applyMenuindexRange($query, 'site_content', $validated['from'], $validated['to']);
            $query->orderBy('site_content.menuindex')->orderBy('site_content.id');
        } else {
            $this->inDirection($query, $anchor, $direction);
        }

        return $query->limit($validated['limit'])->offset($validated['offset'])->get();
    }

    /**
     * Seek away from the anchor in one direction, using ID to break menuindex ties.
     *
     * @since 0.3.0
     */
    private function inDirection(Builder $query, SiteContent $anchor, string $direction): Builder
    {
        $before = $direction === 'before';
        $operator = $before ? '<' : '>';
        $order = $before ? 'desc' : 'asc';

        return $query->where(function (Builder $query) use ($anchor, $operator): void {
            $query->where('site_content.menuindex', $operator, $anchor->menuindex)
                ->orWhere(function (Builder $tie) use ($anchor, $operator): void {
                    $tie->where('site_content.menuindex', $anchor->menuindex)
                        ->where('site_content.id', $operator, $anchor->id);
                });
        })->orderBy('site_content.menuindex', $order)->orderBy('site_content.id', $order);
    }

    /** Project the page of siblings or the nearest two visible records. */
    protected function mapStage(mixed $queryResult, array $validated): array
    {
        return ['items' => $this->projectItems($queryResult, $validated['tvs']['names'])];
    }

    /** Use the same structured list envelope in every direction. */
    protected function respondStage(array $mapped, array $validated): ResponseFactory
    {
        return $this->respondList($mapped['items'], $validated['limit'], $validated['offset']);
    }

    /** Describe the direction-specific constraints alongside the shared list arguments. */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->min(1)->required()->description('ID of the visible document whose siblings to read.'),
            'direction' => $schema->string()->enum(self::DIRECTIONS)->default('all')
                ->description('all uses ascending (menuindex, id); before and after start with the nearest match; adjacent returns at most one per side.'),
            'menuindex_from' => $schema->integer()->min(0)->nullable()->description('Inclusive lower menuindex bound, for all only.'),
            'menuindex_to' => $schema->integer()->min(0)->nullable()->description('Inclusive upper menuindex bound, for all only.'),
            'with_tvs' => $this->tvSchema($schema),
            'limit' => $schema->integer()->min(1)->description('Required for all, before and after. Omit for adjacent.'),
            'offset' => $schema->integer()->min(0)->nullable()->description('Matching records to skip. Defaults to 0 for paged directions. Omit for adjacent.'),
        ];
    }
}
