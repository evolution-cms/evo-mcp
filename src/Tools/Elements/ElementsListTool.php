<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Elements;

use EvolutionCMS\eMCP\Support\ToolContract;

use EvolutionCMS\eMCP\Contracts\ToolResponses\ListToolResponse;
use EvolutionCMS\eMCP\Support\ElementTypes;
use EvolutionCMS\eMCP\Tools\Write\BaseWriteTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.elements.list')]
#[Description('List editable templates, TVs, chunks, snippets or plugins ordered by name, with lock information. Requires the edit permission for the type. Use evo.elements.get for one element and its source code.')]
/** Read a paginated, code-free list of elements of one type. */
class ElementsListTool extends BaseWriteTool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $spec = ElementTypes::get((string)$validated['type']);
        $this->requirePermission($spec['permissions']['view']);

        $limit = (int)($validated['limit'] ?? 50);
        $offset = (int)($validated['offset'] ?? 0);
        $nameField = $spec['name_field'];
        $codeField = $spec['code_field'];

        /** @var class-string<Model> $model */
        $model = $spec['model'];
        $query = $model::query()->orderBy($nameField);

        $search = trim((string)($validated['search'] ?? ''));
        if ($search !== '') {
            $query->where($nameField, 'like', '%' . addcslashes($search, '%_\\') . '%');
        }
        if (isset($validated['category'])) {
            $query->where('category', (int)$validated['category']);
        }

        $fields = array_values(array_diff($spec['read'], [$codeField]));
        $items = [];
        foreach ($query->limit($limit)->offset($offset)->get() as $row) {
            $item = [];
            foreach ($fields as $field) {
                $item[$field] = $row->getAttribute($field);
            }
            $item['locked_by'] = $this->lockedBy((int)$spec['lock_type'], (int)$row->getKey());
            $items[] = $item;
        }

        return Response::structured(
            (new ListToolResponse(
                $items,
                $limit,
                $offset,
                count($items),
                ToolContract::VERSION
            ))->toArray()
        );
    }

    private function lockedBy(int $type, int $id): ?string
    {
        $lock = evo()->elementIsLocked($type, $id, true);

        return is_array($lock) ? (string)($lock['username'] ?? '') : null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(ElementTypes::names())->required()->description('Element type to list.'),
            'search' => $schema->string()->max(100)->nullable()->description('Substring of the element name. Omit for all names.'),
            'category' => $schema->integer()->min(0)->nullable()->description('Category ID. Omit for any category.'),
            'limit' => $schema->integer()->min(1)->max(200)->nullable()->default(50)->description('Maximum matching elements to return.'),
            'offset' => $schema->integer()->min(0)->nullable()->default(0)->description('Matching elements to skip after sorting by name.'),
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
        return ToolContract::listing($schema);
    }
}
