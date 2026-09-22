# 0015 · Keep Symfony error classification outside Core

- **Status:** Accepted
- **Date:** 2026-09-22

## Context

Deptrac found two Symfony dependencies in Core's ErrorClassifier. Its static
methods and ExponentialBackoffPolicy already accept Symfony exceptions, so simply
removing that behavior would break callers even though the engine does not wire
these utilities automatically.

## Decision

Core owns ErrorClassification, ErrorClassifierInterface, EngineErrorClassifier
and ExponentialBackoff. The default classifier understands engine exceptions;
status 0 remains ambiguous and does not imply a network failure.
Infrastructure's SymfonyErrorClassifier translates Symfony exceptions to that
Core value. Applications inject it when they need Symfony-aware decisions.

The two legacy classes keep their exact public names, signatures and Symfony-aware
behavior as deprecated facades in src/Compatibility, explicitly loaded through
Composer's classmap. Their old Symfony service IDs are registered explicitly;
Compatibility is excluded from PSR-4 service discovery. They are included in
mutation testing and classified as a separate outer layer by Deptrac. Core cannot
depend on Compatibility, Infrastructure or Symfony. No aliases, reflective type
checks, ignored violations or broader Core rule conceal the legacy dependencies.

## Alternatives considered

- Remove Symfony support: breaks existing utility callers.
- Make Core depend on an adapter by default: reverses the desired dependency.
- Global classifier registration: adds process state and initialization order.
- Treat Symfony as an allowed Core dependency: abandons the architecture promise.

## Consequences

Existing Composer users need no source change after regenerating the autoloader.
New callers select the classifier explicitly. Directly requiring internal files
by their former disk path is not supported. Serialized legacy policy instances
are not a supported persistence format; no serialization compatibility is claimed.
The compatibility layer can be removed only with a documented major migration.

## References

- [Core classifier contract](../../src/Core/Resilience/ErrorClassifierInterface.php)
- [Symfony adapter](../../src/Infrastructure/Resilience/SymfonyErrorClassifier.php)
- [Boundary tests](../../tests/Core/Resilience/ResilienceBoundaryTest.php)
- [Existing utility behavior tests](../../tests/Core/Resilience/ErrorClassifierTest.php)
- [Deptrac configuration](../../deptrac.yaml)
