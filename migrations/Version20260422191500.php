<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422191500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rework notification table for per-user notification center.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prevue pour MySQL.'
        );

        $this->addSql('ALTER TABLE notification ADD recipient_id INT DEFAULT NULL, ADD eventType VARCHAR(50) NOT NULL DEFAULT \'project_created\', ADD spokenText LONGTEXT DEFAULT NULL, ADD createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('UPDATE notification SET createdAt = IF(dateNotification IS NOT NULL, CAST(CONCAT(dateNotification, \' 00:00:00\') AS DATETIME), NOW()), spokenText = description WHERE spokenText IS NULL');
        $this->addSql('ALTER TABLE notification CHANGE description description LONGTEXT NOT NULL, DROP dateNotification, DROP target_role');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_RECIPIENT FOREIGN KEY (recipient_id) REFERENCES user (idUser) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_NOTIFICATION_RECIPIENT ON notification (recipient_id)');
        $this->addSql('CREATE INDEX IDX_NOTIFICATION_READ ON notification (isRead)');
        $this->addSql('CREATE INDEX IDX_NOTIFICATION_CREATED_AT ON notification (createdAt)');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prevue pour MySQL.'
        );

        $this->addSql('DROP INDEX IDX_NOTIFICATION_CREATED_AT ON notification');
        $this->addSql('DROP INDEX IDX_NOTIFICATION_READ ON notification');
        $this->addSql('DROP INDEX IDX_NOTIFICATION_RECIPIENT ON notification');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_NOTIFICATION_RECIPIENT');
        $this->addSql('ALTER TABLE notification ADD dateNotification DATE DEFAULT NULL, ADD target_role VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE notification SET dateNotification = DATE(createdAt)');
        $this->addSql('ALTER TABLE notification CHANGE description description VARCHAR(255) NOT NULL, DROP recipient_id, DROP eventType, DROP spokenText, DROP createdAt');
    }
}
