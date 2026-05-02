<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502191817 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY `fk_bookings_event`');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY `fk_bookings_user`');
        $this->addSql('DROP INDEX idx_7a853c3571f7e88b ON booking');
        $this->addSql('CREATE INDEX IDX_E00CEDDE71F7E88B ON booking (event_id)');
        $this->addSql('DROP INDEX idx_7a853c35a76ed395 ON booking');
        $this->addSql('CREATE INDEX IDX_E00CEDDEA76ED395 ON booking (user_id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT `fk_bookings_event` FOREIGN KEY (event_id) REFERENCES event (id_ev_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT `fk_bookings_user` FOREIGN KEY (user_id) REFERENCES user (idUser) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE decision DROP FOREIGN KEY `fk_decisions_project`');
        $this->addSql('ALTER TABLE decision DROP FOREIGN KEY `fk_decisions_user`');
        $this->addSql('DROP INDEX idx_638daa17166d1f9c ON decision');
        $this->addSql('CREATE INDEX IDX_84ACBE48166D1F9C ON decision (project_id)');
        $this->addSql('DROP INDEX idx_638daa17a76ed395 ON decision');
        $this->addSql('CREATE INDEX IDX_84ACBE48A76ED395 ON decision (user_id)');
        $this->addSql('ALTER TABLE decision ADD CONSTRAINT `fk_decisions_project` FOREIGN KEY (project_id) REFERENCES project (idProj) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE decision ADD CONSTRAINT `fk_decisions_user` FOREIGN KEY (user_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY `fk_events_gerant`');
        $this->addSql('DROP INDEX idx_5387574aa500a924 ON event');
        $this->addSql('CREATE INDEX IDX_3BAE0AA7A500A924 ON event (gerant_id)');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT `fk_events_gerant` FOREIGN KEY (gerant_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE investment DROP FOREIGN KEY `fk_investments_project`');
        $this->addSql('ALTER TABLE investment DROP FOREIGN KEY `fk_investments_user`');
        $this->addSql('DROP INDEX idx_74fd72e0166d1f9c ON investment');
        $this->addSql('CREATE INDEX IDX_43CA0AD6166D1F9C ON investment (project_id)');
        $this->addSql('DROP INDEX idx_74fd72e0a76ed395 ON investment');
        $this->addSql('CREATE INDEX IDX_43CA0AD6A76ED395 ON investment (user_id)');
        $this->addSql('ALTER TABLE investment ADD CONSTRAINT `fk_investments_project` FOREIGN KEY (project_id) REFERENCES project (idProj) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE investment ADD CONSTRAINT `fk_investments_user` FOREIGN KEY (user_id) REFERENCES user (idUser) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE objective DROP FOREIGN KEY `fk_objectives_strategy`');
        $this->addSql('DROP INDEX idx_6cb0696c3ae1b9bd ON objective');
        $this->addSql('CREATE INDEX IDX_B996F1013AE1B9BD ON objective (strategie_id)');
        $this->addSql('ALTER TABLE objective ADD CONSTRAINT `fk_objectives_strategy` FOREIGN KEY (strategie_id) REFERENCES strategy (idStrategie) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY `FK_5C93B3A4896DBBDE`');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY `FK_5C93B3A4B03A8386`');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY `fk_projects_client`');
        $this->addSql('DROP INDEX idx_5c93b3a4a76ed395 ON project');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEA76ED395 ON project (user_id)');
        $this->addSql('DROP INDEX idx_5c93b3a4b03a8386 ON project');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEB03A8386 ON project (created_by_id)');
        $this->addSql('DROP INDEX idx_5c93b3a4896dbbde ON project');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE896DBBDE ON project (updated_by_id)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT `FK_5C93B3A4896DBBDE` FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT `FK_5C93B3A4B03A8386` FOREIGN KEY (created_by_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT `fk_projects_client` FOREIGN KEY (user_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE resource DROP FOREIGN KEY `fk_resources_catalogue`');
        $this->addSql('DROP INDEX idx_ef66ebae8d733cc9 ON resource');
        $this->addSql('CREATE INDEX IDX_BC91F4168D733CC9 ON resource (cataloguefournisseur_id)');
        $this->addSql('ALTER TABLE resource ADD CONSTRAINT `fk_resources_catalogue` FOREIGN KEY (cataloguefournisseur_id) REFERENCES cataloguefournisseur (idFr) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE strategy DROP FOREIGN KEY `fk_strategies_project`');
        $this->addSql('ALTER TABLE strategy DROP FOREIGN KEY `fk_strategies_user`');
        $this->addSql('DROP INDEX idx_611f2213166d1f9c ON strategy');
        $this->addSql('CREATE INDEX IDX_144645ED166D1F9C ON strategy (project_id)');
        $this->addSql('DROP INDEX idx_611f2213a76ed395 ON strategy');
        $this->addSql('CREATE INDEX IDX_144645EDA76ED395 ON strategy (user_id)');
        $this->addSql('ALTER TABLE strategy ADD CONSTRAINT `fk_strategies_project` FOREIGN KEY (project_id) REFERENCES project (idProj) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE strategy ADD CONSTRAINT `fk_strategies_user` FOREIGN KEY (user_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY `fk_transaction_investment`');
        $this->addSql('DROP INDEX idx_723705d16e1b4fd5 ON transactions');
        $this->addSql('CREATE INDEX IDX_EAA81A4C6E1B4FD5 ON transactions (investment_id)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT `fk_transaction_investment` FOREIGN KEY (investment_id) REFERENCES investment (idInv) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE71F7E88B');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEA76ED395');
        $this->addSql('DROP INDEX idx_e00ceddea76ed395 ON booking');
        $this->addSql('CREATE INDEX IDX_7A853C35A76ED395 ON booking (user_id)');
        $this->addSql('DROP INDEX idx_e00cedde71f7e88b ON booking');
        $this->addSql('CREATE INDEX IDX_7A853C3571F7E88B ON booking (event_id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE71F7E88B FOREIGN KEY (event_id) REFERENCES event (id_ev_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEA76ED395 FOREIGN KEY (user_id) REFERENCES user (idUser) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE decision DROP FOREIGN KEY FK_84ACBE48166D1F9C');
        $this->addSql('ALTER TABLE decision DROP FOREIGN KEY FK_84ACBE48A76ED395');
        $this->addSql('DROP INDEX idx_84acbe48166d1f9c ON decision');
        $this->addSql('CREATE INDEX IDX_638DAA17166D1F9C ON decision (project_id)');
        $this->addSql('DROP INDEX idx_84acbe48a76ed395 ON decision');
        $this->addSql('CREATE INDEX IDX_638DAA17A76ED395 ON decision (user_id)');
        $this->addSql('ALTER TABLE decision ADD CONSTRAINT FK_84ACBE48166D1F9C FOREIGN KEY (project_id) REFERENCES project (idProj) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE decision ADD CONSTRAINT FK_84ACBE48A76ED395 FOREIGN KEY (user_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7A500A924');
        $this->addSql('DROP INDEX idx_3bae0aa7a500a924 ON event');
        $this->addSql('CREATE INDEX IDX_5387574AA500A924 ON event (gerant_id)');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7A500A924 FOREIGN KEY (gerant_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE investment DROP FOREIGN KEY FK_43CA0AD6166D1F9C');
        $this->addSql('ALTER TABLE investment DROP FOREIGN KEY FK_43CA0AD6A76ED395');
        $this->addSql('DROP INDEX idx_43ca0ad6166d1f9c ON investment');
        $this->addSql('CREATE INDEX IDX_74FD72E0166D1F9C ON investment (project_id)');
        $this->addSql('DROP INDEX idx_43ca0ad6a76ed395 ON investment');
        $this->addSql('CREATE INDEX IDX_74FD72E0A76ED395 ON investment (user_id)');
        $this->addSql('ALTER TABLE investment ADD CONSTRAINT FK_43CA0AD6166D1F9C FOREIGN KEY (project_id) REFERENCES project (idProj) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE investment ADD CONSTRAINT FK_43CA0AD6A76ED395 FOREIGN KEY (user_id) REFERENCES user (idUser) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE objective DROP FOREIGN KEY FK_B996F1013AE1B9BD');
        $this->addSql('DROP INDEX idx_b996f1013ae1b9bd ON objective');
        $this->addSql('CREATE INDEX IDX_6CB0696C3AE1B9BD ON objective (strategie_id)');
        $this->addSql('ALTER TABLE objective ADD CONSTRAINT FK_B996F1013AE1B9BD FOREIGN KEY (strategie_id) REFERENCES strategy (idStrategie) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EEA76ED395');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EEB03A8386');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE896DBBDE');
        $this->addSql('DROP INDEX idx_2fb3d0eea76ed395 ON project');
        $this->addSql('CREATE INDEX IDX_5C93B3A4A76ED395 ON project (user_id)');
        $this->addSql('DROP INDEX idx_2fb3d0eeb03a8386 ON project');
        $this->addSql('CREATE INDEX IDX_5C93B3A4B03A8386 ON project (created_by_id)');
        $this->addSql('DROP INDEX idx_2fb3d0ee896dbbde ON project');
        $this->addSql('CREATE INDEX IDX_5C93B3A4896DBBDE ON project (updated_by_id)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEA76ED395 FOREIGN KEY (user_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE resource DROP FOREIGN KEY FK_BC91F4168D733CC9');
        $this->addSql('DROP INDEX idx_bc91f4168d733cc9 ON resource');
        $this->addSql('CREATE INDEX IDX_EF66EBAE8D733CC9 ON resource (cataloguefournisseur_id)');
        $this->addSql('ALTER TABLE resource ADD CONSTRAINT FK_BC91F4168D733CC9 FOREIGN KEY (cataloguefournisseur_id) REFERENCES cataloguefournisseur (idFr) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE strategy DROP FOREIGN KEY FK_144645ED166D1F9C');
        $this->addSql('ALTER TABLE strategy DROP FOREIGN KEY FK_144645EDA76ED395');
        $this->addSql('DROP INDEX idx_144645eda76ed395 ON strategy');
        $this->addSql('CREATE INDEX IDX_611F2213A76ED395 ON strategy (user_id)');
        $this->addSql('DROP INDEX idx_144645ed166d1f9c ON strategy');
        $this->addSql('CREATE INDEX IDX_611F2213166D1F9C ON strategy (project_id)');
        $this->addSql('ALTER TABLE strategy ADD CONSTRAINT FK_144645ED166D1F9C FOREIGN KEY (project_id) REFERENCES project (idProj) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE strategy ADD CONSTRAINT FK_144645EDA76ED395 FOREIGN KEY (user_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4C6E1B4FD5');
        $this->addSql('DROP INDEX idx_eaa81a4c6e1b4fd5 ON transactions');
        $this->addSql('CREATE INDEX IDX_723705D16E1B4FD5 ON transactions (investment_id)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4C6E1B4FD5 FOREIGN KEY (investment_id) REFERENCES investment (idInv) ON DELETE CASCADE');
    }
}
