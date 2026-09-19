<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

/**
 * Failure raised by test doubles, so a deliberate fake error is never
 * confused with a generic runtime exception from the code under test.
 */
final class FakeFailure extends \RuntimeException {}
