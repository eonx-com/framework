# EoneoPay Framework

This package requires [Lumen](https://lumen.laravel.com) framework and defines base classes to handle common features
across EoneoPay applications.

Base classes provided are as following:

- `EoneoPay\Framework\Http\Controllers\Controller`: Entity endpoints features
- `EoneoPay\Framework\Database\Entities\Entity`: Entity exceptions abstract methods
- `EoneoPay\Framework\Helpers\VersionHelper`: Guess requested version based on request
- `EoneoPay\Framework\Exceptions\ExceptionHandler`: Handle common exceptions
