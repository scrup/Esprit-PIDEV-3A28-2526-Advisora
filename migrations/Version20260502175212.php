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
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE auth_session ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL, DROP createdBy, DROP updatedBy');
        $this->addSql('ALTER TABLE auth_session ADD CONSTRAINT FK_9E60F527B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE auth_session ADD CONSTRAINT FK_9E60F527896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_9E60F527B03A8386 ON auth_session (created_by_id)');
        $this->addSql('CREATE INDEX IDX_9E60F527896DBBDE ON auth_session (updated_by_id)');
        $this->addSql('ALTER TABLE notification ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL, DROP createdBy, DROP updatedBy');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_BF5476CAB03A8386 ON notification (created_by_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CA896DBBDE ON notification (updated_by_id)');
        $this->addSql('ALTER TABLE otp_code ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL, DROP createdBy, DROP updatedBy');
        $this->addSql('ALTER TABLE otp_code ADD CONSTRAINT FK_93FE2319B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE otp_code ADD CONSTRAINT FK_93FE2319896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_93FE2319B03A8386 ON otp_code (created_by_id)');
        $this->addSql('CREATE INDEX IDX_93FE2319896DBBDE ON otp_code (updated_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE auth_session DROP FOREIGN KEY FK_9E60F527B03A8386');
        $this->addSql('ALTER TABLE auth_session DROP FOREIGN KEY FK_9E60F527896DBBDE');
        $this->addSql('DROP INDEX IDX_9E60F527B03A8386 ON auth_session');
        $this->addSql('DROP INDEX IDX_9E60F527896DBBDE ON auth_session');
        $this->addSql('ALTER TABLE auth_session ADD createdBy VARCHAR(255) DEFAULT NULL, ADD updatedBy VARCHAR(255) DEFAULT NULL, DROP created_by_id, DROP updated_by_id');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAB03A8386');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA896DBBDE');
        $this->addSql('DROP INDEX IDX_BF5476CAB03A8386 ON notification');
        $this->addSql('DROP INDEX IDX_BF5476CA896DBBDE ON notification');
        $this->addSql('ALTER TABLE notification ADD createdBy VARCHAR(255) DEFAULT NULL, ADD updatedBy VARCHAR(255) DEFAULT NULL, DROP created_by_id, DROP updated_by_id');
        $this->addSql('ALTER TABLE otp_code DROP FOREIGN KEY FK_93FE2319B03A8386');
        $this->addSql('ALTER TABLE otp_code DROP FOREIGN KEY FK_93FE2319896DBBDE');
        $this->addSql('DROP INDEX IDX_93FE2319B03A8386 ON otp_code');
        $this->addSql('DROP INDEX IDX_93FE2319896DBBDE ON otp_code');
        $this->addSql('ALTER TABLE otp_code ADD createdBy VARCHAR(255) DEFAULT NULL, ADD updatedBy VARCHAR(255) DEFAULT NULL, DROP created_by_id, DROP updated_by_id');
    }
}
