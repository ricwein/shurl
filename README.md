# shurl

> Another PHP based URL shortener.

## Database

It's using a PDO Database Wrapper in the background, so you're not limited to MySQL!
But even if so, this project uses prepared statements, to prevent sql injections.

To init a new MySQL database, just run the queries in [ressources/database.sql](ressources/database.sql).

## Configuration

The default configuration is stored in [src/config/Config.php](src/config/Config.php) `$__config` Array, but can be overwritten by adding a config-file at `config/config.yml`.
It should has the following format:

```yaml
---
database:
  username: usernamehere
  prefix: shurl_

development: true
```

This way it's possible, to override each single setting.

## Adding a new URL

Adding a new URL to the shortener Server is done with the bundled CLI tool. Just run `./bin/shurl add`, and follow the instructions.
For more Informations see `./bin/shurl add --help`.
