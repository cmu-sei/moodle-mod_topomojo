# TopoMojo Plugin Unit Tests

This directory contains PHPUnit tests for the mod_topomojo plugin, following Moodle best practices.

## Test Files

- **lib_test.php** - Tests for core library functions in lib.php
- **locallib_test.php** - Tests for local library functions in locallib.php
- **topomojo_test.php** - Tests for the topomojo class
- **topomojo_attempt_test.php** - Tests for the topomojo_attempt class
- **topomojo_question_test.php** - Tests for the topomojo_question class
- **events_test.php** - Tests for Moodle events (course_module_viewed, attempt_started, etc.)
- **generator/lib.php** - Data generator for creating test instances

## Prerequisites

1. Ensure PHPUnit is installed and configured in your Moodle installation
2. Initialize PHPUnit for Moodle (if not already done):
   ```bash
   php admin/tool/phpunit/cli/init.php
   ```

## Running Tests

### Run all TopoMojo tests
```bash
vendor/bin/phpunit --testsuite mod_topomojo_testsuite
```

### Run specific test file
```bash
vendor/bin/phpunit mod/topomojo/tests/lib_test.php
```

### Run specific test class
```bash
vendor/bin/phpunit --filter lib_test
```

### Run specific test method
```bash
vendor/bin/phpunit --filter test_topomojo_supports
```

### Run with coverage (if configured)
```bash
vendor/bin/phpunit --coverage-html coverage/ mod/topomojo/tests/
```

## Test Coverage

The test suite covers:

- Module installation and lifecycle (add, update, delete)
- Feature support declarations
- Grade management
- Review options and display settings
- User capability checks
- Attempt creation and management
- Question handling
- Event triggering and validation
- API client setup (API key and OAuth)
- State management (open, closed, unopen)