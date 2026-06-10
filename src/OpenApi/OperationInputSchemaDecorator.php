<?php
// file generated with AI assistance: Claude Code - 2026-05-12 15:15:00 UTC

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;
use Dmstr\OpenApiJsonSchema\Service\OperationInputSchemaResolver;

/**
 * Injects per-operation JSON-Schema input files as OpenAPI `requestBody`.
 *
 * Convention: operation `<name>` looks up `config/schemas/<name-kebab>-input.json`
 * via {@see OperationInputSchemaResolver}. Operations without a matching file
 * are left untouched, so standard CRUD endpoints keep their API-Platform
 * default request body.
 */
final class OperationInputSchemaDecorator implements OpenApiFactoryInterface
{
    /** @var list<string> */
    private const VERBS = ['Get', 'Post', 'Put', 'Patch', 'Delete'];

    public function __construct(
        private readonly OpenApiFactoryInterface $decorated,
        private readonly OperationInputSchemaResolver $resolver,
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
                $schema = $this->resolver->loadSchema($opName);
                if (null === $schema) {
                    continue;
                }

                $requestBody = new RequestBody(
                    description: (string) ($schema['description'] ?? ''),
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => $this->stripTopLevelMeta($schema),
                        ],
                    ]),
                    required: $this->resolver->isRequired($schema),
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
