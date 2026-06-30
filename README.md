# Data API plugin

An API plugin for exposing data to external applications.

Copy the plugin to the folder app/Plugins/APIData, install and enable.

During installation the following tables will be created to tracked deleted entities:

* itk_projects_deleted
* itk_tickets_deleted
* itk_timesheets_deleted

3 triggers will also be installed that populate the tables when entities are deleted.

NB! The triggers are removed on uninstall, but the tables are left alone to avoid data loss through install/uninstalls.

## Endpoints

The API consists of the following endpoints:

* Get list of entities
* Get list of deleted entities
* Get timesheet hour totals

### Get list of entities

GET/POST: `https://{{YOUR_DOMAIN}}/apidata/api/{{TYPE}}`

TYPE: projects, milestones, tickets, timesheets

Attach query/body parameters to the request:

* start: Starting id of the results.
* limit: Maximum number of results to get from start id in ascending order.
* modifiedAfter: Only retrieve entries that have a modified later than modifiedAfter (unix timestamp).
* ids: Array of ids to retrieve.
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

* types: Array of types to get deleted entities for: projects, milestones, tickets, timesheets.
* deleted: Unix timestamp. Only retrieve ids of entities deleted after this timestamp.

Example request:

```shell
curl https://leantime.local.itkdev.dk/apidata/api/deleted
   -H "x-api-key: lt_1234567890"
   -H "Content-Type: application/json"
   -d '{"deleted":1759906882,"types":["projects","milestones","tickets","timesheets"]}'
```

### Get timesheet hour totals

GET/POST: `https://{{YOUR_DOMAIN}}/apidata/api/timesheetTotals`

Returns the sum of logged hours grouped by day or week. Use it as a sanity check: sum the
synced hours per period yourself and compare to the totals to detect drift.

Attach query/body parameters to the request:

* groupBy: day (default) or week. Weeks are ISO-8601 (Monday based).
* from: Unix timestamp. Only include entries with a workDate later than or equal to from.
* to: Unix timestamp. Only include entries with a workDate earlier than or equal to to.
* projectIds: Array of projectIds. Limits the totals to entities attached to projects in projectIds.

The same filters as the timesheets endpoint are applied (entries without hours are excluded),
so the totals reconcile against the synced timesheets. Each result has a period (`YYYY-MM-DD`
for days, `YYYY-Www` for weeks), the summed hours (rounded to 2 decimals) and the entry count.

Example request:

```shell
curl https://leantime.local.itkdev.dk/apidata/api/timesheetTotals
   -H "x-api-key: lt_1234567890"
   -H "Content-Type: application/json"
   -d '{"groupBy":"week","from":1700000000,"projectIds":[12,13,14]}'
```

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
