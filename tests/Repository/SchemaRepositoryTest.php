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
            $this->allTriggerStatements(),
        );

        foreach (SchemaRepository::TRACKED_TABLES as $table) {
            $statements[] = SchemaRepository::addColumnStatement($table);
            $statements[] = SchemaRepository::addIndexStatement($table);
            $statements[] = SchemaRepository::backfillStatement($table);
        }

        return $statements;
    }

    private function triggerNameIn(string $statement): string
    {
        preg_match('/^CREATE TRIGGER `([^`]+)`/', $statement, $matches);

        return $matches[1] ?? '';
    }
}
