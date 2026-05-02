<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502173122 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE decisions CHANGE StatutD StatutD ENUM(\'pending\',\'active\',\'refused\') NOT NULL DEFAULT \'pending\'');
        $this->addSql('ALTER TABLE projects CHANGE budgetProj budgetProj DOUBLE PRECISION DEFAULT 0 NOT NULL, CHANGE stateProj stateProj ENUM(\'PENDING\',\'ACCEPTED\',\'REFUSED\',\'ARCHIVED\') NOT NULL DEFAULT \'PENDING\', CHANGE avancementProj avancementProj DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE resource_market_delivery CHANGE totalPrice totalPrice NUMERIC(12, 4) NOT NULL');
        $this->addSql('ALTER TABLE resource_wallet_account CHANGE balanceCoins balanceCoins NUMERIC(14, 4) NOT NULL');
        $this->addSql('ALTER TABLE resource_wallet_txn CHANGE amountCoins amountCoins NUMERIC(14, 4) NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE createdBy createdBy VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE decisions CHANGE StatutD StatutD ENUM(\'pending\', \'active\', \'refused\') DEFAULT \'pending\' NOT NULL');
        $this->addSql('ALTER TABLE projects CHANGE budgetProj budgetProj DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE stateProj stateProj ENUM(\'PENDING\', \'ACCEPTED\', \'REFUSED\', \'ARCHIVED\') DEFAULT \'PENDING\' NOT NULL, CHANGE avancementProj avancementProj DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE resource_market_delivery CHANGE totalPrice totalPrice NUMERIC(12, 3) NOT NULL');
        $this->addSql('ALTER TABLE resource_wallet_account CHANGE balanceCoins balanceCoins NUMERIC(14, 3) NOT NULL');
        $this->addSql('ALTER TABLE resource_wallet_txn CHANGE amountCoins amountCoins NUMERIC(14, 3) NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE createdBy createdBy VARCHAR(255) DEFAULT \'system\' NOT NULL');
    }
}
