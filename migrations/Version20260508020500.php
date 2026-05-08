<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508020500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cascade user deletion to owned projects and decisions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `decision` DROP FOREIGN KEY `fk_decisions_user`');
        $this->addSql('ALTER TABLE `decision` ADD CONSTRAINT `fk_decisions_user` FOREIGN KEY (user_id) REFERENCES `user` (idUser) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE `project` DROP FOREIGN KEY `fk_projects_client`');
        $this->addSql('ALTER TABLE `project` ADD CONSTRAINT `fk_projects_client` FOREIGN KEY (user_id) REFERENCES `user` (idUser) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `decision` DROP FOREIGN KEY `fk_decisions_user`');
        $this->addSql('ALTER TABLE `decision` ADD CONSTRAINT `fk_decisions_user` FOREIGN KEY (user_id) REFERENCES `user` (idUser)');

        $this->addSql('ALTER TABLE `project` DROP FOREIGN KEY `fk_projects_client`');
        $this->addSql('ALTER TABLE `project` ADD CONSTRAINT `fk_projects_client` FOREIGN KEY (user_id) REFERENCES `user` (idUser)');
    }
}
