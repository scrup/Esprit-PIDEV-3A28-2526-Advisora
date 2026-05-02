<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502172956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change decimal scale from 3 to 4 for wallet and delivery amounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE resource_market_delivery CHANGE totalPrice totalPrice NUMERIC(12, 4) NOT NULL');
        $this->addSql('ALTER TABLE resource_wallet_account CHANGE balanceCoins balanceCoins NUMERIC(14, 4) NOT NULL');
        $this->addSql('ALTER TABLE resource_wallet_txn CHANGE amountCoins amountCoins NUMERIC(14, 4) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE resource_market_delivery CHANGE totalPrice totalPrice NUMERIC(12, 3) NOT NULL');
        $this->addSql('ALTER TABLE resource_wallet_account CHANGE balanceCoins balanceCoins NUMERIC(14, 3) NOT NULL');
        $this->addSql('ALTER TABLE resource_wallet_txn CHANGE amountCoins amountCoins NUMERIC(14, 3) NOT NULL');
    }
}
