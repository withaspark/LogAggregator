<?php

namespace withaspark\LogAggregator;

class Host
{
    public $host;
    public $port;
    public $user;

    public function __construct(string $host, int $port, string $user)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
    }
}
