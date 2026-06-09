# Convertly PHP SDK

Official PHP client for the <a href="https://docs.convertly.sh/docs/php-sdk" target="_blank" rel="noopener noreferrer">Convertly media API</a>.

```bash
composer require convertly/convertly-php
```

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Convertly\ConvertlyClient;

$convertly = new ConvertlyClient(getenv('CONVERTLY_API_KEY'));

$result = $convertly->convertFile(__DIR__ . '/photo.png', 'webp', [
    'compression' => 'balanced',
    'saveToStorage' => false,
]);
```

## Image CDN

This SDK covers the **REST media API**. CDN delivery is URL-based — see <a href="https://docs.convertly.sh/docs/image-cdn" target="_blank" rel="noopener noreferrer">Image CDN</a> and the <a href="https://docs.convertly.sh/guides/image-cdn-setup" target="_blank" rel="noopener noreferrer">setup guide</a>. For JS/TS CDN helpers: <a href="https://www.npmjs.com/package/@convertly-sh/image" target="_blank" rel="noopener noreferrer">`@convertly-sh/image`</a>.

## License

**MIT** © <a href="https://convertly.sh" target="_blank" rel="noopener noreferrer">Convertly</a>.

- Summary: <a href="https://opensource.org/license/mit" target="_blank" rel="noopener noreferrer">MIT on Open Source Initiative</a>
- Full text: `LICENSE` in this package (included when installed via Composer / Packagist)
