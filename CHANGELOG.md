<!-- file generated with AI assistance: Claude Code - 2026-11-23 22:49:19 UTC -->

# Changelog

All notable changes to this bundle are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and the bundle uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.6.0] - 2026-11-25

### Changed
- `SchemaRegistry::getUnifiedSchema()` returns `array|bool` instead of `array`. Callers typed against `array` must handle `false`.
- Without registered schema providers, the unified schema is the boolean JSON schema `false`, which rejects all input (fail closed). It replaces the schema with an empty `anyOf`, which is invalid JSON Schema and made opis throw an `InvalidKeywordException` (#3).
- The upgrade does not invalidate the unified schema cache: without providers, a warm cache keeps serving the old schema with the empty `anyOf` until its TTL expires (`cache_ttl`, default 3600 s). Clear the cache after upgrading.
- `JsonFieldSchemaDecorator` resolves the `#[JsonSchema]` attribute lookup at most once per class and property per decorator instance, negative results included; the output is unchanged (dmstr/api-platform-utils-bundle#14).

## [0.5.0] - 2026-11-23

### Added
- `InputSchemaValidator` service: validates the input of an operation, a raw JSON request body (`validateRaw()`) or decoded input such as console options (`validate()`), against the JSON-Schema the `InputSchemaResolverInterface` chain resolves for it. Malformed JSON is a 400, a schema violation a 422 (`symfony/http-kernel` exceptions), an operation without a schema a `LogicException`. A REST processor and its CLI mirror thus enforce the request body the OpenAPI document publishes.
- `opis/json-schema` ^2.6 and `symfony/http-kernel` ^7.0 are required (`opis/json-schema` was a suggestion before).
