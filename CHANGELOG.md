# Changelog

## [Unreleased]

* [PR-21](https://github.com/ITK-Leantime/data-api/pull/21)
  * Paginated the deleted endpoint with `start` and `limit`, so a response is bounded like the entity endpoints instead of carrying the whole deletion history.
  * Added `deletionId` to the deleted results — the deletion's own id, which the results are ordered and paged on, as opposed to `id`, which is the deleted entity's.
  * Replaced `types` with a required singular `type` on the deleted endpoint, since a request now answers with one type's page; `results` is a flat list rather than an object keyed by type.
  * Renamed the deleted endpoint's `deleted` parameter to `deletedAfter`, matching `modifiedAfter` on the entity endpoints. The old name answers 400 rather than being ignored, which would have returned the whole deletion history to a caller that believes it asked for a window.
  * Answered the retired `types` parameter with a 400 naming `type`, instead of the generic "type is required" a caller sending it would otherwise get.
  * Documented paging against the limit in the response rather than the one requested, since a limit above the cap is answered with the cap, and documented that `start` is a watermark rather than a gap-free cursor across sync runs.
* [PR-20](https://github.com/ITK-Leantime/data-api/pull/20)
  * Added a plugin owned `itk_data_api_modified` column, maintained by database triggers, on projects, tickets, timesheets and users, so no write path can leave the sync watermark behind.
  * Changed `modifiedAfter` to filter on that column, so edits to existing tickets and milestones are no longer missed and time logged from the weekly grid is picked up.
  * Added `modified` to the users endpoint.
  * Changed the deletion triggers to stamp `dateDeleted` in UTC, so `deleted` filters against the same clock the responses are read in.
  * Moved the schema handling into a SchemaRepository, executing one statement at a time so installation reports failures instead of swallowing them, and made installing idempotent.
  * Dropped the `dateDeleted` default on the deletion tables, so a row inserted without a trigger in place is left null rather than stamped with the server's local time.
* [PR-19](https://github.com/ITK-Leantime/data-api/pull/19)
  * Validated request parameters, so malformed input answers 400 with a reason instead of failing with a 500.
  * Rejected a limit below 1, which previously dropped the LIMIT clause and returned every row, and capped limit at 1000.
  * Accepted comma separated ids, projectIds and types, since the endpoints are documented as GET with query parameters.
  * Required types on the deleted endpoint, so a bare request answers 400 instead of returning every deleted id ever recorded, and stopped an unknown type reaching the error page.
  * Fixed an empty projectIds list dropping the filter, which answered with every row instead of none.
  * Trimmed whitespace around ids, projectIds and types elements sent in array form.
  * Renamed InvalidRequestException to BadRequestException, matching the 400 it turns into.
* [PR-18](https://github.com/ITK-Leantime/data-api/pull/18)
  * Allowed null values in API models, so entries referencing deleted users or deleted tickets no longer fail the whole request.
  * Added userId to timesheets, so hours logged by a deleted user stay attributable.
  * Stopped resolving ticket status against the session's project when a ticket has no project.
  * Allowed a missing worker name, and stopped returning a whitespace-only name for a worker without one.
  * Looked up ticket status labels once per project instead of once per ticket.
  * Pinned the development dependencies to the Leantime release the plugin targets.
  * Added PHPUnit test setup and a Taskfile for running it, and ran the tests in the project's Docker Compose stack on pull requests.

## [0.1.2] - 2026-03-06

* [PR-12](https://github.com/ITK-Leantime/data-api/pull/12)
  * Excluded timesheets where hours is null.

## [0.1.1] - 2026-01-08

* [PR-8](https://github.com/ITK-Leantime/data-api/pull/8)
  * Exclude api users from users endpoint

## [0.1.0] - 2025-12-04

* [PR-5](https://github.com/ITK-Leantime/data-api/pull/5)
  * Added users endpoint

## [0.0.2] - 2025-11-21

* [PR-3](https://github.com/ITK-Leantime/data-api/pull/3)
 * Added release bin scripts

## [0.0.1] - 2025-11-21
* [PR-1](https://github.com/ITK-Leantime/data-api/pull/1)
 * Initial plugin
* [PR-2](https://github.com/ITK-Leantime/data-api/pull/2)
 * Initial release

[Unreleased]: https://github.com/ITK-Leantime/data-api/compare/0.1.2...HEAD
[0.1.2]: https://github.com/ITK-Leantime/data-api/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/ITK-Leantime/data-api/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/ITK-Leantime/data-api/compare/0.0.2...0.1.0
[0.0.2]: https://github.com/ITK-Leantime/data-api/compare/0.0.1...0.0.2
[0.0.1]: https://github.com/ITK-Leantime/data-api/releases/tag/0.0.1
