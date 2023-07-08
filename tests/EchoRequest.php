<?php

declare(strict_types=1);

namespace Jenky\Atlas\Pool\React\Tests;

use Jenky\Atlas\Request;

final class EchoRequest extends Request
{
    public function __construct(private string $method = 'get')
    {
    }

    public function method(): string
    {
        return $this->method;
    }

    public function endpoint(): string
    {
        return mb_strtolower($this->method);
    }
}
