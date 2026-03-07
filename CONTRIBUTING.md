# Contributing Guideline

Thank you for your interest in contributing!

## How to Contribute

1. **Bug Reports**: Use the GitHub issue tracker to report bugs. Please include steps to reproduce.
2. **Feature Requests**: Open an issue to discuss new features before implementing them.
3. **Pull Requests**:
   - Branch from `develop`.
   - Ensure all tests pass (`vendor/bin/phpunit`).
   - Ensure static analysis passes (`vendor/bin/phpstan analyse`).
   - Follow PSR-12 coding standards.

## Development Setup

1. Clone the repository.
2. Run `composer install`.
3. Run `vendor/bin/phpunit` to verify the setup.