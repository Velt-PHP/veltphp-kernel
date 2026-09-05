# Changelog

## Unreleased

### Added

- Runtime state machine and stable lifecycle hooks/events.
- Explicit application, request and preview session scopes.
- Portable execution queue, task cancellation and expiration contracts.
- Fatal runtime failure signal with host reconstruction request.
- Portable migration, cache and extension event contracts.

### Changed

- Runtime lifecycle guards now reject handling while paused or after shutdown.
- Runtime reset clears the current execution termination state.
- Shutdown and fatal failure paths release request and preview resources.

### Compatibility

- No Android API, `Activity`, `Context`, thread or storage implementation is included in the Kernel.
- Concrete execution, Android lifecycle integration and instrumentation remain the responsibility of the owning runtime.
