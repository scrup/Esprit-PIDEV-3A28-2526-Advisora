<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502180318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add BlameableTrait relations across timestamped entities with safe backfill';
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
            throw new \RuntimeException('Cannot add blameable relations: no user found for backfill.');
        }

        $fallbackUserId = (int) $fallbackUserId;

        $this->addSql('ALTER TABLE projects ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE projects SET created_by_id = ? WHERE created_by_id IS NULL', [$fallbackUserId]);
        $this->addSql('ALTER TABLE projects CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A4B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A4896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_5C93B3A4B03A8386 ON projects (created_by_id)');
        $this->addSql('CREATE INDEX IDX_5C93B3A4896DBBDE ON projects (updated_by_id)');

        $this->addSql('ALTER TABLE resource_market_delivery ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE resource_market_delivery SET created_by_id = ? WHERE created_by_id IS NULL', [$fallbackUserId]);
        $this->addSql('ALTER TABLE resource_market_delivery CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE resource_market_delivery DROP createdBy, DROP updatedBy');
        $this->addSql('ALTER TABLE resource_market_delivery ADD CONSTRAINT FK_959D4501B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE resource_market_delivery ADD CONSTRAINT FK_959D4501896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_959D4501B03A8386 ON resource_market_delivery (created_by_id)');
        $this->addSql('CREATE INDEX IDX_959D4501896DBBDE ON resource_market_delivery (updated_by_id)');

        $this->addSql('ALTER TABLE resource_market_listing ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE resource_market_listing SET created_by_id = ? WHERE created_by_id IS NULL', [$fallbackUserId]);
        $this->addSql('ALTER TABLE resource_market_listing CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE resource_market_listing DROP createdBy, DROP updatedBy');
        $this->addSql('ALTER TABLE resource_market_listing ADD CONSTRAINT FK_B0D82BF7B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE resource_market_listing ADD CONSTRAINT FK_B0D82BF7896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B0D82BF7B03A8386 ON resource_market_listing (created_by_id)');
        $this->addSql('CREATE INDEX IDX_B0D82BF7896DBBDE ON resource_market_listing (updated_by_id)');

        $this->addSql('ALTER TABLE resource_market_order ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE resource_market_order SET created_by_id = ? WHERE created_by_id IS NULL', [$fallbackUserId]);
        $this->addSql('ALTER TABLE resource_market_order CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE resource_market_order ADD CONSTRAINT FK_BB66A70BB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE resource_market_order ADD CONSTRAINT FK_BB66A70B896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_BB66A70BB03A8386 ON resource_market_order (created_by_id)');
        $this->addSql('CREATE INDEX IDX_BB66A70B896DBBDE ON resource_market_order (updated_by_id)');

        $this->addSql('ALTER TABLE resource_market_review ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE resource_market_review SET created_by_id = ? WHERE created_by_id IS NULL', [$fallbackUserId]);
        $this->addSql('ALTER TABLE resource_market_review CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE resource_market_review ADD CONSTRAINT FK_100B0C0CB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE resource_market_review ADD CONSTRAINT FK_100B0C0C896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_100B0C0CB03A8386 ON resource_market_review (created_by_id)');
        $this->addSql('CREATE INDEX IDX_100B0C0C896DBBDE ON resource_market_review (updated_by_id)');

        $this->addSql('ALTER TABLE resource_wallet_account ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE resource_wallet_account SET created_by_id = ? WHERE created_by_id IS NULL', [$fallbackUserId]);
        $this->addSql('ALTER TABLE resource_wallet_account CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE resource_wallet_account ADD CONSTRAINT FK_7E79F811B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE resource_wallet_account ADD CONSTRAINT FK_7E79F811896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_7E79F811B03A8386 ON resource_wallet_account (created_by_id)');
        $this->addSql('CREATE INDEX IDX_7E79F811896DBBDE ON resource_wallet_account (updated_by_id)');

        $this->addSql('ALTER TABLE resource_wallet_topup ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE resource_wallet_topup SET created_by_id = ? WHERE created_by_id IS NULL', [$fallbackUserId]);
        $this->addSql('ALTER TABLE resource_wallet_topup CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE resource_wallet_topup ADD CONSTRAINT FK_AEF2F04B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE resource_wallet_topup ADD CONSTRAINT FK_AEF2F04896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_AEF2F04B03A8386 ON resource_wallet_topup (created_by_id)');
        $this->addSql('CREATE INDEX IDX_AEF2F04896DBBDE ON resource_wallet_topup (updated_by_id)');

        $this->addSql('ALTER TABLE resource_wallet_txn ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE resource_wallet_txn SET created_by_id = ? WHERE created_by_id IS NULL', [$fallbackUserId]);
        $this->addSql('ALTER TABLE resource_wallet_txn CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE resource_wallet_txn ADD CONSTRAINT FK_CE5C0FACB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE resource_wallet_txn ADD CONSTRAINT FK_CE5C0FAC896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CE5C0FACB03A8386 ON resource_wallet_txn (created_by_id)');
        $this->addSql('CREATE INDEX IDX_CE5C0FAC896DBBDE ON resource_wallet_txn (updated_by_id)');

        $this->addSql('ALTER TABLE swot_item ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE swot_item SET created_by_id = ? WHERE created_by_id IS NULL', [$fallbackUserId]);
        $this->addSql('ALTER TABLE swot_item CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE swot_item DROP createdBy, DROP updatedBy');
        $this->addSql('ALTER TABLE swot_item ADD CONSTRAINT FK_E0E3A2F4B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE swot_item ADD CONSTRAINT FK_E0E3A2F4896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E0E3A2F4B03A8386 ON swot_item (created_by_id)');
        $this->addSql('CREATE INDEX IDX_E0E3A2F4896DBBDE ON swot_item (updated_by_id)');

        $this->addSql('ALTER TABLE task ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE task SET created_by_id = ? WHERE created_by_id IS NULL', [$fallbackUserId]);
        $this->addSql('ALTER TABLE task CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE task DROP createdBy, DROP updatedBy');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_527EDB25B03A8386 ON task (created_by_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25896DBBDE ON task (updated_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE projects DROP FOREIGN KEY FK_5C93B3A4B03A8386');
        $this->addSql('ALTER TABLE projects DROP FOREIGN KEY FK_5C93B3A4896DBBDE');
        $this->addSql('DROP INDEX IDX_5C93B3A4B03A8386 ON projects');
        $this->addSql('DROP INDEX IDX_5C93B3A4896DBBDE ON projects');
        $this->addSql('ALTER TABLE projects DROP created_by_id, DROP updated_by_id');
        $this->addSql('ALTER TABLE resource_market_delivery DROP FOREIGN KEY FK_959D4501B03A8386');
        $this->addSql('ALTER TABLE resource_market_delivery DROP FOREIGN KEY FK_959D4501896DBBDE');
        $this->addSql('DROP INDEX IDX_959D4501B03A8386 ON resource_market_delivery');
        $this->addSql('DROP INDEX IDX_959D4501896DBBDE ON resource_market_delivery');
        $this->addSql('ALTER TABLE resource_market_delivery ADD createdBy VARCHAR(255) DEFAULT NULL, ADD updatedBy VARCHAR(255) DEFAULT NULL, DROP created_by_id, DROP updated_by_id');
        $this->addSql('ALTER TABLE resource_market_listing DROP FOREIGN KEY FK_B0D82BF7B03A8386');
        $this->addSql('ALTER TABLE resource_market_listing DROP FOREIGN KEY FK_B0D82BF7896DBBDE');
        $this->addSql('DROP INDEX IDX_B0D82BF7B03A8386 ON resource_market_listing');
        $this->addSql('DROP INDEX IDX_B0D82BF7896DBBDE ON resource_market_listing');
        $this->addSql('ALTER TABLE resource_market_listing ADD createdBy VARCHAR(255) DEFAULT NULL, ADD updatedBy VARCHAR(255) DEFAULT NULL, DROP created_by_id, DROP updated_by_id');
        $this->addSql('ALTER TABLE resource_market_order DROP FOREIGN KEY FK_BB66A70BB03A8386');
        $this->addSql('ALTER TABLE resource_market_order DROP FOREIGN KEY FK_BB66A70B896DBBDE');
        $this->addSql('DROP INDEX IDX_BB66A70BB03A8386 ON resource_market_order');
        $this->addSql('DROP INDEX IDX_BB66A70B896DBBDE ON resource_market_order');
        $this->addSql('ALTER TABLE resource_market_order DROP created_by_id, DROP updated_by_id');
        $this->addSql('ALTER TABLE resource_market_review DROP FOREIGN KEY FK_100B0C0CB03A8386');
        $this->addSql('ALTER TABLE resource_market_review DROP FOREIGN KEY FK_100B0C0C896DBBDE');
        $this->addSql('DROP INDEX IDX_100B0C0CB03A8386 ON resource_market_review');
        $this->addSql('DROP INDEX IDX_100B0C0C896DBBDE ON resource_market_review');
        $this->addSql('ALTER TABLE resource_market_review DROP created_by_id, DROP updated_by_id');
        $this->addSql('ALTER TABLE resource_wallet_account DROP FOREIGN KEY FK_7E79F811B03A8386');
        $this->addSql('ALTER TABLE resource_wallet_account DROP FOREIGN KEY FK_7E79F811896DBBDE');
        $this->addSql('DROP INDEX IDX_7E79F811B03A8386 ON resource_wallet_account');
        $this->addSql('DROP INDEX IDX_7E79F811896DBBDE ON resource_wallet_account');
        $this->addSql('ALTER TABLE resource_wallet_account DROP created_by_id, DROP updated_by_id');
        $this->addSql('ALTER TABLE resource_wallet_topup DROP FOREIGN KEY FK_AEF2F04B03A8386');
        $this->addSql('ALTER TABLE resource_wallet_topup DROP FOREIGN KEY FK_AEF2F04896DBBDE');
        $this->addSql('DROP INDEX IDX_AEF2F04B03A8386 ON resource_wallet_topup');
        $this->addSql('DROP INDEX IDX_AEF2F04896DBBDE ON resource_wallet_topup');
        $this->addSql('ALTER TABLE resource_wallet_topup DROP created_by_id, DROP updated_by_id');
        $this->addSql('ALTER TABLE resource_wallet_txn DROP FOREIGN KEY FK_CE5C0FACB03A8386');
        $this->addSql('ALTER TABLE resource_wallet_txn DROP FOREIGN KEY FK_CE5C0FAC896DBBDE');
        $this->addSql('DROP INDEX IDX_CE5C0FACB03A8386 ON resource_wallet_txn');
        $this->addSql('DROP INDEX IDX_CE5C0FAC896DBBDE ON resource_wallet_txn');
        $this->addSql('ALTER TABLE resource_wallet_txn DROP created_by_id, DROP updated_by_id');
        $this->addSql('ALTER TABLE swot_item DROP FOREIGN KEY FK_E0E3A2F4B03A8386');
        $this->addSql('ALTER TABLE swot_item DROP FOREIGN KEY FK_E0E3A2F4896DBBDE');
        $this->addSql('DROP INDEX IDX_E0E3A2F4B03A8386 ON swot_item');
        $this->addSql('DROP INDEX IDX_E0E3A2F4896DBBDE ON swot_item');
        $this->addSql('ALTER TABLE swot_item ADD createdBy VARCHAR(255) DEFAULT NULL, ADD updatedBy VARCHAR(255) DEFAULT NULL, DROP created_by_id, DROP updated_by_id');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25B03A8386');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25896DBBDE');
        $this->addSql('DROP INDEX IDX_527EDB25B03A8386 ON task');
        $this->addSql('DROP INDEX IDX_527EDB25896DBBDE ON task');
        $this->addSql('ALTER TABLE task ADD createdBy VARCHAR(255) DEFAULT NULL, ADD updatedBy VARCHAR(255) DEFAULT NULL, DROP created_by_id, DROP updated_by_id');
    }
}
