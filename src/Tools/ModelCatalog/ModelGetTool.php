<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\ModelCatalog;

use EvolutionCMS\eMCP\Support\ToolContract;

use EvolutionCMS\eMCP\Contracts\ToolArguments\ModelGetArgs;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.model.get')]
#[Description('Read one record by ID from an allowed Evolution CMS model. Requires permission to read that model and returns only its public fields. Use evo.model.list to filter or browse several records.')]
/** Read one projected record from a registered model. */
class ModelGetTool extends BaseModelTool
{
    /**
     * @return array<string, mixed>
     */
    protected function validateStage(Request $request): array
    {
        $validated = $request->validate([
            'model' => ['required', 'string'],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        return [
            'args' => new ModelGetArgs(
                (string)$validated['model'],
                (int)$validated['id']
            ),
        ];
    }

    protected function queryStage(array $validated): mixed
    {
        /** @var ModelGetArgs $args */
        $args = $validated['args'];

        $modelClass = $this->resolveModelClass($args->model);
        $publicFields = $this->resolvePublicFields($modelClass);

        return [
            'modelClass' => $modelClass,
            'publicFields' => $publicFields,
            'item' => $modelClass::query()
                ->select($publicFields)
                ->where('id', $args->id)
                ->first(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapStage(mixed $queryResult, array $validated): array
    {
        $modelClass = (string)($queryResult['modelClass'] ?? '');
        $publicFields = is_array($queryResult['publicFields'] ?? null) ? $queryResult['publicFields'] : [];

        /** @var Model|null $item */
        $item = $queryResult['item'] instanceof Model ? $queryResult['item'] : null;

        return [
            'modelClass' => $modelClass,
            'item' => $item ? $this->projectItem($item, $publicFields) : null,
        ];
    }

    protected function respondStage(array $mapped, array $validated): ResponseFactory
    {
        $item = $mapped['item'] ?? null;

        return $this->respondItem(
            (string)($mapped['modelClass'] ?? ''),
            is_array($item) ? $item : null
        );
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()->required()->description('Allowed model alias or full class name, case-insensitive. Unsupported models produce an error.'),
            'id' => $schema->integer()->min(1)->required()->description('Record ID. A missing record returns item null.'),
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
        return ToolContract::item($schema, extraMeta: [
            'model' => $schema->string()->required()->description('Canonical short name of the queried model.'),
        ]);
    }
}
