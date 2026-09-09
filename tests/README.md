# TopoMojo Plugin Unit Tests

This directory contains PHPUnit tests for the mod_topomojo plugin, following Moodle best practices.

## Test Files

- **lib_test.php** - Tests for core library functions in lib.php
- **locallib_test.php** - Tests for local library functions in locallib.php
- **topomojo_test.php** - Tests for the topomojo class
- **topomojo_attempt_test.php** - Tests for the topomojo_attempt class
- **topomojo_question_test.php** - Tests for the topomojo_question class
- **events_test.php** - Tests for Moodle events (course_module_viewed, attempt_started, etc.)
- **questionmanager_test.php** - Tests for questionorder resolution in the questionmanager
- **backup/backup_restore_test.php** - Round-trips an activity through backup and restore
- **utils/grade_test.php** - Tests for grading attempts that carry no questions
- **local/bulkdeploy/** - Tests for the bulk deploy job, launcher and repositories
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
- Backup and restore of activity settings, question links and intro files
- Grading attempts that carry no question usage

### Backup and restore

`backup/backup_restore_test.php` guards the failure that has no other symptom: a column added to
`db/install.xml` and to `mod_form.php` but forgotten in `backup/moodle2/backup_topomojo_stepslib.php`
restores as its column default, losing the teacher's setting silently. If you add a column to the
`topomojo` table, `test_backup_xml_covers_every_activity_column` fails until you either add it to the
backup structure or add it to `backup_restore_test::NOT_BACKED_UP` with a reason.