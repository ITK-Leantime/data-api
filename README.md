# Data API plugin

An API plugin for exposing data to external applications.

Copy the plugin to the folder app/Plugins/APIData, install and enable.

## What installation changes in the database

The following tables are created to track deleted entities:

* itk_projects_deleted
* itk_tickets_deleted
* itk_timesheets_deleted

3 triggers populate those tables when entities are deleted.

An `itk_data_api_modified` column, with an index, is added to `zp_projects`, `zp_tickets`,
`zp_timesheets` and `zp_user`, and 8 more triggers (insert and update, one pair per table) keep it
current. This column exists because Leantime does not maintain its own `modified` column on every write
path — time logged from the weekly grid, for instance, leaves it untouched. Since the triggers sit in the
database, no write path can bypass them.

The column is written as UTC, and `modifiedAfter` filters on it.

The Leantime database user needs `ALTER` on `zp_projects`, `zp_tickets`, `zp_timesheets` and
`zp_user`, on top of the `CREATE` and `TRIGGER` the plugin already needed. Installation fails, and
says so, if the grant is missing.

NB! Install and update the plugin with the site down. The triggers are absent while the plugin is
being replaced, and an edit made in that window is not recoverable — installing only stamps rows that
have no timestamp at all, which covers new rows and nothing else.

NB! Installing stamps every existing row with the install time, so **the first sync after installing
returns everything once**.

NB! All 11 triggers are removed on uninstall, but the tables, the column and its data are left alone to
avoid data loss through install/uninstalls.

## Endpoints

The API consists of the following endpoints:

* Get list of entities
* Get list of deleted entities

### Get list of entities

GET/POST: `https://{{YOUR_DOMAIN}}/apidata/api/{{TYPE}}`

TYPE: projects, milestones, tickets, timesheets, users

Attach query/body parameters to the request:

* start: Starting id of the results.
* limit: Maximum number of results to get from start id in ascending order. Must be at least 1,
  and is capped at 1000. The limit that was actually applied is echoed in `parameters`.
* modifiedAfter: Only retrieve entries that have a modified later than modifiedAfter (unix timestamp).
  All five types, users included, carry a `modified` timestamp in the response.
* ids: Array of ids to retrieve. A comma separated string is also accepted, e.g. `?ids=1,2,3`.
* projectIds: Array of projectIds. Limits the entities to those attached to projects in projectIds.
  Only applies for types: milestone, tickets, timesheets.

Example request:

```shell
curl https://leantime.local.itkdev.dk/apidata/api/tickets
   -H "x-api-key: lt_1234567890"
   -H "Content-Type: application/json"
   -d '{"start":0,"limit":100,"modifiedAfter":1761051213,"ids":[1,2,3],"projectIds":[12,13,14]}'
```

### Get list of deleted entities

GET/POST: `https://{{YOUR_DOMAIN}}/apidata/api/deleted`

Attach query/body parameters to the request:

* type: The type to get deleted entities for: projects, milestones, tickets or timesheets.
  Required, and names exactly one type — a list is rejected, as is the `types` parameter this
  replaced. (`users` is not available: Leantime keeps no record of deleted users.)
* start: Starting deletionId of the results.
* limit: Maximum number of results to get from start deletionId in ascending order. Must be at
  least 1, and is capped at 1000. The limit that was actually applied is echoed in `parameters`.
* deletedAfter: Unix timestamp. Only retrieve ids of entities deleted after this timestamp.

Each result carries a `deletionId` alongside the deleted entity's `id`. Deletions are appended
in the order they happen, so `deletionId` — not `id` — is what the results are ordered and paged
on: request the next page with `start` set to the highest `deletionId` you got plus one, for as
long as `resultsCount` equals `parameters.limit`. Compare against the limit in the response, not
the one you sent: a request above the cap is answered with the capped limit, and a client that
compares against its own 5000 would stop at the first page.

NB! `start` is a watermark, not a gap-free cursor. A `deletionId` is assigned when the deletion is
written, but the row only becomes visible when its transaction commits, so a deletion can appear
below a `deletionId` you have already read past — and `deletedAfter`, stamped at the same moment,
moves with it. A consumer that must not miss a deletion should therefore not carry `start` from one
sync run to the next: begin each run at `start=0` with `deletedAfter` set a little before the
previous run started, and ignore the `deletionId`s it has already seen.

Example request:

```shell
curl https://leantime.local.itkdev.dk/apidata/api/deleted
   -H "x-api-key: lt_1234567890"
   -H "Content-Type: application/json"
   -d '{"type":"tickets","start":0,"limit":100,"deletedAfter":1759906882}'
```

## Errors

A parameter that cannot be interpreted answers `400` with the reason, e.g. a non numeric
`modifiedAfter`, a `limit` below 1, an id that is not a number, or a missing or unknown `type`:

```json
{"error": "modifiedAfter must be a whole number."}
```

## Development

The plugin has no long-running stack, so everything runs in a one-off
`itkdev/php8.3-fpm` container. Install [Task](https://taskfile.dev), then:

```shell
task setup
task test
task lint
```

Run `task --list-all` to see the remaining commands.

The `Dockerfile` exists only for releases: `bin/create-release` needs `rsync`,
which the base image does not carry. It backs the `php-release` compose service
and is not used for tests or linting.

Leantime core is not a Composer dependency of this plugin, so the development
dependencies stand in for it. `illuminate/database` and `nesbot/carbon` are
pinned to the exact versions the targeted Leantime release locks, so the tests
run against the code Leantime itself runs — currently v3.9.7, which runs PHP 8.3
and locks `laravel/framework v11.45.1` and `nesbot/carbon 3.10.1`. Bump those
pins and re-check `tests/Stub/` against Leantime's own `composer.lock` when
upgrading Leantime.

## API Key

To use the plugin you need an API key for leantime.

See <https://docs.leantime.io/api/usage?id=connect>.

The API should be set as a header for all requests to the API.

E.g.

```shell
curl https://{{YOUR_DOMAIN}}/apidata/api/{{TYPE}}
   -H "x-api-key: {{YOUR_APIKEY}}"
   -H "Content-Type: application/json"
   -d '{"start":0,"limit":100}'
```
