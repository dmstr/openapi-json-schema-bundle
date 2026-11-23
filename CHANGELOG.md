<!-- file generated with AI assistance: Claude Code - 2026-11-23 22:49:19 UTC -->

# Changelog

All notable changes to this bundle are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and the bundle uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `InputSchemaValidator` service: validates the input of an operation, a raw JSON request body (`validateRaw()`) or decoded input such as console options (`validate()`), against the JSON-Schema the `InputSchemaResolverInterface` chain resolves for it. Malformed JSON is a 400, a schema violation a 422 (`symfony/http-kernel` exceptions), an operation without a schema a `LogicException`. A REST processor and its CLI mirror thus enforce the request body the OpenAPI document publishes.
- `opis/json-schema` ^2.6 and `symfony/http-kernel` ^7.0 are required (`opis/json-schema` was a suggestion before).
