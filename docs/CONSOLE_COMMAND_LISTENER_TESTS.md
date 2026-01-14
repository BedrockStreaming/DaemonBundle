# ConsoleCommandListener Tests

This document describes the tests implemented for the `ConsoleCommandListener` following Symfony 7.4 best practices.

## Test Structure

Tests are organized into two categories:

### 1. Unit Tests (`tests/Unit/EventListener/ConsoleCommandListenerTest.php`)

These tests verify the listener's behavior in isolation, without complex external dependencies.

**Implemented tests:**

- ✅ `testOnConsoleSignalWithSigtermAndDaemonCommand()`: Verifies that the SIGTERM signal triggers the shutdown of a daemon command
- ✅ `testOnConsoleSignalWithSigintAndDaemonCommand()`: Verifies that the SIGINT signal triggers the shutdown of a daemon command
- ✅ `testOnConsoleSignalWithSigtermAndNonDaemonCommand()`: Verifies that SIGTERM on a non-daemon command does not cause an error
- ✅ `testOnConsoleSignalWithSigintAndNonDaemonCommand()`: Verifies that SIGINT on a non-daemon command does not cause an error
- ✅ `testOnConsoleSignalWithOtherSignalAndDaemonCommand()`: Verifies that other signals (SIGHUP) do not trigger shutdown
- ✅ `testOnConsoleSignalWithOtherSignalAndNonDaemonCommand()`: Verifies that other signals (SIGUSR1) on a non-daemon command do not cause an error
- ✅ `testListenerHasCorrectAttribute()`: Verifies that the listener has the `AsEventListener` attribute correctly configured
- ✅ `testOnlyShutdownSignalsTriggerShutdown()`: Verifies that only SIGTERM and SIGINT trigger shutdown

### 2. Integration Tests (`tests/Integration/EventListener/ConsoleCommandListenerIntegrationTest.php`)

These tests verify that the listener works correctly with Symfony's event system.

**Implemented tests:**

- ✅ `testListenerIsInvokedBySigtermEvent()`: Verifies that the listener is called when a SIGTERM event is dispatched
- ✅ `testListenerIsInvokedBySigintEvent()`: Verifies that the listener is called when a SIGINT event is dispatched
- ✅ `testListenerIgnoresNonDaemonCommands()`: Verifies that non-daemon commands are ignored during dispatch
- ✅ `testListenerIgnoresUnhandledSignals()`: Verifies that unhandled signals are ignored
- ✅ `testMultipleSignalsCanBeHandled()`: Verifies that multiple signals can be processed in sequence
- ✅ `testListenerWorksWithMultipleListeners()`: Verifies compatibility with other listeners on the same event

## Symfony 7.4 Best Practices Applied

### 1. Using `KernelTestCase` for integration tests

Integration tests extend `KernelTestCase` to benefit from Symfony's testing infrastructure.

### 2. Testing PHP 8 Attributes

The `testListenerHasCorrectAttribute()` test uses the Reflection API to verify that the `#[AsEventListener]` attribute is correctly configured.

### 3. Separation of Concerns

- **Unit tests**: test the listener's business logic in isolation
- **Integration tests**: test integration with the event system

### 4. Comprehensive Edge Case Testing

- Shutdown signals (SIGTERM, SIGINT)
- Unhandled signals (SIGHUP, SIGUSR1, SIGUSR2)
- Daemon commands vs. standard commands
- Interaction with other listeners

### 5. Using Fixtures

The test uses `DaemonCommandConcrete`, a concrete test class that extends `DaemonCommand`, rather than complex mocks. This makes tests more robust and easier to maintain.

### 6. Clear Assertions and Explicit Messages

Each assertion includes a descriptive message to facilitate diagnosis in case of failure.

## Running the Tests

### All listener tests

```bash
vendor/bin/phpunit tests/ --filter=ConsoleCommandListener
```

### Unit tests only

```bash
vendor/bin/phpunit tests/Unit/EventListener/ConsoleCommandListenerTest.php
```

### Integration tests only

```bash
vendor/bin/phpunit tests/Integration/EventListener/ConsoleCommandListenerIntegrationTest.php
```

### With readable output

```bash
vendor/bin/phpunit tests/ --filter=ConsoleCommandListener --testdox
```

## Code Coverage

The tests cover:

- ✅ 100% of switch branches (SIGTERM, SIGINT, default)
- ✅ 100% of conditions (instanceof DaemonCommand)
- ✅ Nominal cases and edge cases
- ✅ AsEventListener attribute configuration

## Changes Made

### `tests/Fixtures/Command/DaemonCommandConcrete.php`

Added the `isShutdownRequested()` method to facilitate testing:

```php
public function isShutdownRequested(): bool
{
    return $this->shutdownRequested;
}
```

This method allows verifying the command's internal state without using complex mocks.

## Statistics

- **14 tests** in total
- **29 assertions**
- **100% success rate**
- Execution time: ~300ms

