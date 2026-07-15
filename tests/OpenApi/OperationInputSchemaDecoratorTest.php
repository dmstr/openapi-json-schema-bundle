<?php
// file generated with AI assistance: Claude Code - 2026-07-15 17:00:00 UTC

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\Tests\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;
use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;
use Dmstr\OpenApiJsonSchema\OpenApi\OperationInputSchemaDecorator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see OperationInputSchemaDecorator}.
 *
 * Verb-dependent binding: body verbs (POST/PUT/PATCH) receive the resolved
 * schema as `requestBody`; query verbs (DELETE/GET) receive each top-level
 * property as an `in: query` Parameter (RFC 9110: DELETE/GET bodies have no
 * defined semantics), preserving pre-existing parameters.
 */
final class OperationInputSchemaDecoratorTest extends TestCase
{
    /** @param array<string,array<string,mixed>> $schemasByOperationId */
    private function decorate(PathItem $pathItem, string $path, array $schemasByOperationId): OpenApi
    {
        $paths = new Paths();
        $paths->addPath($path, $pathItem);
        $openApi = new OpenApi(new Info('test', '1.0'), [], $paths);

        $decorated = new class($openApi) implements OpenApiFactoryInterface {
            public function __construct(private readonly OpenApi $openApi)
            {
            }

            public function __invoke(array $context = []): OpenApi
            {
                return $this->openApi;
            }
        };

        $resolver = new class($schemasByOperationId) implements InputSchemaResolverInterface {
            /** @param array<string,array<string,mixed>> $schemas */
            public function __construct(private readonly array $schemas)
            {
            }

            public function supports(string $operationName, array $context = []): bool
            {
                return isset($this->schemas[$operationName]);
            }

            public function resolve(string $operationName, array $context = []): ?array
            {
                return $this->schemas[$operationName] ?? null;
            }
        };

        return (new OperationInputSchemaDecorator($decorated, $resolver))();
    }

    /** @return array<string,mixed> */
    private static function cascadeSchema(): array
    {
        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'type' => 'object',
            'properties' => [
                'cascade' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Also delete instances.',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function testDeleteSchemaBindsAsQueryParametersWithoutRequestBody(): void
    {
        $pathItem = (new PathItem())->withDelete(new Operation(
            operationId: 'thing_delete',
            parameters: [new Parameter('id', 'path', required: true)],
        ));

        $openApi = $this->decorate($pathItem, '/things/{id}', ['thing_delete' => self::cascadeSchema()]);
        $delete = $openApi->getPaths()->getPath('/things/{id}')->getDelete();

        self::assertNull($delete->getRequestBody(), 'DELETE must not carry a requestBody.');

        $parameters = $delete->getParameters();
        self::assertCount(2, $parameters, 'Pre-existing parameters must be preserved.');
        self::assertSame('id', $parameters[0]->getName());

        $cascade = $parameters[1];
        self::assertSame('cascade', $cascade->getName());
        self::assertSame('query', $cascade->getIn());
        self::assertFalse($cascade->getRequired());
        self::assertSame('boolean', $cascade->getSchema()['type']);
    }

    public function testRequiredSchemaPropertyYieldsRequiredQueryParameter(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => ['reason' => ['type' => 'string']],
            'required' => ['reason'],
        ];
        $pathItem = (new PathItem())->withDelete(new Operation(operationId: 'thing_delete'));

        $openApi = $this->decorate($pathItem, '/things/{id}', ['thing_delete' => $schema]);
        $parameters = $openApi->getPaths()->getPath('/things/{id}')->getDelete()->getParameters();

        self::assertCount(1, $parameters);
        self::assertTrue($parameters[0]->getRequired());
    }

    public function testColidingParameterNamesAreNotDuplicated(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'string'], 'cascade' => ['type' => 'boolean']],
        ];
        $pathItem = (new PathItem())->withDelete(new Operation(
            operationId: 'thing_delete',
            parameters: [new Parameter('id', 'path', required: true)],
        ));

        $openApi = $this->decorate($pathItem, '/things/{id}', ['thing_delete' => $schema]);
        $parameters = $openApi->getPaths()->getPath('/things/{id}')->getDelete()->getParameters();

        $names = array_map(static fn (Parameter $p): string => $p->getName(), [...$parameters]);
        self::assertSame(['id', 'cascade'], $names, 'A schema property shadowing an existing parameter is skipped.');
    }

    public function testPostSchemaStillBindsAsRequestBody(): void
    {
        $pathItem = (new PathItem())->withPost(new Operation(operationId: 'thing_action'));

        $openApi = $this->decorate($pathItem, '/things/{id}/action', ['thing_action' => self::cascadeSchema()]);
        $post = $openApi->getPaths()->getPath('/things/{id}/action')->getPost();

        $requestBody = $post->getRequestBody();
        self::assertNotNull($requestBody);
        $schema = $requestBody->getContent()['application/json']['schema'];
        self::assertArrayNotHasKey('$schema', $schema, 'Top-level JSON-Schema meta keys are stripped.');
        self::assertArrayHasKey('cascade', $schema['properties']);
        self::assertSame([], $post->getParameters() ?? [], 'Body verbs gain no query parameters.');
    }

    public function testOperationWithoutSchemaIsLeftUntouched(): void
    {
        $pathItem = (new PathItem())->withDelete(new Operation(operationId: 'thing_delete'));

        $openApi = $this->decorate($pathItem, '/things/{id}', []);
        $delete = $openApi->getPaths()->getPath('/things/{id}')->getDelete();

        self::assertNull($delete->getRequestBody());
        self::assertSame([], $delete->getParameters() ?? []);
    }
}
