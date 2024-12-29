## Installation

You can install the package via composer:

```bash
composer require shopapps/laravel-settings
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-settings-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-settings-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-settings-views"
```

## Usage

```php
$variable = new VendorName\Skeleton();
echo $variable->echoPhrase('Hello, VendorName!');
```
