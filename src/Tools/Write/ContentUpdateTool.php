<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Write;

use EvolutionCMS\eMCP\Support\ToolContract;

use EvolutionCMS\DocumentManager\Facades\DocumentManager;
use EvolutionCMS\Exceptions\ServiceActionException;
use EvolutionCMS\Exceptions\ServiceValidationException;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.write.content.update')]
#[Description('Update document fields and TVs together in one transaction. Requires the Evo save_document permission. Changing published, pub_date or unpub_date also requires publish_document. For a standalone publish or unpublish action use evo.write.content.publish.')]
/** Save a partial document update through the transactional DocumentManager API. */
class ContentUpdateTool extends BaseWriteTool
{
    /** @var array<int, string> */
    public const WRITABLE_FIELDS = [
        'pagetitle', 'longtitle', 'description', 'alias', 'introtext', 'content', 'menutitle',
        'template', 'parent', 'published', 'pub_date', 'unpub_date', 'hidemenu', 'menuindex',
        'isfolder', 'searchable', 'cacheable', 'richtext', 'type', 'contentType', 'content_dispo',
        'link_attributes', 'alias_visible', 'hide_from_tree',
    ];

    /** Check the manager ACL and report the supplied fields and TVs that changed on commit. */
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'fields' => ['nullable', 'array'],
            'fields.published' => ['sometimes', 'required', 'boolean'],
            'fields.pub_date' => ['sometimes', 'required', 'integer', 'min:0'],
            'fields.unpub_date' => ['sometimes', 'required', 'integer', 'min:0'],
            'tvs' => ['nullable', 'array'],
        ]);

        $id = (int)$validated['id'];
        $fields = $this->pickFields((array)$request->get('fields', []), self::WRITABLE_FIELDS);
        $tvs = (array)($validated['tvs'] ?? []);

        if ($fields === [] && $tvs === []) {
            throw ValidationException::withMessages(['fields' => 'Nothing to update: pass fields and/or tvs.']);
        }

        $this->requirePermission('save_document');
        try {
            $result = DocumentManager::editPatch($id, $fields, $this->tvValues($tvs), function (SiteContent $document) use ($id, $fields): void {
                $this->requireDocumentAccess($id);
                $this->requireUnlocked(7, $id);
                if (isset($fields['parent']) && (int)$fields['parent'] !== (int)$document->parent) {
                    $this->requireDocumentAccess(0, (int)$fields['parent']);
                }
            });
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => "Document [{$id}] not found."]);
        } catch (ServiceValidationException $e) {
            throw ValidationException::withMessages($this->flatten($e->getValidationErrors()));
        } catch (ServiceActionException $e) {
            throw ValidationException::withMessages(['document' => $e->getMessage()]);
        }

        $fresh = $result['document'];
        $connection = $fresh->getConnection();
        $log = fn() => $this->logManagerAction(5, 'Saved resource via MCP', $id, (string)$fresh->pagetitle);
        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit($log);
        } else {
            $log();
        }

        return $this->respondItem([
            'id' => $id,
            'pagetitle' => $fresh->pagetitle,
            'alias' => $fresh->alias,
            'published' => (bool)$fresh->published,
            'editedon' => $fresh->editedon,
            'updated_fields' => $result['updated_fields'],
            'updated_tvs' => $result['updated_tvs'],
        ]);
    }

    /**
     * Normalize TV values without merging their names into the document columns.
     *
     * @param  array<string, mixed>  $tvs
     * @return array<string, mixed>
     */
    private function tvValues(array $tvs): array
    {
        $values = [];
        foreach ($tvs as $name => $value) {
            $name = trim((string)$name);
            if (!preg_match('~^[A-Za-z0-9_\-]+$~', $name)) {
                throw ValidationException::withMessages(['tvs' => "Invalid TV name [{$name}]."]);
            }
            if (in_array($name, self::WRITABLE_FIELDS, true) || $name === 'id') {
                throw ValidationException::withMessages(['tvs' => "TV name [{$name}] collides with a document field."]);
            }
            $parts = is_array($value) ? $value : [$value];
            foreach ($parts as $part) {
                if (!is_scalar($part) && $part !== null) {
                    throw ValidationException::withMessages(['tvs' => "TV [{$name}] expects scalar values."]);
                }
            }
            $values[$name] = is_array($value) ? implode('||', array_map('strval', $parts)) : (string)$value;
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $errors
     * @return array<string, string>
     */
    private function flatten(array $errors): array
    {
        $flat = [];
        foreach ($errors as $field => $messages) {
            $flat[(string)$field] = implode(' ', array_map('strval', (array)$messages));
        }

        return $flat;
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->min(1)->required()->description('ID of the existing document to edit.'),
            'fields' => $schema->object([
                'published' => $schema->union(['boolean', 'integer'])->enum([true, false, 0, 1])->description('Publication state. Changing it requires publish_document.'),
                'pub_date' => $schema->integer()->min(0)->description('Scheduled publication timestamp. Use 0 to clear.'),
                'unpub_date' => $schema->integer()->min(0)->description('Scheduled unpublication timestamp. Use 0 to clear.'),
            ])->nullable()->description('Document columns to change. Provide fields, TVs or both. Allowed: ' . implode(', ', self::WRITABLE_FIELDS)),
            'tvs' => $schema->object()->nullable()->description('TV values keyed by names assigned to the final template. A list value is stored using the || separator.'),
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
        return ToolContract::item($schema, [
            'id' => $schema->integer()->required(),
            'pagetitle' => $schema->string()->required(),
            'alias' => $schema->string()->nullable()->required(),
            'published' => $schema->boolean()->required(),
            'editedon' => $schema->integer()->required(),
            'updated_fields' => $schema->array()->items($schema->string())->required()
                ->description('Requested document fields whose stored values changed.'),
            'updated_tvs' => $schema->array()->items($schema->string())->required()
                ->description('Requested TVs whose stored values were added or changed.'),
        ], nullable: false);
    }
}
