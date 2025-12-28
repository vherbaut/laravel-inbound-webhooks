# Contributing

Thank you for considering contributing to Laravel Inbound Webhooks! This document provides guidelines and instructions for contributing.

## Code of Conduct

Please be respectful and constructive in all interactions. We welcome contributors of all experience levels.

## How to Contribute

### Reporting Bugs

Before submitting a bug report:

1. Check the [existing issues](https://github.com/vherbaut/laravel-inbound-webhooks/issues) to avoid duplicates
2. Use the latest version of the package
3. Provide a clear description with steps to reproduce

Include in your bug report:

- PHP version
- Laravel version
- Package version
- Minimal code example to reproduce
- Expected vs actual behavior

### Suggesting Features

Feature requests are welcome! Please:

1. Check existing issues and discussions first
2. Describe the use case clearly
3. Explain why this feature would benefit other users

### Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run the test suite (`./vendor/bin/pest`)
5. Run static analysis (`./vendor/bin/phpstan analyse`)
6. Run code style fixer (`./vendor/bin/pint`)
7. Commit your changes (`git commit -m 'Add amazing feature'`)
8. Push to the branch (`git push origin feature/amazing-feature`)
9. Open a Pull Request

## Development Setup

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/laravel-inbound-webhooks.git
cd laravel-inbound-webhooks

# Install dependencies
composer install

# Run tests
./vendor/bin/pest

# Run static analysis
./vendor/bin/phpstan analyse

# Fix code style
./vendor/bin/pint
```

## Coding Standards

This project follows:

- [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style
- [Laravel conventions](https://laravel.com/docs/contributions#coding-style)
- PHPStan level 5 for static analysis

### Documentation

- Add PHPDoc blocks to all classes and public methods
- Use `@param`, `@return`, and `@throws` annotations
- Document complex logic with inline comments

### Testing

- Write tests for all new features
- Maintain or improve code coverage
- Use descriptive test names

```php
it('validates stripe webhook signature', function () {
    // Test implementation
});
```

## Adding a New Driver

To add support for a new webhook provider:

1. Create a new driver class in `src/Drivers/`
2. Extend `AbstractDriver` and implement `DriverInterface`
3. Add the driver to `DriverManager::$builtInDrivers`
4. Write comprehensive tests in `tests/Unit/Drivers/`
5. Update the README with configuration examples
6. Update the CHANGELOG

Example driver structure:

```php
class NewProviderDriver extends AbstractDriver
{
    protected const SIGNATURE_HEADER = 'X-Provider-Signature';

    public function validateSignature(Request $request): void
    {
        // Implementation
    }

    public function getEventType(Request $request): ?string
    {
        // Implementation
    }

    public function getExternalId(Request $request): ?string
    {
        // Implementation
    }

    protected function getRelevantHeaders(): array
    {
        return array_merge(parent::getRelevantHeaders(), [
            self::SIGNATURE_HEADER,
        ]);
    }
}
```

## Questions?

If you have questions, feel free to:

- Open a [Discussion](https://github.com/vherbaut/laravel-inbound-webhooks/discussions)
- Open an [Issue](https://github.com/vherbaut/laravel-inbound-webhooks/issues)

Thank you for contributing!