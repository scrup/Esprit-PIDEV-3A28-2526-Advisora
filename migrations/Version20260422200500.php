<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422200500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix notification primary key and field lengths.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prevue pour MySQL.'
        );

        $this->addSql('UPDATE notification SET spokenText = description WHERE spokenText IS NULL');
        $this->addSql('ALTER TABLE notification ADD id INT AUTO_INCREMENT NOT NULL PRIMARY KEY FIRST, CHANGE title title VARCHAR(255) NOT NULL, CHANGE isRead isRead TINYINT(1) NOT NULL DEFAULT 0, CHANGE spokenText spokenText LONGTEXT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prevue pour MySQL.'
        );

        $this->addSql('ALTER TABLE notification DROP PRIMARY KEY, DROP id, CHANGE title title VARCHAR(100) NOT NULL, CHANGE spokenText spokenText LONGTEXT DEFAULT NULL');
    }
}
