# Elegant database query builder for connecting to FileMaker databases

[![Latest Version on Packagist](https://img.shields.io/packagist/v/theprivateer/filemaker.svg?style=flat-square)](https://packagist.org/packages/theprivateer/filemaker)
[![Total Downloads](https://img.shields.io/packagist/dt/theprivateer/filemaker.svg?style=flat-square)](https://packagist.org/packages/theprivateer/filemaker)

This is an elegant object-orientated approach to dealing with FileMaker databases from PHP applications, very heavily inspired by the [database query builder](https://laravel.com/docs/8.x/queries) found in Laravel.

It also allows you to effortlessly switch between the traditional XML-based FMPHP connection method and the newer FMREST Data API.

The list of supported queries is still a bit limited, but does allow for some fairly complex requests under the hood.  Over time I will be adding more query types to the package.

## Installation

You can install the package via composer:

```bash
composer require theprivateer/filemaker
```

## Usage

```php
$config = [
    'driver'	=> 'fmphp',
    'host'      => '127.0.0.1',
    'file'      => 'DatabaseName',
    'user'      => 'admin',
    'password'  => 'someP@ssword',
];

$fm = new Privateer\FileMaker\FileMaker($config);

$user = $fm->layout('users')->where('username', 'privateer')->first();

$newUser = $fm->layout('users')->insert([
    'name'      => 'John Doe',
    'username'  => 'johndoe',
    'email'     => 'john@example.com'
]);
```

### Testing

I'm working on test coverage - watch this space!

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email phils@hey.com instead of using the issue tracker.

## Credits

-   [Phil Stephens](https://github.com/theprivateer)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## PHP Package Boilerplate

This package was generated using the [PHP Package Boilerplate](https://laravelpackageboilerplate.com) by [Beyond Code](http://beyondco.de/).
