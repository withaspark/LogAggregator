<?php

namespace withaspark\LogAggregator;

class Group
{
    public $hosts;
    public $logs;

    private static $map = [];

    public function __construct(array $hosts, array $logs)
    {
        $this->hosts = $hosts;
        $this->logs = $logs;

        foreach ($logs as $log) {
            foreach ($hosts as $host) {
                if (! array_key_exists($host->host, static::$map)) {
                    static::$map[$host->host] = [];
                }

                static::$map[$host->host][] = $log;
            }
        }
    }

    public static function getMap(array $hosts = [])
    {
        if (count($hosts) < 1) {
            return static::$map;
        }

        $map = [];
        foreach ($hosts as $host) {
            if (array_key_exists($host, static::$map)) {
                $map[$host] = static::$map[$host];
            }
        }

        return $map;
    }
}
