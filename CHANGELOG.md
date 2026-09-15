# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

Full history predating this file (v1.0.0 through v4.1.0) is reconstructed
from git tags separately; see the pinned entries below this line once that
work lands.

## [Unreleased]

### Fixed

- `make:integration` generated `public const string NAME`, PHP 8.3 syntax,
  even though `composer.json` declares `php >=8.2` — the generated code
  raised a fatal parse error on 8.2 and 8.3-but-not-yet-upgraded projects.
  The generator, `IntegrationName`'s docblock example, and every
  documentation example now use the untyped `public const NAME`.
