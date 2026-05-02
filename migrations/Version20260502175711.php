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
        $fallbackUserId = $this->connection->fetchOne(
            "SELECT idUser FROM `user` WHERE roleUser = 'admin' ORDER BY idUser ASC LIMIT 1"
        );
        if ($fallbackUserId === false || $fallbackUserId === null) {
            $fallbackUserId = $this->connection->fetchOne(
                "SELECT idUser FROM `user` ORDER BY idUser ASC LIMIT 1"
            );
        }

        if ($fallbackUserId === false || $fallbackUserId === null) {
            throw new \RuntimeException('Cannot enforce NOT NULL on created_by_id: no user exists to backfill data.');
        }

        $fallbackUserId = (int) $fallbackUserId;

        $this->addSql('UPDATE auth_session SET created_by_id = ? WHERE created_by_id IS NULL', [$fallbackUserId]);
        $this->addSql('UPDATE notification SET created_by_id = ? WHERE created_by_id IS NULL', [$fallbackUserId]);
        $this->addSql('UPDATE otp_code SET created_by_id = ? WHERE created_by_id IS NULL', [$fallbackUserId]);

        $this->addSql('ALTER TABLE auth_session DROP FOREIGN KEY `FK_9E60F527B03A8386`');
        $this->addSql('ALTER TABLE auth_session CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE auth_session ADD CONSTRAINT FK_9E60F527B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY `FK_BF5476CAB03A8386`');
        $this->addSql('ALTER TABLE notification CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE otp_code DROP FOREIGN KEY `FK_93FE2319B03A8386`');
        $this->addSql('ALTER TABLE otp_code CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE otp_code ADD CONSTRAINT FK_93FE2319B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE auth_session DROP FOREIGN KEY FK_9E60F527B03A8386');
        $this->addSql('ALTER TABLE auth_session CHANGE created_by_id created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE auth_session ADD CONSTRAINT `FK_9E60F527B03A8386` FOREIGN KEY (created_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAB03A8386');
        $this->addSql('ALTER TABLE notification CHANGE created_by_id created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT `FK_BF5476CAB03A8386` FOREIGN KEY (created_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE otp_code DROP FOREIGN KEY FK_93FE2319B03A8386');
        $this->addSql('ALTER TABLE otp_code CHANGE created_by_id created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE otp_code ADD CONSTRAINT `FK_93FE2319B03A8386` FOREIGN KEY (created_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
    }
}
