# shurl

> Another PHP based URL shortener.


## Install

### project

To create a new shurl instance, the project can be initialized through composer, just run the following command:

```bash
composer create-project ricwein/shurl -s dev
```

### Database

Initializing the Database structure can be done two ways. Either run the static queries from [resources/database/database.sql](resources/database/database.sql), or use the `init` commandline tool.

> This tool requires you, to first setup your wished database configuration! This can be done, as described in [Configuration](#configuration). A valid Database-user is required, and needs either: the database to be already existing, or rights to create a new one.

```bash
bin/shurl init
```

Since this tool, acknowledges your database-configuration, the resulting database-structure is customizable! use table-prefixes, custom charsets, or even another database name!

## Configuration

The default configuration is stored in [src/Config/Config.php](src/Config/Config.php) `$__config` Array, but can be overwritten by adding a config-file at `config/config.yml`.
It should has the following format:

```yaml
---
database:
  username: usernamehere
  prefix: shurl_

cache:
  enabled: true
```

This way it's possible, to override each single setting.

You can copy an example file to start with

```bash
cp config/example.config.yml config/config.yml
```

### rootURL

For some features, shurl need to know your webservers Domain and Path to shurl.
This URL can be set inside the described configfile:

```yaml
---
rootURL: https://fancy.url
```

## Adding a new URL

Adding a new URL to the shortener Server is done with the bundled CLI tool. Just run `bin/shurl url:add`, and follow the instructions.
For more Informations see `bin/shurl url:add --help`.

## Remove an URL

Removing an URL from the list, is as easy as adding one. Execute `bin/shurl url:remove` from your command line should do the job.

## List available URLs

A list of all currently available URls can be accessed through: `bin/shurl url:show`

## Routes

There are several different routes, which are supported by shurl.

- **/** The root displays a welcome message
- **/<i>{slug}</i>** redirects to a given url
- **/preview/<i>{slug}</i>** shows a resolved-URL preview
- **/api/<i>{slug}</i>** exposes a simple JSON api (`GET` Method), which provides access to the resolved URL

```json
[{
	"slug": "shurl",
	"original": "https:\/\/s.ricwein.com"
}]
```
