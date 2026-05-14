<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508015000 extends AbstractMigration
{
    /**
     * @var array<int, array{0: string, 1: string}>
     */
    private const AUDIT_CREATED_BY_CONSTRAINTS = [
        ['auth_session', 'FK_9E60F527B03A8386'],
        ['notification', 'FK_BF5476CAB03A8386'],
        ['otp_code', 'FK_93FE2319B03A8386'],
        ['project', 'FK_5C93B3A4B03A8386'],
        ['resource_market_delivery', 'FK_959D4501B03A8386'],
        ['resource_market_listing', 'FK_B0D82BF7B03A8386'],
        ['resource_market_order', 'FK_BB66A70BB03A8386'],
        ['resource_market_review', 'FK_100B0C0CB03A8386'],
        ['resource_wallet_account', 'FK_7E79F811B03A8386'],
        ['resource_wallet_topup', 'FK_AEF2F04B03A8386'],
        ['resource_wallet_txn', 'FK_CE5C0FACB03A8386'],
        ['swot_item', 'FK_E0E3A2F4B03A8386'],
        ['task', 'FK_527EDB25B03A8386'],
    ];

    public function getDescription(): string
    {
        return 'Allow deleting users referenced by audit created_by_id fields.';
    }

    public function up(Schema $schema): void
    {
        // Disabled: This migration attempted to modify created_by_id column which doesn't exist in the database
        // The BlameableTrait that created these columns was removed from entities
    }

    public function down(Schema $schema): void
    {
        // Disabled: This migration attempted to modify created_by_id column which doesn't exist in the database
    }
}
