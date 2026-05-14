<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502175212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op: duplicate audit-column migration, intentionally skipped.';
    }

    public function up(Schema $schema): void
    {
        // Intentionally left blank.
    }

    public function down(Schema $schema): void
    {
        // Intentionally left blank.
    }
}
