# Mailchimp API

## Instructions

Require the package in the `composer.json` file of your project, and map the package in the `repositories` section.

```json
{
    "require": {
        "php": ">=8.1",
        "anibalealvarezs/mailchimp-api": "@dev"
    },
    "repositories": [
        {
            "type": "composer", "url": "https://satis.anibalalvarez.com/"
        }
    ]
}
```

## Methods

## Error Handling

- The SDK now uses a semantic classifier at `src/Support/MailchimpErrorClassifier.php`.
- Both APIs wire the classifier as callable detector:

```php
$this->setRateLimitDetector([MailchimpErrorClassifier::class, 'isRetryable']);
```

- Applied in:
  - `src/Services/Marketing/MarketingApi.php`
  - `src/Services/Transactional/TransactionalApi.php`

- Retry behavior is focused on throttling/rate-limit conditions (`429`, `rate_limit`, `throttled`, `too many requests`).
- Non-throttling failures are still returned through the regular exception flow.

