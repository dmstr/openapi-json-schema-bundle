<?php
// file generated with AI assistance: Claude Code - 2026-05-26 22:34:09 UTC

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\OpenApi;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;

/**
 * Enriches API-Platform JSON-Schemas with Jedison-compatible grid layout hints.
 *
 * For every object schema produced by the decorated factory, the decorator:
 *
 * 1. Sets `x-format: "grid"` on the object (unless already present).
 * 2. Classifies every property into a logical group and assigns an
 *    `x-grid: { columnsMd: N }` width based on type/format/name heuristics.
 * 3. Inserts `newRow: true` whenever two consecutive properties belong to
 *    different logical groups, so the rendered form visually separates
 *    identifiers, main attributes, descriptive strings, relations, flags,
 *    numbers and timestamps into their own rows.
 *
 * Property → group → width matrix (first match wins):
 *
 * | Group        | Match                                              | columnsMd |
 * |--------------|----------------------------------------------------|-----------|
 * | identifier   | `format: uuid`                                     | 6         |
 * | main         | name in `name`, `title`, `slug`                    | 8         |
 * | descriptive  | `type: string` (no format, or unknown format)      | 6         |
 * | relation     | `format: iri-reference`                            | 6         |
 * | flag         | `type: boolean` (also nullable)                    | 3         |
 * | number       | `type: integer` or `number`                        | 3         |
 * | date         | `format: date` or `date-time` (non-timestamp name) | 4         |
 * | timestamp    | name in `createdAt`, `updatedAt`, `deletedAt`      | 4         |
 * | (unmatched)  | objects, arrays, no detectable type                | omitted   |
 *
 * Group transitions trigger an automatic `newRow: true` on the next
 * property's `x-grid`, so the natural property ordering (identifiers →
 * main → descriptive → relations → flags → numbers → timestamps) renders
 * as visually separated row groups.
 *
 * `createdAt` always carries `newRow: true` even within the timestamps
 * group, so the timestamps pair starts on a fresh row.
 *
 * Per-property override (developer-supplied wins):
 *
 *     #[ApiProperty(jsonSchemaContext: ['x-grid' => ['columnsMd' => 4]])]
 *
 * Existing `x-format` and `x-grid` values are never overwritten.
 */
final class JedisonGridSchemaDecorator implements SchemaFactoryInterface
{
    private const X_FORMAT_GRID = 'grid';

    private const GROUP_IDENTIFIER = 'identifier';
    private const GROUP_MAIN = 'main';
    private const GROUP_DESCRIPTIVE = 'descriptive';
    private const GROUP_RELATION = 'relation';
    private const GROUP_FLAG = 'flag';
    private const GROUP_NUMBER = 'number';
    private const GROUP_DATE = 'date';
    private const GROUP_TIMESTAMP = 'timestamp';

    private const NAME_PROPERTIES = ['name', 'title', 'slug'];
    private const DATE_FORMATS = ['date', 'date-time'];
    private const TIMESTAMP_PROPERTIES = ['createdAt', 'updatedAt', 'deletedAt'];

    public function __construct(
        private readonly SchemaFactoryInterface $decorated,
    ) {
    }

    public function buildSchema(
        string $className,
        string $format = 'json',
        string $type = Schema::TYPE_OUTPUT,
        ?Operation $operation = null,
        ?Schema $schema = null,
        ?array $serializerContext = null,
        bool $forceCollection = false,
    ): Schema {
        $schema = $this->decorated->buildSchema(
            $className,
            $format,
            $type,
            $operation,
            $schema,
            $serializerContext,
            $forceCollection,
        );

        foreach ($schema->getDefinitions() as $definition) {
            $this->enrichObjectSchema($definition);
        }

        // Root-level object schema (rare, but possible when no $ref is used).
        if (($schema['type'] ?? null) === 'object' && isset($schema['properties'])) {
            /** @var \ArrayAccess<string,mixed> $schema */
            $this->enrichObjectSchema($schema);
        }

        return $schema;
    }

    /**
     * JSON-LD variants wrap the entity schema inside an `allOf` array
     * (`[HydraItemBaseSchema, <actual-object-schema>]`). We recurse into
     * every allOf branch so grid annotations land on the inner properties
     * regardless of representation format.
     *
     * @param \ArrayAccess<string,mixed>|array<string,mixed> $definition
     */
    private function enrichAllOfBranches(mixed &$definition): void
    {
        if (!isset($definition['allOf']) || !is_iterable($definition['allOf'])) {
            return;
        }

        $branches = $definition['allOf'];
        foreach ($branches as $idx => $branch) {
            if (!is_array($branch) && !$branch instanceof \ArrayObject) {
                continue;
            }
            $this->enrichObjectSchema($branch);
            $branches[$idx] = $branch;
        }
        $definition['allOf'] = $branches;
    }

    /**
     * Apply grid annotations to a single object schema in place.
     *
     * Accepts both native arrays (root schema) and `\ArrayObject` instances
     * (`$schema->getDefinitions()` entries), because API Platform stores
     * property definitions as `\ArrayObject` for reference semantics.
     *
     * @param \ArrayAccess<string,mixed>|array<string,mixed> $definition
     */
    private function enrichObjectSchema(mixed &$definition): void
    {
        $this->enrichAllOfBranches($definition);

        $properties = $definition['properties'] ?? null;
        if (!is_iterable($properties)) {
            return;
        }

        if (!isset($definition['x-format'])) {
            $definition['x-format'] = self::X_FORMAT_GRID;
        }

        $previousGroup = null;
        foreach ($properties as $propertyName => $propertySchema) {
            // Property may be an \ArrayObject (sub-schema) or a plain array;
            // both expose ArrayAccess, so the heuristic works uniformly.
            if (!is_array($propertySchema) && !$propertySchema instanceof \ArrayAccess) {
                continue;
            }

            if (isset($propertySchema['x-grid'])) {
                // User-supplied annotation wins; treat as unknown group so
                // the next property still starts a new row if it differs.
                $previousGroup = null;
                continue;
            }

            $group = $this->classifyGroup((string) $propertyName, $propertySchema);
            if ($group === null) {
                // No matching heuristic — leave Jedison default (full width)
                // in place but still treat as a group break.
                $previousGroup = null;
                continue;
            }

            $grid = $this->gridForGroup($group, (string) $propertyName);

            // Insert a row break whenever we transition between groups.
            if ($previousGroup !== null && $previousGroup !== $group) {
                $grid['newRow'] = true;
            }

            $propertySchema['x-grid'] = $grid;
            $properties[$propertyName] = $propertySchema;
            $previousGroup = $group;
        }

        $definition['properties'] = $properties;
    }

    /**
     * Classify a property into its logical group. Order matters — earlier
     * checks win over later ones (e.g. timestamp name beats date format).
     *
     * @param \ArrayAccess<string,mixed>|array<string,mixed> $propertySchema
     */
    private function classifyGroup(string $propertyName, mixed $propertySchema): ?string
    {
        if (in_array($propertyName, self::TIMESTAMP_PROPERTIES, true)) {
            return self::GROUP_TIMESTAMP;
        }

        if (in_array($propertyName, self::NAME_PROPERTIES, true)) {
            return self::GROUP_MAIN;
        }

        $propertyFormat = $propertySchema['format'] ?? null;
        if (is_string($propertyFormat)) {
            if ($propertyFormat === 'uuid') {
                return self::GROUP_IDENTIFIER;
            }
            if ($propertyFormat === 'iri-reference') {
                return self::GROUP_RELATION;
            }
            if (in_array($propertyFormat, self::DATE_FORMATS, true)) {
                return self::GROUP_DATE;
            }
        }

        $propertyType = $propertySchema['type'] ?? null;
        if ($this->typeIncludes($propertyType, 'boolean')) {
            return self::GROUP_FLAG;
        }
        if ($this->typeIncludes($propertyType, 'integer') || $this->typeIncludes($propertyType, 'number')) {
            return self::GROUP_NUMBER;
        }
        if ($this->typeIncludes($propertyType, 'string')) {
            return self::GROUP_DESCRIPTIVE;
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private function gridForGroup(string $group, string $propertyName): array
    {
        return match ($group) {
            self::GROUP_MAIN => ['columnsMd' => 8],
            self::GROUP_IDENTIFIER => ['columnsMd' => 6],
            self::GROUP_DESCRIPTIVE => ['columnsMd' => 6],
            self::GROUP_RELATION => ['columnsMd' => 6],
            self::GROUP_FLAG => ['columnsMd' => 3],
            self::GROUP_NUMBER => ['columnsMd' => 3],
            self::GROUP_DATE => ['columnsMd' => 4],
            self::GROUP_TIMESTAMP => $propertyName === 'createdAt'
                ? ['columnsMd' => 4, 'newRow' => true]
                : ['columnsMd' => 4],
        };
    }

    /**
     * JSON-Schema `type` may be a scalar (`"boolean"`) or a nullable union
     * (`["boolean","null"]`). Both forms are treated as matching when the
     * target type appears.
     */
    private function typeIncludes(mixed $type, string $target): bool
    {
        if (is_string($type)) {
            return $type === $target;
        }

        if (is_array($type)) {
            return in_array($target, $type, true);
        }

        return false;
    }
}
