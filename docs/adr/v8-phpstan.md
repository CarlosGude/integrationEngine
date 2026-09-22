# PHPStan contracts without speculative send inference

- **Status:** Accepted
- **Date:** 2026-09-22

## Context

`IntegrationEngine::send()` receives a string and resolves it with
`ConfigPort::getAction()`. `AbstractAction::mapper()` returns `?string`; concrete
mappers may retain `ResponseInterface` as their `transform()` return type. A caller
using `SomeAction::getName()` has not proved that this engine's configuration maps
that name to that action. Executing application methods during analysis would also
make analysis unsafe and dependent on application state.

## Decision

**No-go for automatic send return inference.** Ship three optional rules: reciprocal
literal action/mapper declarations, explicit facade return types with object
collections, and `final readonly` response/event DTOs. Parse mapper declarations
with PHPStan's parser and reflection; never call application code.

A literal `GetMovieMapper::class` can be resolved by the mapper rule and
`transform(): MovieResponse` can be reflected. That still does not establish the
runtime string-to-action mapping. Computed mapper names and mappers exposing only
`ResponseInterface` cannot reliably produce a narrower response type. Keep the
engine's declared interface and expose concrete response types in facades.

## Alternatives considered

A dynamic return extension could optimistically join those literals, but would
report an unsound type when another action shares a configured name. Generics on
actions and mappers would document their relationship but cannot bind an arbitrary
string lookup to the matching generic instance. A typed action dispatch API would
change the engine contract and is outside this closed release scope.

## Consequences

Applications get actionable errors with stable identifiers and file/line locations.
The optional extension introduces no production dependency on PHPStan. Applications
using computed mapper relationships must simplify their declarations or configure
PHPStan's normal identifier-based policy locally. The bundle does not suppress
these errors or execute code to guess the answer.

## References

- [Engine dispatch](../../src/Core/IntegrationEngine.php)
- [Mapper rule and syntax checks](../../src/PHPStan/Rules/MapperActionRule.php)
- [Rule test fixtures](../../tests/PHPStan/Rules/MapperActionRuleTest.php)
- [Configuration and supported contracts](../phpstan.md)
- [PHPStan custom rules](https://phpstan.org/developing-extensions/rules)
- [PHPStan dynamic return extensions](https://phpstan.org/developing-extensions/dynamic-return-type-extensions)
