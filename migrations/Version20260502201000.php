<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502201000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename plural business tables to singular names.';
    }

    public function up(Schema $schema): void
    {
        $this->renameTableIfNeeded('bookings', 'booking');
        $this->renameTableIfNeeded('decisions', 'decision');
        $this->renameTableIfNeeded('events', 'event');
        $this->renameTableIfNeeded('investments', 'investment');
        $this->renameTableIfNeeded('objectives', 'objective');
        $this->renameTableIfNeeded('projects', 'project');
        $this->renameTableIfNeeded('resources', 'resource');
        $this->renameTableIfNeeded('strategies', 'strategy');
    }

    public function down(Schema $schema): void
    {
        $this->renameTableIfNeeded('strategy', 'strategies');
        $this->renameTableIfNeeded('resource', 'resources');
        $this->renameTableIfNeeded('project', 'projects');
        $this->renameTableIfNeeded('objective', 'objectives');
        $this->renameTableIfNeeded('investment', 'investments');
        $this->renameTableIfNeeded('event', 'events');
        $this->renameTableIfNeeded('decision', 'decisions');
        $this->renameTableIfNeeded('booking', 'bookings');
    }

    private function renameTableIfNeeded(string $from, string $to): void
    {
        $schemaManager = method_exists($this->connection, 'createSchemaManager')
            ? $this->connection->createSchemaManager()
            : $this->connection->getSchemaManager();

        $tables = array_map('strtolower', $schemaManager->listTableNames());

        if (in_array(strtolower($from), $tables, true) && !in_array(strtolower($to), $tables, true)) {
            $this->addSql(sprintf('RENAME TABLE `%s` TO `%s`', $from, $to));
        }
    }
}

