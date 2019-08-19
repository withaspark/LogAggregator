<?php

namespace withaspark\LogAggregator;

use PDO;
use PDOException;
use PDOStatement;
use withaspark\LogAggregator\Host;
use withaspark\LogAggregator\Group;

if (! function_exists('withaspark\LogAggregator\parse')) {
    /**
     * Parse the configuration file.
     *
     * @param string $config Path to the configuration file
     * @return [\withaspark\LogAggregator\Host[], \withaspark\LogAggregator\Group[]]
     */
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
    /**
     * Echo the shell commands required to make a local copy of all logs being
     * watched. These commands must be executed in a shell.
     *
     * @param string $output_dir Path to the directory where logs will be kept
     * @return void
     */
    function pull(string $output_dir = '/home/logging/logs'): void
    {
        list($hosts, $groups) = parse('config.php');

        $output_dir = rtrim($output_dir, '/');
        if (! is_writable($output_dir)) {
            fprintf(STDERR, "\nERROR: Unable to write to output directory %s\n\nCreate or make writeable by current user.\n", $output_dir);
            exit(1);
        }

        $map = Group::getMap();
        $count = 0;
        $total = count($map, COUNT_RECURSIVE) - count($map);

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
                    escapeshellarg($log),
                    escapeshellarg($host->host)
                ) . "\n";
                echo sprintf(
                    'scp -P %d %s@%s:%s %s',
                    $host->port,
                    escapeshellarg($host->user),
                    escapeshellarg($host->host),
                    escapeshellarg($log),
                    escapeshellarg($target)
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

if (! function_exists('withaspark\LogAggregator\analyze')) {
    /**
     * Creates a local database of all messages that have been pulled.
     *
     * @return void
     */
    function analyze(): void
    {
        $total = 0;
        $count = 0;
        $lines = [];
        $last_percentage = 0;
        $fh = fopen('master.log', 'r');

        fprintf(STDOUT, "Analyzing pulled logs ...\n");
        while (! feof($fh)) {
            $total++;
            fgets($fh);
        }

        rewind($fh);
        while (! feof($fh)) {
            $count++;
            $percentage = floor(($count - 1) / $total * 100);

            if ($percentage != $last_percentage) {
                fprintf(
                    STDOUT,
                    "[%s%%] Building log file database ...\r",
                    str_pad(number_format($percentage, 0), 3, ' ', STR_PAD_LEFT)
                );

                $last_percentage = $percentage;
            }

            $line = fgets($fh);

            if ($line) {
                $lines[sha1($line)] = $line;
            }

            // Maximum of 1000 variables and 1 mil characters per query
            if (count($lines) >= 100 || $line === false) {
                // Relying on unique index for performance
                // $lines = array_intersect_key($lines, array_flip(findNewMessages(array_keys($lines))));

                if (count($lines) > 0) {
                    insertLogMessages($lines);
                }
                $lines = [];
            }
        }
        fclose($fh);

        fprintf(STDOUT, "[%s%%] Log file database successfully built.\n", 100);
    }
}

if (! function_exists('withaspark\LogAggregator\tail')) {
    /**
     * Echo the shell commands required to tail  all of te logs being watched.
     * These commands must be executed in a shell.
     *
     * @return void
     */
    function tail(): void
    {
        list($hosts, $groups) = parse('config.php');

        $map = Group::getMap();

        echo sprintf("echo -- %s --\n", date('Y-m-d H:i:s')) . "\n";

        foreach ($map as $h => $logs) {
            $host = $hosts[$h];

            foreach ($logs as $log) {
                echo sprintf(
                    'ssh -p %d %s@%s tail -n0 -f %s &',
                    $host->port,
                    escapeshellarg($host->user),
                    escapeshellarg($host->host),
                    escapeshellarg($log)
                ) . "\n";
            }
        }
    }
}

if (! function_exists('withaspark\LogAggregator\initdb')) {
    /**
     * Establish a connection to the local log database. If one does not exist,
     * create it.
     *
     * @return \PDO
     */
    function initdb(): PDO
    {
        static $connection = null;

        if (is_null($connection)) {
            $db = 'database/database.sqlite';

            if (! file_exists($db)) {
                touch($db);
            }

            if (! is_writable($db)) {
                fprintf(STDERR, "\nERROR: Unable to write to analysis database %s\n\nCreate or make writeable by current user.\n", $db);
                exit(1);
            }

            $connection = new PDO(sprintf("sqlite:%s", $db));

            $results = $connection->query('SELECT name FROM sqlite_master WHERE type=\'table\' AND name=\'logs\';');

            if (count($results->fetchAll()) == 0) {
                createLogsTable($connection);
            }
        }

        return $connection;
    }
}

if (! function_exists('withaspark\LogAggregator\createLogsTable')) {
    /**
     * Create the logs table in the local log database.
     *
     * @param \PDO $connection PDO connection to local log database
     * @return void
     */
    function createLogsTable(PDO $connection): void
    {
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $statement = $connection->exec("
                CREATE TABLE logs (
                    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                    hash TEXT NOT NULL UNIQUE,
                    host TEXT NOT NULL,
                    file TEXT NOT NULL,
                    message TEXT NOT NULL,
                    created_at DATETIME NOT NULL
                );
                CREATE INDEX logs_hash_index ON logs (hash);
                CREATE INDEX logs_host_index ON logs (host);
                CREATE INDEX logs_file_index ON logs (file);
                CREATE INDEX logs_created_at_index ON logs (created_at);
            ");
        } catch (PDOException $e) {
            fprintf(STDERR, "\nERROR: Unable to create to analysis database %s.\n", $e->getMessage());
            exit(1);
        }
    }
}

if (! function_exists('withaspark\LogAggregator\findNewMessages')) {
    /**
     * Find which log message hashes are not found in the local log database.
     *
     * @param string[] $hashes Raw log message hashes to search for
     * @return string[]
     */
    function findNewMessages(array $hashes): array
    {
        $select = implode(', ', array_fill(0, count($hashes), '?'));
        $connection = initdb();
        $query = sprintf('SELECT `hash` FROM logs WHERE `hash` IN (%s);', $select);

        $statement = $connection->prepare($query);
        foreach ($hashes as $j => $hash) {
            $statement->bindValue(($j + 1), $hash);
        }
        $statement->execute();

        $found = array_column($statement->fetchAll(), 'hash');

        $hashes = array_diff($hashes, $found);

        return $hashes;
    }
}

if (! function_exists('withaspark\LogAggregator\insertLogMessages')) {
    /**
     * Insert the messages into the local log database with key properties
     * extracted and saved.
     *
     * @param string[] $raws Array of raw log messages indexed by hash
     * @return boolean
     */
    function insertLogMessages(array $raws): bool
    {
        static $hosts = null;

        if (is_null($hosts)) {
            $hosts = array_keys(parse('config.php')[0]);
        }

        $inserts = [];
        $time = date('Y-m-d H:i:s');

        foreach ($raws as $hash => $raw) {
            $result = explode(':', $raw, 2);

            if (count($result) !== 2) {
                return false;
            }

            list($preamble, $message) = $result;

            $matches = [];
            preg_match(sprintf('/^(.*)(%s)(.*)$/', str_replace('.', '\.', implode('|', $hosts))), $preamble, $matches);

            if (count($matches) !== 4) {
                return false;
            }

            list(, , $host, $file) = $matches;

            $inserts[] = (object) [
                'hash' => $hash,
                'host' => $host,
                'file' => $file,
                'message' => $message,
            ];
        }
        unset($raws);

        $insert = implode(',', array_fill(0, count($inserts), '(?, ?, ?, ?, ?)'));

        $connection = initdb();

        $statement = $connection->prepare(sprintf('INSERT INTO logs (`hash`, `host`, `file`, `message`, `created_at`) VALUES %s;', $insert));
        foreach ($inserts as $j => $ins) {
            $statement->bindValue((5 * $j) + 1, $ins->hash);
            $statement->bindValue((5 * $j) + 2, $ins->host);
            $statement->bindValue((5 * $j) + 3, $ins->file);
            $statement->bindValue((5 * $j) + 4, $ins->message);
            $statement->bindValue((5 * $j) + 5, $time);
        }

        $statement->execute();

        return true;
    }
}
