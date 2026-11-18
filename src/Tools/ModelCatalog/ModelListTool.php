<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\ModelCatalog;

use EvolutionCMS\eMCP\Support\ToolContract;

use EvolutionCMS\eMCP\Contracts\ToolArguments\ModelListArgs;
use EvolutionCMS\eMCP\Contracts\ToolArguments\PagingArgs;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.model.list')]
#[Description('List records from one allowed Evolution CMS model, optionally filtered and sorted by its public fields. Requires permission to read that model. Use evo.model.get for a known record ID or evo.elements.list for browsing editable CMS elements.')]
/** Read a page of projected records from one registered model. */
class ModelListTool extends BaseModelTool
{
    /**
     * @return array<string, mixed>
     */
    protected function validateStage(Request $request): array
    {
        $validated = $request->validate([
            'model' => ['required', 'string'],
            'filters' => ['nullable', 'array'],
            'filters.where' => ['nullable', 'array'],
            'filters.where.*.field' => ['required_with:filters.where', 'string'],
            'filters.where.*.op' => ['required_with:filters.where', 'string'],
            'filters.where.*.value' => ['nullable'],
            'order_by' => ['nullable', 'string'],
            'order_dir' => ['nullable', 'string'],
            'limit' => ['required', 'integer', 'min:1'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        [$limit, $offset] = $this->resolveLimitOffset($validated);

        return [
            'args' => new ModelListArgs(
                (string)$validated['model'],
                new PagingArgs($limit, $offset),
                is_array($validated['filters'] ?? null) ? $validated['filters'] : null,
                isset($validated['order_by']) ? (string)$validated['order_by'] : null,
                isset($validated['order_dir']) ? (string)$validated['order_dir'] : null,
            ),
        ];
    }

    protected function queryStage(array $validated): mixed
    {
        /** @var ModelListArgs $args */
        $args = $validated['args'];

        $modelClass = $this->resolveModelClass($args->model);
        $publicFields = $this->resolvePublicFields($modelClass);

        $query = $modelClass::query()->select($publicFields);

        $this->applyFilters($query, $modelClass, $args->filters);
        $this->applySort($query, $modelClass, $args->orderBy, $args->orderDir);

        return [
            'modelClass' => $modelClass,
            'publicFields' => $publicFields,
            'rows' => $query
                ->limit($args->paging->limit)
                ->offset($args->paging->offset)
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapStage(mixed $queryResult, array $validated): array
    {
        /** @var ModelListArgs $args */
        $args = $validated['args'];

        $modelClass = (string)($queryResult['modelClass'] ?? '');
        $publicFields = is_array($queryResult['publicFields'] ?? null) ? $queryResult['publicFields'] : [];
        $rows = $queryResult['rows'] ?? [];

        return [
            'modelClass' => $modelClass,
            'items' => $this->projectItems($rows, $publicFields),
            'limit' => $args->paging->limit,
            'offset' => $args->paging->offset,
        ];
    }

    protected function respondStage(array $mapped, array $validated): ResponseFactory
    {
        $items = is_array($mapped['items'] ?? null) ? $mapped['items'] : [];

        return $this->respondList(
            (string)($mapped['modelClass'] ?? ''),
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
        return [
            'model' => $schema->string()->required()
                ->description('Allowed model alias or full class name, case-insensitive. Unsupported models or missing read permissions produce an error.'),
            'filters' => $schema->object([
                'where' => $schema->array()->items($schema->object([
                    'field' => $schema->string()->required()->description('Allowlisted field of the selected model.'),
                    'op' => $schema->string()->enum(self::ALLOWED_OPERATORS)->required()->description('Comparison operator.'),
                    'value' => $schema->union(['string', 'number', 'boolean', 'array'])->nullable()
                        ->description('Scalar operand, or a list for in and not_in. Omit for null and !null.'),
                ]))->nullable()->description('Conditions combined with AND.'),
            ])->nullable()->description('Structured field filters. Example: {"where":[{"field":"name","op":"like","value":"banner"}]}.'),
            'limit' => $schema->integer()->min(1)->required()->description('Maximum records to return, up to the configured server limit.'),
            'offset' => $schema->integer()->min(0)->nullable()->default(0)->description('Number of matching records to skip after sorting.'),
            'order_by' => $schema->string()->nullable()->description('Allowlisted sort field. Defaults to id, or the first public field if id is unavailable.'),
            'order_dir' => $schema->string()->enum(['asc', 'desc', null])->nullable()->description('Direction for order_by. Defaults to asc.'),
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
            'model' => $schema->string()->required()->description('Canonical short name of the queried model.'),
        ]);
    }
}
