# PHPStan rules

Install PHPStan 2.2 or newer as a development dependency. The bundle's optional
extension runs during static analysis; production applications do not need PHPStan.
With `phpstan/extension-installer`, Composer discovers the configuration automatically.
Without the installer, add this to your application's PHPStan configuration:

```neon
includes:
    - vendor/carlosgude/integration-engine/extension.neon
```

The rules are opt-in through that configuration (or installation of the extension
installer). They are not enabled automatically by the Symfony bundle.

| Identifier | Contract |
| --- | --- |
| `integrationEngine.mapperAction` | A concrete mapper's `getAction()` consists of a return of `SomeAction::class`, where that class extends `AbstractAction`. |
| `integrationEngine.mapperReciprocity` | That action's `mapper()` consists of a return of this mapper's class constant. |
| `integrationEngine.facadeReturnType` | Public methods on `IntegrationName` implementations declare return types. `mixed` and collections without object value types are rejected. Constructors and destructors are exempt. |
| `integrationEngine.responseModifiers` | Concrete named classes implementing `ResponseInterface` or `WebhookEventInterface` are `final readonly`. |

A facade returning `MovieResponse` or an array annotated `@return list<MovieDto>`
is accepted. A bare `array`, `array<string, mixed>`, or an `array|string` union is
rejected. `iterable<MovieDto>` and nullable typed object collections are accepted.
Private and protected helper methods are outside this facade rule.

Mapper relationships are inspected from syntax and PHPStan's static reflection;
application methods are never invoked. Computed mapper/action class names and
conditional returns are deliberately rejected: use a direct class constant.
The rule also checks the declaring class when an action inherits `mapper()`.

These rules check response modifiers, not the transitive serializability of every
property or the absence of side effects inside a mapper.

## Return type inference

The extension deliberately does not narrow `IntegrationEngine::send()` based on
`SomeAction::getName()`. The engine resolves that string through its configured
`ConfigPort`; the expression does not guarantee which action class the runtime
configuration selects. Concrete facades should declare the response type and
validate the engine result. See the [inference decision](adr/v8-phpstan.md).

## Verification

```bash
vendor/bin/phpunit tests/PHPStan
vendor/bin/phpstan diagnose
```

Rule fixtures have the `.php.fixture` extension so intentionally invalid examples
are analysed by their rule tests instead of being treated as application source by
the repository's general PHPStan run. Fixtures are not loaded by the extension.
