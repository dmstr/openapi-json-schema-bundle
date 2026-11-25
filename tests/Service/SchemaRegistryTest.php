<?php
// file generated with AI assistance: Claude Code - 2026-11-25 23:06:23 UTC

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\Tests\Service;

use Dmstr\OpenApiJsonSchema\Interface\SchemaProviderInterface;
use Dmstr\OpenApiJsonSchema\Service\SchemaRegistry;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class SchemaRegistryTest extends TestCase
{
    private const NO_PROVIDERS_WARNING = 'No schema providers registered, returning boolean schema false (rejects all input)';

    private ArrayAdapter $cache;

    /** Spy logger exposing a public $records list */
    private AbstractLogger $logger;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message];
            }
        };
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testWithoutProvidersReturnsBooleanSchemaFalse(): void
    {
        $registry = new SchemaRegistry($this->cache, $this->logger);

        self::assertFalse($registry->getUnifiedSchema());
        self::assertSame(1, $this->countWarnings(self::NO_PROVIDERS_WARNING));
    }

    public function testCachedFalseIsAHitAndNotRegenerated(): void
    {
        $registry = new SchemaRegistry($this->cache, $this->logger);

        self::assertFalse($registry->getUnifiedSchema());
        self::assertFalse($registry->getUnifiedSchema());

        // A second registry on the same pool must read the cached false as well
        $second = new SchemaRegistry($this->cache, $this->logger);
        self::assertFalse($second->getUnifiedSchema());

        self::assertSame(1, $this->countWarnings(self::NO_PROVIDERS_WARNING));
    }

    #[DataProvider('anyInputProvider')]
    public function testUnifiedSchemaWithoutProvidersRejectsAllInputInOpis(mixed $data): void
    {
        $registry = new SchemaRegistry($this->cache, $this->logger);
        $schema = json_decode(json_encode($registry->getUnifiedSchema(), JSON_THROW_ON_ERROR));

        $result = (new Validator())->validate($data, $schema);

        self::assertFalse($result->isValid());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function anyInputProvider(): iterable
    {
        yield 'object' => [(object) ['name' => 'thing', 'days' => 7]];
        yield 'empty object' => [new \stdClass()];
        yield 'scalar' => [42];
    }

    public function testWithProvidersAnyOfContainsAllProviderSchemas(): void
    {
        $first = ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]];
        $second = ['type' => 'object', 'properties' => ['b' => ['type' => 'integer']]];

        $registry = new SchemaRegistry($this->cache, $this->logger);
        $registry->register($this->provider('first', $this->schemaFile(json_encode($first, JSON_THROW_ON_ERROR))));
        $registry->register($this->provider('second', $this->schemaFile(json_encode($second, JSON_THROW_ON_ERROR))));

        $schema = $registry->getUnifiedSchema();

        self::assertIsArray($schema);
        self::assertSame([$first, $second], $schema['anyOf']);
        self::assertSame(0, $this->countWarnings(self::NO_PROVIDERS_WARNING));
    }

    public function testMissingSchemaFileThrows(): void
    {
        $registry = new SchemaRegistry($this->cache, $this->logger);
        $registry->register($this->provider('missing', sys_get_temp_dir() . '/does-not-exist-' . uniqid() . '.json'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Schema file not found for provider "missing"');

        $registry->getUnifiedSchema();
    }

    public function testInvalidJsonThrows(): void
    {
        $registry = new SchemaRegistry($this->cache, $this->logger);
        $registry->register($this->provider('broken', $this->schemaFile('{"type": "object",')));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON in schema file for provider "broken"');

        $registry->getUnifiedSchema();
    }

    private function countWarnings(string $message): int
    {
        return count(array_filter(
            $this->logger->records,
            static fn (array $record): bool => $record['level'] === 'warning' && $record['message'] === $message
        ));
    }

    private function schemaFile(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'schema-registry-test-');
        self::assertIsString($file);
        file_put_contents($file, $content);
        $this->tempFiles[] = $file;

        return $file;
    }

    private function provider(string $name, string $path): SchemaProviderInterface
    {
        return new class ($name, $path) implements SchemaProviderInterface {
            public function __construct(private readonly string $name, private readonly string $path)
            {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getSchemaPath(): string
            {
                return $this->path;
            }
        };
    }
}
