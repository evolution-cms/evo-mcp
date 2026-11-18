<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Content;

use EvolutionCMS\eMCP\Contracts\ToolArguments\ContentSearchArgs;
use EvolutionCMS\eMCP\Contracts\ToolArguments\PagingArgs;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.content.search')]
#[Description('Search visible documents using field filters, template, publication state, TV values or tags. Use evo.content.get for a known ID or evo.content.tree for a fixed tree relationship.')]
/** Search accessible documents using structured filters and deterministic paging. */
class ContentSearchTool extends BaseContentTool
{
    /**
     * @return array<string, mixed>
     */
    protected function validateStage(Request $request): array
    {
        $validated = $request->validate([
            'parent' => ['nullable', 'integer', 'min:0'],
            'published' => ['nullable', 'boolean'],
            'deleted' => ['nullable', 'boolean'],
            'template' => ['nullable', 'integer', 'min:0'],
            'hidemenu' => ['nullable', 'boolean'],
            'depth' => ['nullable', 'integer', 'min:1'],
            'with_tvs' => ['nullable', 'array'],
            'with_tvs.*' => ['string'],
            'tv_filters' => ['nullable', 'array'],
            'tv_filters.*.tv' => ['required', 'string'],
            'tv_filters.*.op' => ['required', 'string'],
            'tv_filters.*.value' => ['nullable'],
            'tv_filters.*.cast' => ['nullable', 'string'],
            'tv_filters.*.use_default' => ['nullable', 'boolean'],
            'tv_order' => ['nullable', 'array'],
            'tv_order.*.tv' => ['required', 'string'],
            'tv_order.*.dir' => ['nullable', 'string'],
            'tv_order.*.cast' => ['nullable', 'string'],
            'tv_order.*.use_default' => ['nullable', 'boolean'],
            'tags_data' => ['nullable', 'array'],
            'tags_data.tv_id' => ['required_with:tags_data', 'integer', 'min:1'],
            'tags_data.tags' => ['required_with:tags_data', 'array', 'min:1'],
            'tags_data.tags.*' => ['string'],
            'order_by' => ['nullable', 'string'],
            'order_dir' => ['nullable', 'string'],
            'order_by_date' => ['nullable', 'string'],
            'limit' => ['required', 'integer', 'min:1'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        [$limit, $offset] = $this->resolveLimitOffset($validated);
        $withTvs = $this->normalizeWithTvs($validated['with_tvs'] ?? null);

        return [
            'args' => new ContentSearchArgs(
                new PagingArgs($limit, $offset),
                isset($validated['parent']) ? (int)$validated['parent'] : null,
                $this->booleanOrNull($validated['published'] ?? null),
                $this->booleanOrNull($validated['deleted'] ?? null),
                isset($validated['template']) ? (int)$validated['template'] : null,
                $this->booleanOrNull($validated['hidemenu'] ?? null),
                isset($validated['depth']) ? $this->resolveDepth((int)$validated['depth']) : null,
                $withTvs['entries'],
                $withTvs['names'],
                is_array($validated['tv_filters'] ?? null) ? $validated['tv_filters'] : [],
                is_array($validated['tv_order'] ?? null) ? $validated['tv_order'] : [],
                is_array($validated['tags_data'] ?? null) ? $validated['tags_data'] : null,
                isset($validated['order_by']) ? (string)$validated['order_by'] : null,
                isset($validated['order_dir']) ? (string)$validated['order_dir'] : null,
                isset($validated['order_by_date']) ? (string)$validated['order_by_date'] : null,
            ),
        ];
    }

    protected function queryStage(array $validated): mixed
    {
        /** @var ContentSearchArgs $args */
        $args = $validated['args'];

        $tvFilterString = $this->buildTvFilterString($args->tvFilters);
        $tvOrderString = $this->buildTvOrderString($args->tvOrder);
        $tagsDataString = $this->buildTagsDataString($args->tagsData);

        $query = SiteContent::query()->select('site_content.*');

        if ($args->parent !== null) {
            $effectiveDepth = $args->depth ?? $this->resolveMaxDepth();
            $query->descendantsOf($args->parent);
            $query->where('site_content_closure.depth', '<=', $effectiveDepth);
        }

        if ($args->published !== null) {
            $query->where('published', $args->published ? 1 : 0);
        }

        $query->where('deleted', ($args->deleted ?? false) ? 1 : 0);
        $this->applyManagerAccess($query);

        if ($args->template !== null) {
            $query->where('template', $args->template);
        }

        if ($args->hidemenu !== null) {
            $query->where('hidemenu', $args->hidemenu ? 1 : 0);
        }

        if ($args->withTvEntries !== []) {
            $query->withTVs($args->withTvEntries);
        }

        if ($tvFilterString !== '') {
            $query->tvFilter($tvFilterString);
        }

        if ($tvOrderString !== '') {
            $query->tvOrderBy($tvOrderString);
        }

        if ($tagsDataString !== '') {
            $query->tagsData($tagsDataString);
        }

        $this->applySort(
            $query,
            $args->orderBy,
            $args->orderDir,
            $args->orderByDate
        );

        return $query
            ->limit($args->paging->limit)
            ->offset($args->paging->offset)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapStage(mixed $queryResult, array $validated): array
    {
        /** @var ContentSearchArgs $args */
        $args = $validated['args'];

        return [
            'items' => $this->projectItems($queryResult, $args->withTvNames),
            'limit' => $args->paging->limit,
            'offset' => $args->paging->offset,
        ];
    }

    protected function respondStage(array $mapped, array $validated): ResponseFactory
    {
        $items = is_array($mapped['items'] ?? null) ? $mapped['items'] : [];

        return $this->respondList(
            $items,
            (int)($mapped['limit'] ?? 0),
            (int)($mapped['offset'] ?? 0)
        );
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        $direction = ['asc', 'desc'];

        return [
            'parent' => $schema->integer()->min(0)->nullable()
                ->description('Ancestor ID to scope the search to its descendants. Omit to search the site.'),
            'published' => $schema->boolean()->nullable()->description('Filter by publication state, subject to caller visibility.'),
            'deleted' => $schema->boolean()->nullable()->default(false)->description('Select trashed documents when true. Defaults to live documents.'),
            'template' => $schema->integer()->min(0)->nullable()->description('Filter by template ID.'),
            'hidemenu' => $schema->boolean()->nullable()->description('Filter by the Hide from menu flag.'),
            'depth' => $schema->integer()->min(1)->nullable()
                ->description('Maximum levels below parent. Used only with parent and defaults to the server depth limit.'),
            'with_tvs' => $this->tvSchema($schema),
            'tv_filters' => $schema->array()->nullable()->items($schema->object([
                'tv' => $schema->string()->required()->description('TV name.'),
                'op' => $schema->string()->enum(self::ALLOWED_TV_OPERATORS)->required()->description('Comparison operator.'),
                'value' => $schema->union(['string', 'number', 'boolean', 'array'])->nullable()
                    ->description('Operand; omit for null or !null. in and not_in accept a list.'),
                'cast' => $schema->string()->nullable()->description('Optional numeric comparison cast: SIGNED, UNSIGNED or DECIMAL(p,s).'),
                'use_default' => $schema->boolean()->nullable()->default(false)->description('Use the TV default when no document value is stored.'),
            ]))->description('TV conditions. Example: [{"tv":"price","op":">","value":"100","cast":"UNSIGNED"}].'),
            'tv_order' => $schema->array()->nullable()->items($schema->object([
                'tv' => $schema->string()->required()->description('TV name.'),
                'dir' => $schema->string()->enum([...$direction, null])->nullable()->default('asc')->description('TV sort direction.'),
                'cast' => $schema->string()->enum([...self::ALLOWED_TV_CASTS, null])->nullable()->description('Optional numeric TV sort cast.'),
                'use_default' => $schema->boolean()->nullable()->default(false)->description('Use the TV default when no document value is stored.'),
            ]))->description('Ordered TV sort keys. Example: [{"tv":"price","dir":"asc","cast":"UNSIGNED"}].'),
            'tags_data' => $schema->object([
                'tv_id' => $schema->integer()->min(1)->required()->description('ID of the tag TV.'),
                'tags' => $schema->array()->items($schema->string())->required()->description('Nonempty list of tag values.'),
            ])->nullable()->description('Tag match. Example: {"tv_id":17,"tags":["sale","featured"]}.'),
            'order_by' => $schema->string()->enum([...self::ALLOWED_SORT_FIELDS, null])->nullable()
                ->description('Document sort field after TV sorting. Defaults to id ascending.'),
            'order_dir' => $schema->string()->enum([...$direction, null])->nullable()->description('Direction for order_by; defaults to asc.'),
            'order_by_date' => $schema->string()->enum([...$direction, null])->nullable()
                ->description('Sort by publication date or creation date when no publication date is set. Takes precedence over order_by.'),
        ] + $this->pagingSchema($schema);
    }
}
