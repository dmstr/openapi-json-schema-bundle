<?php
// file generated with AI assistance: Claude Code - 2026-11-25 23:07:13 UTC

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\Tests\OpenApi;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;
use Dmstr\OpenApiJsonSchema\OpenApi\JsonFieldSchemaDecorator;
use Dmstr\OpenApiJsonSchema\Service\SchemaRegistry;
use Dmstr\OpenApiJsonSchema\Tests\Fixtures\JsonFieldEntity;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Unit tests for {@see JsonFieldSchemaDecorator}.
 *
 * The #[JsonSchema] attribute lookup depends only on class and property, so the
 * decorator resolves it at most once per class and property and instance,
 * negative results included. The decorated output must not change by that.
 */
final class JsonFieldSchemaDecoratorTest extends TestCase
{
    private const string ENTITY = JsonFieldEntity::class;

    private string $schemaDir;

    protected function setUp(): void
    {
        $this->schemaDir = sys_get_temp_dir() . '/json-field-schema-decorator-' . bin2hex(random_bytes(6));
        mkdir($this->schemaDir, 0o777, true);

        file_put_contents($this->schemaDir . '/jsonfieldentity-metadata.json', json_encode([
            'type' => 'object',
            'description' => 'Metadata',
            'properties' => ['source' => ['type' => 'string']],
            'required' => ['source'],
        ]));
        file_put_contents($this->schemaDir . '/jsonfieldentity-config.json', json_encode([
            'description' => 'Config',
            'anyOf' => [['type' => 'string'], ['type' => 'integer']],
        ]));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->schemaDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->schemaDir);
    }

    /**
     * Base factory stub: returns a fresh schema per call with one definition
     * and root-level properties, as API Platform does.
     */
    private function baseFactory(): SchemaFactoryInterface
    {
        return new class implements SchemaFactoryInterface {
            public function buildSchema(
                string $className,
                string $format = 'json',
                string $type = Schema::TYPE_OUTPUT,
                ?Operation $operation = null,
                ?Schema $schema = null,
                ?array $serializerContext = null,
                bool $forceCollection = false
            ): Schema {
                $schema = new Schema();
                $schema['$ref'] = '#/definitions/JsonFieldEntity';
                $schema->setDefinitions(new \ArrayObject([
                    'JsonFieldEntity' => [
                        'type' => 'object',
                        'properties' => [
                            'metadataJson' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'metadata_json' => ['type' => 'array'],
                            'configJson' => ['type' => 'array'],
                            'config_json' => ['type' => 'array'],
                            'title' => ['type' => 'string'],
                            'ghost' => ['type' => 'string'],
                        ],
                    ],
                ]));
                $schema['properties'] = [
                    'metadataJson' => ['type' => 'array'],
                    'title' => ['type' => 'string'],
                    'ghost' => ['type' => 'string'],
                ];

                return $schema;
            }
        };
    }

    /**
     * Decorator whose reflection lookup counts its calls per "class::property".
     */
    private function countingDecorator(): JsonFieldSchemaDecorator
    {
        return new class(
            $this->baseFactory(),
            $this->createStub(SchemaRegistry::class),
            new NullLogger(),
            $this->schemaDir
        ) extends JsonFieldSchemaDecorator {
            /** @var array<string,int> */
            public array $calls = [];

            protected function readSchemaNameAttribute(string $className, string $propertyName): ?string
            {
                $key = $className . '::' . $propertyName;
                $this->calls[$key] = ($this->calls[$key] ?? 0) + 1;

                return parent::readSchemaNameAttribute($className, $propertyName);
            }
        };
    }

    /**
     * Converts the schema, including nested ArrayObjects, into plain arrays.
     *
     * @return array<mixed>
     */
    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof \ArrayObject) {
            $value = $value->getArrayCopy();
        }
        if (is_array($value)) {
            return array_map(self::normalize(...), $value);
        }

        return $value;
    }

    /** @return list<string> */
    private static function lookedUpPairs(): array
    {
        return array_map(
            static fn (string $property): string => self::ENTITY . '::' . $property,
            ['metadataJson', 'metadata_json', 'configJson', 'config_json', 'title', 'ghost']
        );
    }

    public function testAttributeIsResolvedOncePerClassAndPropertyAcrossBuilds(): void
    {
        $decorator = $this->countingDecorator();

        for ($i = 0; $i < 3; $i++) {
            $decorator->buildSchema(self::ENTITY);
        }

        $expected = array_fill_keys(self::lookedUpPairs(), 1);
        ksort($expected);
        $actual = $decorator->calls;
        ksort($actual);

        self::assertSame(
            $expected,
            $actual,
            'Each class::property pair (with attribute, without attribute, non-existent) must be reflected exactly once.'
        );
    }

    public function testNewInstanceStartsWithEmptyMemo(): void
    {
        $first = $this->countingDecorator();
        $first->buildSchema(self::ENTITY);
        $first->buildSchema(self::ENTITY);

        $second = $this->countingDecorator();
        self::assertSame([], $second->calls);

        $second->buildSchema(self::ENTITY);
        $second->buildSchema(self::ENTITY);

        $expected = array_fill_keys(self::lookedUpPairs(), 1);
        ksort($expected);
        $actual = $second->calls;
        ksort($actual);

        self::assertSame($expected, $actual, 'A new decorator instance must resolve every pair again, once.');
    }

    public function testDecoratedOutputForAllShapesIsStableAcrossBuilds(): void
    {
        $decorator = new JsonFieldSchemaDecorator(
            $this->baseFactory(),
            $this->createStub(SchemaRegistry::class),
            new NullLogger(),
            $this->schemaDir
        );

        $objectSchema = [
            'type' => 'object',
            'description' => 'Metadata',
            'properties' => ['source' => ['type' => 'string']],
            'required' => ['source'],
            'additionalProperties' => false,
        ];
        $anyOfSchema = [
            'description' => 'Config',
            'anyOf' => [['type' => 'string'], ['type' => 'integer']],
        ];

        $expected = [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            '$ref' => '#/definitions/JsonFieldEntity',
            'definitions' => [
                'JsonFieldEntity' => [
                    'type' => 'object',
                    'properties' => [
                        // object schema replaces the base definition
                        'metadataJson' => $objectSchema,
                        // anyOf-only schema carries no type/properties/required/additionalProperties
                        'configJson' => $anyOfSchema,
                        // without attribute and non-existent: untouched;
                        // metadata_json and config_json: removed as snake_case duplicates
                        'title' => ['type' => 'string'],
                        'ghost' => ['type' => 'string'],
                    ],
                ],
            ],
            'properties' => [
                'metadataJson' => $objectSchema,
                'title' => ['type' => 'string'],
                'ghost' => ['type' => 'string'],
            ],
        ];

        $first = self::normalize($decorator->buildSchema(self::ENTITY));
        self::assertSame($expected, $first);

        for ($i = 0; $i < 2; $i++) {
            self::assertSame($first, self::normalize($decorator->buildSchema(self::ENTITY)));
        }
    }
}
