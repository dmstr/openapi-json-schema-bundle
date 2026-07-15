<?php
// file generated with AI assistance: Claude Code - 2026-05-12 15:15:00 UTC

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;
use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;

/**
 * Injects per-operation JSON-Schema input into the OpenAPI document.
 *
 * The schema is resolved through the {@see InputSchemaResolverInterface} chain
 * (static file resolver by default, plus any custom resolvers). Operations
 * without a matching schema are left untouched, so standard CRUD endpoints keep
 * their API-Platform default request body.
 *
 * How the schema binds depends on the HTTP verb:
 * - Body verbs (POST, PUT, PATCH): the schema becomes the `requestBody`.
 * - Query verbs (DELETE, GET): each top-level property becomes an `in: query`
 *   Parameter — DELETE/GET request bodies have no defined semantics (RFC 9110)
 *   and are dropped by many intermediaries, so their input schemas MUST be
 *   flat objects of scalar properties.
 *
 * The chain is queried build-time (no per-instance $context), so dynamic
 * resolvers should return a representative/base schema or null here and expose
 * their per-instance schema through their own runtime channel.
 */
final class OperationInputSchemaDecorator implements OpenApiFactoryInterface
{
    /** @var list<string> */
    private const BODY_VERBS = ['Post', 'Put', 'Patch'];

    /** @var list<string> */
    private const QUERY_VERBS = ['Get', 'Delete'];

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

            foreach ([...self::BODY_VERBS, ...self::QUERY_VERBS] as $verb) {
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

                $patched = \in_array($verb, self::QUERY_VERBS, true)
                    ? $this->withQueryParameters($op, $schema)
                    : $this->withRequestBody($op, $schema);

                $patchedItem = $patchedItem->{$wither}($patched);
                $changed = true;
            }

            if ($changed) {
                $openApi->getPaths()->addPath($path, $patchedItem);
            }
        }

        return $openApi;
    }

    /**
     * Body verbs: attach the whole schema as `application/json` requestBody.
     *
     * @param array<string,mixed> $schema
     */
    private function withRequestBody(Operation $op, array $schema): Operation
    {
        $requiredProps = $schema['required'] ?? null;

        return $op->withRequestBody(new RequestBody(
            description: (string) ($schema['description'] ?? ''),
            content: new \ArrayObject([
                'application/json' => [
                    'schema' => $this->stripTopLevelMeta($schema),
                ],
            ]),
            required: \is_array($requiredProps) && [] !== $requiredProps,
        ));
    }

    /**
     * Query verbs (DELETE/GET): map each top-level property to an `in: query`
     * Parameter. Existing parameters (path ids, declared QueryParameters) are
     * preserved; a schema property whose name is already taken is skipped.
     *
     * @param array<string,mixed> $schema
     */
    private function withQueryParameters(Operation $op, array $schema): Operation
    {
        $properties = $schema['properties'] ?? null;
        if (!\is_array($properties) || [] === $properties) {
            return $op;
        }

        /** @var list<Parameter> $parameters */
        $parameters = $op->getParameters() ?? [];
        $taken = [];
        foreach ($parameters as $existing) {
            $taken[$existing->getName()] = true;
        }

        $required = $schema['required'] ?? [];
        foreach ($properties as $name => $propertySchema) {
            if (isset($taken[$name]) || !\is_array($propertySchema)) {
                continue;
            }
            $parameters[] = new Parameter(
                name: (string) $name,
                in: 'query',
                description: (string) ($propertySchema['description'] ?? ''),
                required: \is_array($required) && \in_array($name, $required, true),
                schema: $propertySchema,
            );
        }

        return $op->withParameters($parameters);
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
