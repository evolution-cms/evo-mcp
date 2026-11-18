<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Elements;

use EvolutionCMS\eMCP\Support\ToolContract;

use EvolutionCMS\eMCP\Support\ElementTypes;
use EvolutionCMS\eMCP\Tools\Write\BaseWriteTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.elements.get')]
#[Description('Read one editable template, TV, chunk, snippet or plugin by ID or exact name, including source and lock information. Use evo.elements.list to browse elements or evo.content.get to read a document.')]
/** Read the fields and source of one element allowed by the manager role. */
class ElementsGetTool extends BaseWriteTool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'id' => ['nullable', 'integer', 'min:1'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $spec = ElementTypes::get((string)$validated['type']);
        $this->requirePermission($spec['permissions']['view']);

        $row = self::find($spec, isset($validated['id']) ? (int)$validated['id'] : null, $validated['name'] ?? null);
        if ($row === null) {
            return $this->respondItem(null);
        }

        $item = [];
        foreach ($spec['read'] as $field) {
            $item[$field] = $row->getAttribute($field);
        }

        $lock = evo()->elementIsLocked((int)$spec['lock_type'], (int)$row->getKey(), true);
        $item['locked_by'] = is_array($lock) ? (string)($lock['username'] ?? '') : null;

        return $this->respondItem($item);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    public static function find(array $spec, ?int $id, ?string $name): ?Model
    {
        /** @var class-string<Model> $model */
        $model = $spec['model'];

        if ($id !== null) {
            return $model::query()->find($id);
        }

        $name = trim((string)$name);
        if ($name === '') {
            throw ValidationException::withMessages(['id' => 'Pass id or name.']);
        }

        return $model::query()->where($spec['name_field'], $name)->first();
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(ElementTypes::names())->required()->description('Element type, which determines the required edit permission.'),
            'id' => $schema->integer()->min(1)->nullable()->description('Element ID. Takes precedence over name even if it does not match a record.'),
            'name' => $schema->string()->max(255)->nullable()->description('Exact element name; required when id is omitted.'),
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
        return ToolContract::item($schema);
    }
}
