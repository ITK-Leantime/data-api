<?php

namespace Leantime\Plugins\APIData\Tests\Repository;

use Leantime\Plugins\APIData\Repositories\SchemaRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The schema statements are the one part of this plugin that cannot be tried out
 * against a test database, and getting them wrong breaks plugin installation on
 * production. These tests pin the properties the design depends on.
 */
#[CoversClass(SchemaRepository::class)]
final class SchemaRepositoryTest extends TestCase
{
    public function testTriggerNameDropsTheCoreTablePrefix(): void
    {
        $this->assertSame(
            'itk_data_api_timesheets_modified_insert',
            SchemaRepository::triggerName('zp_timesheets', 'insert'),
        );
        $this->assertSame(
            'itk_data_api_timesheets_modified_update',
            SchemaRepository::triggerName('zp_timesheets', 'update'),
        );
    }

    public function testATableGetsBothAnInsertAndAnUpdateTrigger(): void
    {
        $statements = SchemaRepository::triggerStatements('zp_timesheets');

        $this->assertCount(4, $statements);
        $this->assertStringStartsWith(
            'CREATE TRIGGER `itk_data_api_timesheets_modified_insert` BEFORE INSERT ON `zp_timesheets`',
            $statements[1],
        );
        $this->assertStringStartsWith(
            'CREATE TRIGGER `itk_data_api_timesheets_modified_update` BEFORE UPDATE ON `zp_timesheets`',
            $statements[3],
        );
    }

    /**
     * Installation is also migration — Leantime has no upgrade hook — so every
     * CREATE has to be preceded by the DROP that makes re-running it safe.
     */
    public function testEveryCreateTriggerIsPrecededByItsOwnDrop(): void
    {
        $statements = $this->allTriggerStatements();

        foreach ($statements as $index => $statement) {
            if (!str_starts_with($statement, 'CREATE TRIGGER')) {
                continue;
            }

            $this->assertSame(
                sprintf('DROP TRIGGER IF EXISTS `%s`', $this->triggerNameIn($statement)),
                $statements[$index - 1] ?? null,
            );
        }
    }

    /**
     * The whole point of owning the column is that its values have one
     * unambiguous meaning. NOW() would write the session timezone's clock.
     */
    public function testTimestampsAreWrittenInUtc(): void
    {
        $statements = $this->allTriggerStatements();

        foreach (SchemaRepository::TRACKED_TABLES as $table) {
            $statements[] = SchemaRepository::backfillStatement($table);
        }

        foreach ($statements as $statement) {
            $this->assertStringNotContainsStringIgnoringCase('NOW()', $statement);
        }

        foreach ($statements as $statement) {
            if (str_starts_with($statement, 'DROP TRIGGER')) {
                continue;
            }

            $this->assertStringContainsString('UTC_TIMESTAMP()', $statement);
        }
    }

    /**
     * The deletion tables' `DEFAULT NOW()` writes the session timezone's clock,
     * but `/deleted` parses `dateDeleted` as UTC. The trigger sets the column
     * itself so the two agree.
     */
    public function testDeletionsAreStampedInUtcRatherThanLeftToTheColumnDefault(): void
    {
        $creates = array_values(array_filter(
            SchemaRepository::deleteTriggerStatements(),
            static fn (string $statement) => str_starts_with($statement, 'CREATE TRIGGER'),
        ));

        $this->assertCount(3, $creates);

        foreach ($creates as $statement) {
            $this->assertStringContainsString('dateDeleted', $statement);
            $this->assertStringContainsString('UTC_TIMESTAMP()', $statement);
        }

        $this->assertStringContainsString(
            'INSERT INTO `itk_tickets_deleted`(entryId, type, dateDeleted) VALUES (OLD.id, OLD.type, UTC_TIMESTAMP())',
            $creates[1],
        );
    }

    /**
     * Production is MySQL 8.4. `CREATE OR REPLACE TRIGGER` and
     * `ADD COLUMN IF NOT EXISTS` are MariaDB-only — an earlier attempt at this
     * feature was rejected for using them.
     */
    public function testNoStatementUsesMariaDbOnlySyntax(): void
    {
        foreach ($this->allInstallStatements() as $statement) {
            $this->assertStringNotContainsStringIgnoringCase('CREATE OR REPLACE', $statement);
            $this->assertStringNotContainsStringIgnoringCase('ADD COLUMN IF NOT EXISTS', $statement);
        }
    }

    /**
     * Statements are executed one at a time, so a semicolon inside a trigger
     * body would be read as the end of the CREATE.
     */
    public function testNoTriggerBodyContainsASemicolon(): void
    {
        foreach ($this->allTriggerStatements() as $statement) {
            $this->assertStringNotContainsString(';', $statement);
        }
    }

    #[DataProvider('trackedTables')]
    public function testEveryTrackedTableGetsTheFullSchema(string $table): void
    {
        $this->assertStringContainsString(
            sprintf('ALTER TABLE `%s` ADD COLUMN `%s`', $table, SchemaRepository::COLUMN),
            SchemaRepository::addColumnStatement($table),
        );
        $this->assertStringContainsString(
            sprintf('ADD INDEX `%s` (`%s`)', SchemaRepository::INDEX, SchemaRepository::COLUMN),
            SchemaRepository::addIndexStatement($table),
        );
        $this->assertStringContainsString(
            sprintf('UPDATE `%s`', $table),
            SchemaRepository::backfillStatement($table),
        );
        $this->assertCount(4, SchemaRepository::triggerStatements($table));
    }

    /**
     * The order the install sequence runs in is the whole argument for it: the
     * triggers have to be in place before the backfill, or a row written between
     * the two is missed by an UPDATE that has already passed it, and the index has
     * to come after it, or it is built over an all-NULL column and then rewritten
     * entry by entry.
     */
    public function testTheInstallSequenceStampsBeforeItIndexes(): void
    {
        $statements = SchemaRepository::installStatements('zp_timesheets', false, false);

        $this->assertSame(
            [
                'ALTER TABLE `zp_timesheets` ADD COLUMN',
                'DROP TRIGGER IF EXISTS',
                'CREATE TRIGGER',
                'DROP TRIGGER IF EXISTS',
                'CREATE TRIGGER',
                'UPDATE `zp_timesheets`',
                'ALTER TABLE `zp_timesheets` ADD INDEX',
            ],
            array_map($this->kindOf(...), $statements),
        );
    }

    /**
     * Installation is also migration, so the second install has to skip what the
     * first one created — but still re-create the triggers and re-run the backfill,
     * which are the parts that heal a partial install.
     */
    #[DataProvider('columnAndIndexStates')]
    public function testAlreadyInstalledSchemaIsNotAddedTwice(bool $hasColumn, bool $hasIndex): void
    {
        $statements = SchemaRepository::installStatements('zp_timesheets', $hasColumn, $hasIndex);

        $this->assertSame(!$hasColumn, in_array('ALTER TABLE `zp_timesheets` ADD COLUMN', array_map($this->kindOf(...), $statements), true));
        $this->assertSame(!$hasIndex, in_array('ALTER TABLE `zp_timesheets` ADD INDEX', array_map($this->kindOf(...), $statements), true));
        $this->assertContains(SchemaRepository::backfillStatement('zp_timesheets'), $statements);

        foreach (SchemaRepository::triggerStatements('zp_timesheets') as $statement) {
            $this->assertContains($statement, $statements);
        }
    }

    /**
     * @return list<array{bool, bool}>
     */
    public static function columnAndIndexStates(): array
    {
        return [[false, false], [true, false], [false, true], [true, true]];
    }

    /**
     * `CREATE TABLE IF NOT EXISTS` never reaches a database that already has the
     * table, so a change expressed there alone would only ever apply to fresh
     * installs. Every deleted table has to be carried by a statement both
     * populations run.
     */
    public function testEveryDeletedTableColumnDefaultIsDroppedOnExistingInstallsToo(): void
    {
        $alters = SchemaRepository::deletedTableAlterStatements();

        $this->assertCount(3, SchemaRepository::deletedTables());

        foreach (SchemaRepository::deletedTables() as $table) {
            $this->assertContains(
                sprintf('ALTER TABLE `%s` ALTER COLUMN `dateDeleted` DROP DEFAULT', $table),
                $alters,
            );

            $this->assertStringContainsString(
                sprintf('CREATE TABLE IF NOT EXISTS `%s`', $table),
                implode("\n", SchemaRepository::deletedTableStatements()),
            );
        }
    }

    /**
     * The default writes the session timezone's clock into a column `/deleted`
     * parses as UTC. The triggers make it unreachable, but the tables outlive an
     * uninstall and the triggers do not.
     */
    public function testNoDeletedTableKeepsALocalTimeDefault(): void
    {
        foreach (SchemaRepository::deletedTableStatements() as $statement) {
            if (!str_contains($statement, 'DEFAULT NOW()')) {
                continue;
            }

            preg_match('/CREATE TABLE IF NOT EXISTS `([^`]+)`/', $statement, $matches);

            $this->assertContains(
                sprintf('ALTER TABLE `%s` ALTER COLUMN `dateDeleted` DROP DEFAULT', $matches[1] ?? ''),
                SchemaRepository::deletedTableAlterStatements(),
            );
        }
    }

    /**
     * Display width, deprecated since MySQL 8.0.17. Editing it in place is safe
     * precisely because `int(11)` and `int` are the same column — the one kind of
     * change to the frozen CREATEs that cannot make the two populations differ.
     */
    public function testTheDeletedTablesDoNotDeclareADisplayWidth(): void
    {
        foreach (SchemaRepository::deletedTableStatements() as $statement) {
            $this->assertStringNotContainsString('int(11)', $statement);
        }
    }

    /**
     * Only rows the triggers never saw are stamped, so re-installing does not
     * rewrite timestamps and force consumers through a second full resync.
     */
    public function testTheBackfillOnlyTouchesRowsWithoutATimestamp(): void
    {
        $this->assertStringEndsWith(
            sprintf('WHERE `%s` IS NULL', SchemaRepository::COLUMN),
            SchemaRepository::backfillStatement('zp_timesheets'),
        );
    }

    /**
     * Eight modified triggers plus the three deletion triggers.
     */
    public function testUninstallDropsEveryTriggerThePluginOwns(): void
    {
        $statements = SchemaRepository::uninstallStatements();

        $this->assertCount(11, $statements);
        $this->assertSame($statements, array_unique($statements));

        foreach ($statements as $statement) {
            $this->assertStringStartsWith('DROP TRIGGER IF EXISTS ', $statement);
        }

        foreach ($this->allTriggerStatements() as $statement) {
            if (!str_starts_with($statement, 'CREATE TRIGGER')) {
                continue;
            }

            $this->assertContains(
                sprintf('DROP TRIGGER IF EXISTS `%s`', $this->triggerNameIn($statement)),
                $statements,
            );
        }
    }

    /**
     * @return list<array{string}>
     */
    public static function trackedTables(): array
    {
        return array_map(static fn (string $table) => [$table], SchemaRepository::TRACKED_TABLES);
    }

    /**
     * @return list<string>
     */
    private function allTriggerStatements(): array
    {
        $statements = SchemaRepository::deleteTriggerStatements();

        foreach (SchemaRepository::TRACKED_TABLES as $table) {
            array_push($statements, ...SchemaRepository::triggerStatements($table));
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    private function allInstallStatements(): array
    {
        $statements = array_merge(
            SchemaRepository::deletedTableStatements(),
            SchemaRepository::deletedTableAlterStatements(),
            SchemaRepository::deleteTriggerStatements(),
        );

        foreach (SchemaRepository::TRACKED_TABLES as $table) {
            array_push($statements, ...SchemaRepository::installStatements($table, false, false));
        }

        return $statements;
    }

    private function triggerNameIn(string $statement): string
    {
        preg_match('/^CREATE TRIGGER `([^`]+)`/', $statement, $matches);

        return $matches[1] ?? '';
    }

    /**
     * A statement reduced to its opening clause, so an ordering assertion reads as
     * a sequence rather than as five statements the other tests already pin.
     */
    private function kindOf(string $statement): string
    {
        if (str_starts_with($statement, 'DROP TRIGGER IF EXISTS')) {
            return 'DROP TRIGGER IF EXISTS';
        }

        if (str_starts_with($statement, 'CREATE TRIGGER')) {
            return 'CREATE TRIGGER';
        }

        preg_match('/^(ALTER TABLE `[^`]+` ADD (?:COLUMN|INDEX)|UPDATE `[^`]+`)/', $statement, $matches);

        return $matches[1] ?? $statement;
    }
}
