# withaspark/LogAggregator

## Install

1. Install package.
   ```sh
   composer require withaspark/LogAggregator
   ```
2. Define your hosts, logging groups, and log files.
   ```sh
   cp config.php.example config.php
   ```
   Then edit the entries.

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
path/to/tail "<search regex>"
```

For example,
```sh
./tail
```

Send SIGINT `<ctrl>+c` to exit.
