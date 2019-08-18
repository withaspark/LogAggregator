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

```sh
path/to/pull <local working directory>
```

For example,
```sh
./pull logs
```

#### To query index

```sh
path/to/search "<search regex>"
```

For example,
```sh
./search "host\.example\.com.*auth\.log.*Failed password"
```

#### To tail logs

```sh
path/to/tail
```

For example,
```sh
./tail
```

Send SIGINT `<ctrl>`+`c` to exit.

## Roadmap

* Accept config file as commandline argument. This would allow separate config files for different scenarios and commands.
* Config file validation to minimize unhelpful error messages.
* Handle losses of connectivity to remote hosts.
* Add default log groups that require only hosts, e.g., syslog, apache, nginx, etc.
