# Convertly PHP SDK

Official PHP client for the Convertly media API.

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

if (!$result['ok']) {
    throw new RuntimeException($result['error']);
}
```

Raster-to-SVG conversion preserves color by default. Pass `'mono' => true` only for monochrome tracing.

## Convertly Storage

```php
$rootFolders = $convertly->getFolders('');
$rootFiles = $convertly->getFiles('', 100, 0);

$nestedFiles = $convertly->getFiles($folderId, 100, 0);
$uploaded = $convertly->uploadFile(__DIR__ . '/hero.jpg', $folderId);
```

Pass an empty string to `getFolders()` or `getFiles()` to list only the root level. Omit the folder argument to list across the account.
