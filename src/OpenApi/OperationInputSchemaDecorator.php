<?php
// file generated with AI assistance: Claude Code - 2026-05-12 15:15:00 UTC

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;
use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;

/**
 * Injects per-operation JSON-Schema input as OpenAPI `requestBody`.
 *
 * The schema is resolved through the {@see InputSchemaResolverInterface} chain
 * (static file resolver by default, plus any custom resolvers). Operations
 * without a matching schema are left untouched, so standard CRUD endpoints keep
 * their API-Platform default request body.
 *
 * The chain is queried build-time (no per-instance $context), so dynamic
 * resolvers should return a representative/base schema or null here and expose
 * their per-instance schema through their own runtime channel.
 */
final class OperationInputSchemaDecorator implements OpenApiFactoryInterface
{
    /** @var list<string> */
    private const VERBS = ['Get', 'Post', 'Put', 'Patch', 'Delete'];

    public function __construct(
        private readonly OpenApiFactoryInterface $decorated,
        private readonly InputSchemaResolverInterface $resolver,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            $patchedItem = $pathItem;
            $changed = false;

            foreach (self::VERBS as $verb) {
                $getter = 'get'.$verb;
                $wither = 'with'.$verb;
                $op = $patchedItem->{$getter}();
                if (!$op instanceof Operation) {
                    continue;
                }
                $opName = (string) ($op->getOperationId() ?? '');
                $schema = $this->resolver->resolve($opName);
                if (null === $schema) {
                    continue;
                }

                $requiredProps = $schema['required'] ?? null;

                $requestBody = new RequestBody(
                    description: (string) ($schema['description'] ?? ''),
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => $this->stripTopLevelMeta($schema),
                        ],
                    ]),
                    required: \is_array($requiredProps) && [] !== $requiredProps,
                );

                $patchedItem = $patchedItem->{$wither}($op->withRequestBody($requestBody));
                $changed = true;
            }

            if ($changed) {
                $openApi->getPaths()->addPath($path, $patchedItem);
            }
        }

        return $openApi;
    }

    /**
     * Drop JSON-Schema meta-keys (`$schema`, `$id`) before exposing the schema
     * as part of OpenAPI — they're useful in the source file but redundant
     * (and sometimes confusing) inside the spec.
     *
     * @param array<string,mixed> $schema
     * @return array<string,mixed>
     */
    private function stripTopLevelMeta(array $schema): array
    {
        unset($schema['$schema'], $schema['$id']);

        return $schema;
    }
}
