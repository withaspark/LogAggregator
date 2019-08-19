# withaspark/LogAggregator

## Install

1. Install package.
   ```sh
   composer require withaspark/log-aggregator
   ```
2. Define your configuration of hosts, logging groups, and log files.
   ```sh
   cp config.example.json config.json
   ```
   Then edit the entries.
3. Setup ssh public keys.

## Usage

### CLI

#### To build index

The `pull` command fetches all log files configured to be watched and builds a
master log index containing all records.

**NOTE:** Deduplication is in place to eliminate duplicate records being indexed
with each run, but will also eliminate identical log messages that occurred on
the same host, log file, and with the same timestamp (if present).

To run,
```sh
path/to/pull <local storage directory>
```

For example,
```sh
./pull logs
```

#### To query index

The `query` command will search the master log index for all records matching.
Simple search terms like "Page not found" as well as regular expressions are
supported.

Each log message returned from the index will contain the hostname and log file
name prepended to the line. This allows filtering by hostname and log file name
in addition to the search pattern.

To run,
```sh
path/to/search "<search regex>"
```

For example,
```sh
./search "host\.example\.com.*auth\.log.*Failed password"
```

#### To parse and store in local SQLite database

The `analyze` command parses the master log index and stores messages to a local
SQLite database for easier integration for other use-cases and extensions.

**NOTE:** This feature requires the PHP pdo-sqlite extension be installed.

The database and tables will automatically be created, as needed, and saved to
`./database/database.sqlite`.

Log messages are saved to the `logs` table. Available columns include:

| Name | Datatype | Description |
|---|---|---|
|`id`|`INTEGER AUTOINCREMENT`|Unique ID of the row. For future use.|
|`hash`|`TEXT`|The SHA-1 hash of the original log message. Used for deduplication.|
|`host`|`TEXT`|The full hostname used to make the connection to the host when pulling the logs.|
|`file`|`TEXT`|The name of the log file the message was found in.|
|`message`|`TEXT`|The full log message.|
|`created_at`|`DATETIME`|The datetime the message was first seen by the `analyze` command.|

To run,
```sh
path/to/analyze
```

For example,
```sh
./analyze
```

#### To tail logs

The `tail` command can be used to simultaneously tail all of the configured
remote logs.

To run,
```sh
path/to/tail
```

For example,
```sh
./tail
```

Send SIGINT `<ctrl>`+`c` to exit.

## Roadmap

* Accept config file as commandline argument. This would allow separate config
  files for different scenarios and commands. In lieu of this feature, we may
  add support for a command-line option to scope by host, log group, and/or log
  file.
* Config file validation to minimize unhelpful error messages.
* Handle losses of connectivity to remote hosts.
* Add default log groups that require only hosts, e.g., syslog, apache, nginx, etc.
