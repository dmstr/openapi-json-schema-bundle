<!-- file generated with AI assistance: Claude Code - 2026-06-09 19:27:00 UTC -->

# dmstr/openapi-json-schema-bundle

Inject JSON Schemas into OpenAPI documentation for API Platform.

## Features (planned)

- `SchemaRegistry` — merges provider schemas into a discriminated `anyOf`
- `#[JsonSchema]` attribute — references entity-local JSON Schema files
- `OperationInputSchemaResolver` — entity-local + path-fallback schema lookup
- OpenAPI decorators: `JsonFieldSchemaDecorator`, `JedisonGridSchemaDecorator`,
  `OperationInputSchemaDecorator`
- `DumpSchemaCommand` — CLI utility

## License

MIT © diemeisterei GmbH
