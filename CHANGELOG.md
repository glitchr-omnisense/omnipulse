# Changelog

## Unreleased (1.x)

### Changed
- `omnisense/omnireach` is now `omnisense/omnipulse` (2026-11-10: the family is renamed before
  omnireview and omnifund merge into it): the namespace `Omnipulse\` (`OmnireachException` is
  `OmnipulseException`), the configuration keys `omnipulse.factory_name`, `omnipulse.factory_title`
  and `omnipulse.required_options`, the harness's `OMNIPULSE_AUTOLOAD` and `OMNIPULSE_PLUGINS`, the
  providers `omnipulse/<provider>` at `glitchr-omnisense/omnipulse-<provider>`. `composer.json`
  replaces `omnisense/omnireach` (and `omniscient/omnireach`, as before), so that a project never
  installs both; the namespace changed, so code that used `Omnireach\` moves to `Omnipulse\`.


### Fixed
- No exception `JsonApi::request()` throws keeps its trace's arguments: an error answered (a refused key,
  nothing there, a server error, a body that is not JSON) kept the request's options - the query,
  headers and token with the key - in its trace (`zend.exception_ignore_args` off), as the
  `UnreachableException` did before its fix.
- An `UnreachableException`'s chain no longer holds the key. Its previous exception was the HTTP
  client's own, whose message quotes the whole URL (Bing's `apikey=`, a URL's password), and every
  trace kept the arguments of the calls under it, the request's query, headers and bearer token
  among them. The previous is now a copy of the client's exception (`Http\Redaction::copy()`: the
  same class and code, the key's parameters, the URL's user part and the request's secrets masked
  as `***`), and no trace of the chain keeps its arguments (`Redaction::withoutArguments()`).
- An `UnreachableException` no longer quotes the URL called: its message names the method and the
  path (`... could not be reached for GetQueryStats (GET /json/GetQueryStats): ...`) and the HTTP
  client's reason without the address it quotes. Symfony's transport messages end with the whole
  URL, so Bing's `apikey=` stood in the message.

### Added
- `Omnipulse\Http\Redaction`: a request's secrets (`secrets()`), a text without them (`text()`),
  a failure's copy to wrap in its place (`copy()`), a trace without arguments
  (`withoutArguments()`).
- `Omnipulse\Http\JsonApi::reason()`: an HTTP client's message without the addresses it quotes.
