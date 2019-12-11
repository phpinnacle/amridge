# PHPinnacle Amridge

[![Software License][ico-license]](LICENSE.md)

PHPinnacle Amridge is asynchronous port of [Goridge](https://github.com/spiral/goridge) protocol client.

## Install

Via Composer

```bash
$ composer require phpinnacle/amridge
```

## Basic Usage

Simple Golang RPC Echo server:

```golang
package main

import (
	"fmt"
	"github.com/spiral/goridge"
	"log"
	"net"
	"net/rpc"
)

type App struct{}

func (a *App) Hi(text string, r *string) error {
	*r = fmt.Sprintf(text)
	return nil
}

func main() {
	ln, err := net.Listen("tcp", ":6001")
	if err != nil {
		panic(err)
	}

	rpc.Register(new(App))

	for {
		conn, err := ln.Accept()
		if err != nil {
			continue
		}

		go rpc.ServeCodec(goridge.NewCodec(conn))
	}
}
```

And PHP client:

```php
use Amp\Loop;
use PHPinnacle\Goridge\RPC;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {
    /** @var RPC $rpc */
    $rpc = yield RPC::connect('tcp://127.0.0.1:6001');

    echo yield $rpc->call("App.Hi", "World");

    $rpc->disconnect();
});
```

## Testing

```bash
$ composer test
```

## Benchmarks

```bash
$ composer bench
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email dev@phpinnacle.com instead of using the issue tracker.

## Credits

- [PHPinnacle][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square

[link-author]: https://github.com/phpinnacle
[link-contributors]: https://github.com/phpinnacle/amridge/graphs/contributors
