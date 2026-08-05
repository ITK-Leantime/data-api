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
        $this->execute(...self::deleteTriggerStatements());

        foreach (self::TRACKED_TABLES as $table) {
            if (!$this->hasColumn($table, self::COLUMN)) {
                $this->execute(self::addColumnStatement($table));
            }

            if (!$this->hasIndex($table, self::INDEX)) {
                $this->execute(self::addIndexStatement($table));
            }

            // Triggers first, so rows written between here and the backfill are
            // stamped by the trigger rather than left behind.
            $this->execute(...self::triggerStatements($table));
            $this->execute(self::backfillStatement($table));
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
     * Runs on every install, not just the first. On an existing install it is a
     * no-op except for rows written while the plugin was uninstalled and the
     * triggers were gone — which it heals.
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
     * Left exactly as they were first shipped: IF NOT EXISTS makes these no-ops
     * on every existing install, so changing them here would only make fresh
     * databases differ. The `DEFAULT NOW()` on `dateDeleted` is unreachable now
     * that the delete triggers set the column themselves.
     *
     * @return list<string>
     */
    public static function deletedTableStatements(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS `itk_projects_deleted` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `entryId` int(11) DEFAULT NULL,
                `dateDeleted` datetime DEFAULT NOW(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'CREATE TABLE IF NOT EXISTS `itk_tickets_deleted` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `entryId` int(11) DEFAULT NULL,
                `type` varchar(255) DEFAULT NULL,
                `dateDeleted` datetime DEFAULT NOW(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'CREATE TABLE IF NOT EXISTS `itk_timesheets_deleted` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `entryId` int(11) DEFAULT NULL,
                `dateDeleted` datetime DEFAULT NOW(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];
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
