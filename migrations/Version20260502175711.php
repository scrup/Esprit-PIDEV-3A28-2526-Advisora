<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502175711 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make created_by_id non-nullable on auth_session, notification, otp_code';
    }

    public function up(Schema $schema): void
    {
        // Disabled: This migration attempted to add created_by_id column which doesn't exist in the database
        // The BlameableTrait that created these columns was removed from entities as the database doesn't support them
    }

    public function down(Schema $schema): void
    {
        // Disabled: This migration attempted to modify created_by_id column which doesn't exist in the database
    }
}
