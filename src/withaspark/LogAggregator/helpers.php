<?php

namespace withaspark\LogAggregator;

use withaspark\LogAggregator\Host;
use withaspark\LogAggregator\Group;

if (! function_exists('withaspark\LogAggregator\parse')) {
    function parse(string $config = 'config.json'): array
    {
        $config = json_decode(file_get_contents('config.json'), false);
        $hosts_config = $config->hosts;
        $groups_config = $config->groups;

        $hosts = [];
        foreach ($hosts_config as $host) {
            $hosts[$host->host] = new Host($host->host, $host->port, $host->user);
        }

        $groups = [];
        foreach ($groups_config as $group) {
            $hs = [];
            if ($group->hosts === '*') {
                $hs = $hosts;
            } else {
                foreach ($group->hosts as $h) {
                    if (array_key_exists($h, $hosts)) {
                        $hs[] = $hosts[$h];
                    }
                }
            }

            // TODO: If already exists, append hosts and logs instead of overwriting
            if (! array_key_exists($group->name, $groups)) {
                $groups[$group->name] = new Group($hs, $group->logs);
                continue;
            }

            $hs = array_merge($groups[$group->name]->hosts, $hs);
            $ls = array_merge($groups[$group->name]->logs, $group->logs);
            $groups[$group->name] = new Group($hs, $ls);
        }

        return [$hosts, $groups];
    }
}

if (! function_exists('withaspark\LogAggregator\pull')) {
    function pull(string $output_dir = '/home/logging/logs'): void
    {
        list($hosts, $groups) = parse('config.php');

        $output_dir = rtrim($output_dir ?: '/home/logging/logs', '/');
        if (! is_writable($output_dir)) {
            fprintf(STDERR, "\nERROR: Unable to write to output directory %s\n\nCreate or make writeable by current user.\n", $output_dir);
            exit(1);
        }

        $map = Group::getMap();
        $count = 0;
        $total = count($map, COUNT_RECURSIVE) - count($map);
        $datestamp = date('Ymd_His');

        echo sprintf('WASLAP_START=%d', time()) . "\n";
        echo sprintf("echo \"-- %s --\n\"", date('Y-m-d H:i:s')) . "\n";

        foreach ($map as $h => $logs) {
            $host = $hosts[$h];

            foreach ($logs as $log) {
                $count++;

                $target = sprintf('%s/%s/%s', $output_dir, $host->host, dirname($log));

                if (! file_exists($target)) {
                    mkdir($target, 0755, true);
                }

                echo sprintf(
                    'echo \'[%s%%] Fetching %s from %s ...\'',
                    str_pad(number_format(($count - 1) / $total * 100, 0), 3, ' ', STR_PAD_LEFT),
                    $log,
                    $host->host
                ) . "\n";
                echo sprintf(
                    'scp -P %d "%s@%s:%s" "%s"',
                    $host->port,
                    $host->user,
                    $host->host,
                    $log,
                    $target
                ) . "\n";
            }
        }

        echo 'echo [100%] All logs fetched.' . "\n";

        echo "echo \"\n[  0%] Building master index ...\"" . "\n";

        echo "find . -name '*.log' -type f -exec egrep -H '.*' {} \; | sort -u >master.log; echo \"[100%] Master index built.\"" . "\n";

        echo 'WASLAP_END=$(date \'+%s\')' . "\n";

        echo sprintf("echo \"\n-- \$(date '+%%Y-%%m-%%d %%H:%%M:%%S') --\"", date('Y-m-d H:i:s')) . "\n";
        echo "echo \"\nElapsed: \$((WASLAP_END - WASLAP_START)) s\"" . "\n";

        echo "echo \"\nYou may now query logs with:\n   search '<search regex>'\n\n   Prepend with the hostname and filename to narrow.\"" . "\n";
    }
}

if (! function_exists('withaspark\LogAggregator\tail')) {
    function tail(): void
    {
        list($hosts, $groups) = parse('config.php');

        $map = Group::getMap();
        $datestamp = date('Ymd_His');

        echo sprintf("echo -- %s --\n", date('Y-m-d H:i:s')) . "\n";

        foreach ($map as $h => $logs) {
            $host = $hosts[$h];

            foreach ($logs as $log) {
                echo sprintf('ssh -p %d "%s@%s" tail -n1 -f "%s" &', $host->port, $host->user, $host->host, $log) . "\n";
            }
        }
    }
}
