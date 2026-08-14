<?php

namespace Leantime\Plugins\APIData\Repositories;

use Illuminate\Database\Query\Builder;

/**
 * The plugin's own database schema: the deletion tracking tables and the
 * `itk_data_api_modified` timestamp column.
 *
 * Leantime core does not reliably maintain its own `modified` column — timesheet
 * writes going through `ON DUPLICATE KEY UPDATE` leave it untouched — so the
 * plugin keeps its own timestamp, maintained by triggers rather than by any PHP
 * write path. Nothing can bypass a trigger, not core, not this plugin, not a
 * manual UPDATE.
 *
 * The pure statement builders are static so the SQL can be unit tested without a
 * database. Two constraints they exist to protect:
 *
 * - Production is MySQL 8.4, so no `CREATE OR REPLACE TRIGGER` and no
 *   `ADD COLUMN IF NOT EXISTS` — both are MariaDB-only.
 * - Every timestamp is `UTC_TIMESTAMP()`, never `NOW()`, which would write the
 *   session timezone's clock and reintroduce the ambiguity we are fixing.
 *
 * Installing and updating the plugin happens with the site down. That precondition
 * is load-bearing: while the plugin is being replaced its triggers are gone, and
 * an edit landing in that window is lost for good. The backfill only stamps rows
 * that have no timestamp at all, so it recovers inserts and nothing else.
 */
class SchemaRepository
{
    public const COLUMN = 'itk_data_api_modified';
    public const INDEX = 'itk_data_api_modified_idx';

    /** The core tables that get a plugin-maintained modified timestamp. */
    public const TRACKED_TABLES = ['zp_projects', 'zp_tickets', 'zp_timesheets', 'zp_user'];

    /** Trigger timing per event, in the order install() creates them. */
    private const EVENTS = ['insert' => 'BEFORE INSERT', 'update' => 'BEFORE UPDATE'];

    /** Deletion tracking, keyed by trigger name: [source table, target table, columns, values]. */
    private const DELETE_TRIGGERS = [
        'itk_projects_deleted_trigger' => ['zp_projects', 'itk_projects_deleted', 'entryId', 'OLD.id'],
        'itk_tickets_deleted_trigger' => ['zp_tickets', 'itk_tickets_deleted', 'entryId, type', 'OLD.id, OLD.type'],
        'itk_timesheets_deleted_trigger' => ['zp_timesheets', 'itk_timesheets_deleted', 'entryId', 'OLD.id'],
    ];

    public function install(): void
    {
        $this->execute(...self::deletedTableStatements());
        $this->execute(...self::deletedTableAlterStatements());
        $this->execute(...self::deleteTriggerStatements());

        foreach (self::TRACKED_TABLES as $table) {
            $this->execute(...self::installStatements(
                $table,
                $this->hasColumn($table, self::COLUMN),
                $this->hasIndex($table, self::INDEX),
            ));
        }
    }

    public function uninstall(): void
    {
        // Columns, indexes and the itk_*_deleted tables are deliberately kept:
        // a reinstall then preserves timestamp history instead of forcing every
        // consumer through another full resync.
        $this->execute(...self::uninstallStatements());
    }

    /**
     * The per-table install sequence, in the order it has to run.
     *
     * The triggers precede the backfill so a row written between the two is
     * stamped by the trigger rather than missed by an UPDATE that has already
     * passed it. The index goes last: built any earlier it would index an
     * all-NULL column and then have every entry rewritten by the backfill.
     *
     * The column and index are guarded because installing is also migrating —
     * Leantime has no upgrade hook, so this runs again on every install.
     *
     * @return list<string>
     */
    public static function installStatements(string $table, bool $hasColumn, bool $hasIndex): array
    {
        $statements = [];

        if (!$hasColumn) {
            $statements[] = self::addColumnStatement($table);
        }

        array_push($statements, ...self::triggerStatements($table));
        $statements[] = self::backfillStatement($table);

        if (!$hasIndex) {
            $statements[] = self::addIndexStatement($table);
        }

        return $statements;
    }

    /**
     * `zp_timesheets` + `insert` → `itk_data_api_timesheets_modified_insert`.
     */
    public static function triggerName(string $table, string $event): string
    {
        $shortName = str_starts_with($table, 'zp_') ? substr($table, 3) : $table;

        return sprintf('itk_data_api_%s_modified_%s', $shortName, $event);
    }

    public static function addColumnStatement(string $table): string
    {
        return sprintf('ALTER TABLE `%s` ADD COLUMN `%s` DATETIME NULL DEFAULT NULL', $table, self::COLUMN);
    }

    public static function addIndexStatement(string $table): string
    {
        return sprintf('ALTER TABLE `%s` ADD INDEX `%s` (`%s`)', $table, self::INDEX, self::COLUMN);
    }

    /**
     * Stamps the rows the triggers never saw. Runs on every install, not just the
     * first, but `IS NULL` keeps it from rewriting timestamps a previous install
     * already set and forcing consumers through a second full resync.
     */
    public static function backfillStatement(string $table): string
    {
        return sprintf(
            'UPDATE `%s` SET `%s` = UTC_TIMESTAMP() WHERE `%s` IS NULL',
            $table,
            self::COLUMN,
            self::COLUMN,
        );
    }

    /**
     * @return list<string> A DROP IF EXISTS immediately followed by its CREATE, per event.
     */
    public static function triggerStatements(string $table): array
    {
        $statements = [];

        foreach (self::EVENTS as $event => $timing) {
            $name = self::triggerName($table, $event);

            $statements[] = self::dropTriggerStatement($name);
            // A single-statement body, so it needs no BEGIN … END and contains
            // no semicolon — which is what lets the statements be executed one
            // at a time instead of as one multi-statement string.
            $statements[] = sprintf(
                'CREATE TRIGGER `%s` %s ON `%s` FOR EACH ROW SET NEW.%s = UTC_TIMESTAMP()',
                $name,
                $timing,
                $table,
                self::COLUMN,
            );
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    public static function dropTriggerStatements(string $table): array
    {
        return array_map(
            static fn (string $event) => self::dropTriggerStatement(self::triggerName($table, $event)),
            array_keys(self::EVENTS),
        );
    }

    /**
     * Every trigger the plugin owns, modified and deleted alike.
     *
     * @return list<string>
     */
    public static function uninstallStatements(): array
    {
        $statements = array_map(self::dropTriggerStatement(...), array_keys(self::DELETE_TRIGGERS));

        foreach (self::TRACKED_TABLES as $table) {
            array_push($statements, ...self::dropTriggerStatements($table));
        }

        return $statements;
    }

    /**
     * The historical baseline, frozen. `CREATE TABLE IF NOT EXISTS` is a bootstrap
     * rather than a declaration: a database that already has the table ignores it
     * forever, so editing a body here only changes what fresh installs get and lets
     * the two populations drift apart silently. Every later change belongs in
     * `deletedTableAlterStatements()`, which both populations run.
     *
     * The one exception is a change that produces an identical column either way —
     * `int(11)` lost its display width here, since `int(11)` and `int` are the same
     * column and only the former emits an 8.0.17 deprecation.
     *
     * @return list<string>
     */
    public static function deletedTableStatements(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS `itk_projects_deleted` (
                `id` int NOT NULL AUTO_INCREMENT,
                `entryId` int DEFAULT NULL,
                `dateDeleted` datetime DEFAULT NOW(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'CREATE TABLE IF NOT EXISTS `itk_tickets_deleted` (
                `id` int NOT NULL AUTO_INCREMENT,
                `entryId` int DEFAULT NULL,
                `type` varchar(255) DEFAULT NULL,
                `dateDeleted` datetime DEFAULT NOW(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'CREATE TABLE IF NOT EXISTS `itk_timesheets_deleted` (
                `id` int NOT NULL AUTO_INCREMENT,
                `entryId` int DEFAULT NULL,
                `dateDeleted` datetime DEFAULT NOW(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];
    }

    /**
     * Changes to the deleted tables made after their original CREATE, expressed so
     * that existing databases converge on what a fresh install gets. Re-running one
     * has to be safe; `hasColumn()`/`hasIndex()` are there for the changes that
     * cannot express that in SQL alone.
     *
     * Dropping `dateDeleted`'s default is the first of them. The delete triggers set
     * the column themselves, so `DEFAULT NOW()` is unreachable while they exist —
     * but the tables outlive an uninstall and the triggers do not. Without the
     * default, a row inserted with no trigger in place gets a NULL instead of the
     * session timezone's clock in a column the read side parses as UTC.
     *
     * @return list<string>
     */
    public static function deletedTableAlterStatements(): array
    {
        return array_map(
            static fn (string $table) => sprintf(
                'ALTER TABLE `%s` ALTER COLUMN `dateDeleted` DROP DEFAULT',
                $table,
            ),
            self::deletedTables(),
        );
    }

    /**
     * @return list<string>
     */
    public static function deletedTables(): array
    {
        return array_values(array_unique(array_column(self::DELETE_TRIGGERS, 1)));
    }

    /**
     * Unchanged in name, but re-expressed as single-statement bodies behind a
     * DROP IF EXISTS. Reinstalling used to throw here because CREATE TRIGGER hit
     * the triggers the previous install left behind.
     *
     * `dateDeleted` is now set explicitly rather than left to the column's
     * `DEFAULT NOW()`, which wrote the session timezone's clock while the read
     * side parses the value as UTC.
     *
     * @return list<string>
     */
    public static function deleteTriggerStatements(): array
    {
        $statements = [];

        foreach (self::DELETE_TRIGGERS as $name => [$source, $target, $columns, $values]) {
            $statements[] = self::dropTriggerStatement($name);
            $statements[] = sprintf(
                'CREATE TRIGGER `%s` AFTER DELETE ON `%s` FOR EACH ROW'
                . ' INSERT INTO `%s`(%s, dateDeleted) VALUES (%s, UTC_TIMESTAMP())',
                $name,
                $source,
                $target,
                $columns,
                $values,
            );
        }

        return $statements;
    }

    private static function dropTriggerStatement(string $name): string
    {
        return sprintf('DROP TRIGGER IF EXISTS `%s`', $name);
    }

    private function execute(string ...$statements): void
    {
        $pdo = app('db')->connection()->getPdo();

        foreach ($statements as $statement) {
            // Laravel sets ERRMODE_EXCEPTION so exec() throws, but guard the
            // false return in case the connection is configured differently.
            if ($pdo->exec($statement) === false) {
                [, $code, $message] = $pdo->errorInfo();

                throw new \RuntimeException(sprintf('Schema statement failed (%s): %s', $code, $message));
            }
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->query()
            ->from('information_schema.COLUMNS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', '=', $table)
            ->where('COLUMN_NAME', '=', $column)
            ->exists();
    }

    private function hasIndex(string $table, string $index): bool
    {
        return $this->query()
            ->from('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', '=', $table)
            ->where('INDEX_NAME', '=', $index)
            ->exists();
    }

    private function query(): Builder
    {
        return app('db')->connection()->query();
    }
}
