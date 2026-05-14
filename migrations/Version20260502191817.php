<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502191817 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op: superseded by later table-rename and FK normalization migrations.';
    }

    public function up(Schema $schema): void
    {
        // Intentionally left blank.
        // This migration was generated from an intermediate schema state and
        // is superseded by subsequent normalized migrations.
    }

    public function down(Schema $schema): void
    {
        // Intentionally left blank.
    }
}
