<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add TOWS metadata fields to objectives for SWOT pair traceability.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `objective` ADD `tows_type` VARCHAR(2) DEFAULT NULL, ADD `tows_source_swot_item_id` INT DEFAULT NULL, ADD `tows_target_swot_item_id` INT DEFAULT NULL');
        $this->addSql('CREATE INDEX `IDX_OBJECTIVE_TOWS_SOURCE_SWOT` ON `objective` (`tows_source_swot_item_id`)');
        $this->addSql('CREATE INDEX `IDX_OBJECTIVE_TOWS_TARGET_SWOT` ON `objective` (`tows_target_swot_item_id`)');
        $this->addSql('ALTER TABLE `objective` ADD CONSTRAINT `FK_OBJECTIVE_TOWS_SOURCE_SWOT` FOREIGN KEY (`tows_source_swot_item_id`) REFERENCES `swot_item` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `objective` ADD CONSTRAINT `FK_OBJECTIVE_TOWS_TARGET_SWOT` FOREIGN KEY (`tows_target_swot_item_id`) REFERENCES `swot_item` (`id`) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `objective` DROP FOREIGN KEY `FK_OBJECTIVE_TOWS_SOURCE_SWOT`');
        $this->addSql('ALTER TABLE `objective` DROP FOREIGN KEY `FK_OBJECTIVE_TOWS_TARGET_SWOT`');
        $this->addSql('DROP INDEX `IDX_OBJECTIVE_TOWS_SOURCE_SWOT` ON `objective`');
        $this->addSql('DROP INDEX `IDX_OBJECTIVE_TOWS_TARGET_SWOT` ON `objective`');
        $this->addSql('ALTER TABLE `objective` DROP `tows_type`, DROP `tows_source_swot_item_id`, DROP `tows_target_swot_item_id`');
    }
}
