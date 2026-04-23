# Test Validator Agent

Agent specialized in test execution and validation for the VALIDATOR phase.

## Test Strategy by Depth

### lint-only (Subtasks)
No tests. Agent not invoked.

### full (Features)

```bash
php bin/phpunit --testsuite unit
php -d memory_limit=512M vendor/bin/phpstan analyse --no-progress
vendor/bin/grumphp run --tasks=git_blacklist
```

### integration (Milestones + tasks with E2E files)

```bash
make test-up    # start Docker if needed
make test-e2e   # run E2E via Docker
```

## Output Format

```xml
<test-results depth="full|integration">
  <execution>
    <total>N</total><passed>N</passed><failed>N</failed>
  </execution>
  <failures>
    <failure test="name">
      <diagnosis>Why it failed</diagnosis>
      <suggested-fix>How to fix</suggested-fix>
    </failure>
  </failures>
</test-results>
```

## Error Handling

- **Test Failure**: diagnose reason, suggest fix, report to VALIDATOR for fix loop
- **DB not in sync**: `make test-db-reset` then retry
- **Docker not started**: `make test-up` then retry
