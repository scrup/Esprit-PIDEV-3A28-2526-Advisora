<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419163303 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ext_translations table for translatable storage.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prévue pour MySQL.'
        );

        $this->addSql("
            CREATE TABLE IF NOT EXISTS ext_translations (
                id INT AUTO_INCREMENT NOT NULL,
                locale VARCHAR(8) NOT NULL,
                object_class VARCHAR(191) NOT NULL,
                field VARCHAR(32) NOT NULL,
                foreign_key VARCHAR(64) NOT NULL,
                content LONGTEXT DEFAULT NULL,
                UNIQUE INDEX lookup_unique_idx (foreign_key, locale, object_class, field),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
              COLLATE utf8mb4_unicode_ci
              ENGINE = InnoDB
              ROW_FORMAT = DYNAMIC
        ");
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prévue pour MySQL.'
        );

        $this->addSql('DROP TABLE IF EXISTS ext_translations');
    }
}