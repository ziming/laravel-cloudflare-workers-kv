# Changelog

All notable changes to `laravel-cloudflare-workers-kv` will be documented in this file.

## Unreleased

### Added

- Per-store credentials: a cache store may override `account_id`, `namespace_id`, `api_token`,
  `base_url`, `timeout`, `connect_timeout`, `serializer`, `allowed_classes`, `prefix`, and
  `graceful`, so one app can target multiple KV namespaces.
- Configurable HTTP `timeout` (5s) and `connect_timeout` (2s) so a hung Cloudflare connection
  never blocks the request.
- Optional `graceful` config: read failures (KV outage) degrade to a cache miss instead of
  throwing.
- Artisan commands: `cloudflare-kv:verify`, `cloudflare-kv:keys`, and `cloudflare-kv:get`.
- Direct client: `forever()`, `getWithMetadata()`, and `expiresAt()` helpers.
- `CloudflareKvClientFactory` for building/validating a client from a config array.

### Changed

- `increment()`/`decrement()` now preserve a key's **exact** absolute expiry by reading the
  value and its native expiration together and re-writing with that same expiration (one fewer
  round-trip). The custom expiry metadata previously written on every `put()` is gone.
- `CloudflareKvClient::put()` accepts an absolute `$expiration` alongside `$ttl`;
  `getMetadata()` is replaced by `getWithMetadata()`; `keys()` accepts an optional page limit.

### Fixed

- Corrupt or foreign cached values are treated as a cache miss instead of throwing on read.
- `increment()` no longer extends a near-expiry key's lifetime on every call (it previously
  re-applied a clamped 60-second TTL).
- `many()` now requests `type: text` explicitly. Note that Cloudflare's bulk-get endpoint
  cannot carry non-UTF-8 bytes, so binary values are dropped from `many()` results — use the
  `json` serializer or single `get()` for binary payloads (documented in the README).
