<?php

declare(strict_types=1);

namespace Jenky\Atlas\Pool\React\Tests;

use Jenky\Atlas\Contracts\ConnectorInterface;
use Jenky\Atlas\Traits\ConnectorTrait;

final class EchoConnector implements ConnectorInterface
{
    use ConnectorTrait;

    public static function baseUri(): ?string
    {
        return 'https://postman-echo.com/';
    }
}
