<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260422180810 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE decisions (idD INT AUTO_INCREMENT NOT NULL, StatutD ENUM(\'pending\',\'active\',\'refused\') NOT NULL DEFAULT \'pending\', descriptionD LONGTEXT DEFAULT NULL, dateDecision DATE NOT NULL, idProj INT NOT NULL, idUser INT NOT NULL, INDEX IDX_638DAA1721F1620E (idProj), INDEX IDX_638DAA17FE6E88D7 (idUser), PRIMARY KEY (idD)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE transaction (idTransac INT AUTO_INCREMENT NOT NULL, DateTransac DATE NOT NULL, MontantTransac DOUBLE PRECISION NOT NULL, type VARCHAR(255) DEFAULT NULL, statut VARCHAR(255) NOT NULL, idInv INT DEFAULT NULL, INDEX IDX_723705D1DB17A61A (idInv), PRIMARY KEY (idTransac)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE decisions ADD CONSTRAINT FK_638DAA1721F1620E FOREIGN KEY (idProj) REFERENCES projects (idProj) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE decisions ADD CONSTRAINT FK_638DAA17FE6E88D7 FOREIGN KEY (idUser) REFERENCES user (idUser)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1DB17A61A FOREIGN KEY (idInv) REFERENCES investments (idInv)');
        $this->addSql('DROP TABLE ext_translations');
        $this->addSql('DROP INDEX uq_token_hash ON auth_session');
        $this->addSql('DROP INDEX idx_user ON auth_session');
        $this->addSql('ALTER TABLE auth_session CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE token_hash token_hash VARCHAR(255) NOT NULL, CHANGE device_name device_name VARCHAR(255) DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE ip_address ip_address VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE bookings CHANGE idBk idBk INT AUTO_INCREMENT NOT NULL, CHANGE totalPrixBk totalPrixBk DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT FK_7A853C351CDC6B20 FOREIGN KEY (idEv) REFERENCES events (idEv) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT FK_7A853C35FE6E88D7 FOREIGN KEY (idUser) REFERENCES user (idUser) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bookings RENAME INDEX fk_bookings_event TO IDX_7A853C351CDC6B20');
        $this->addSql('ALTER TABLE bookings RENAME INDEX fk_bookings_user TO IDX_7A853C35FE6E88D7');
        $this->addSql('ALTER TABLE cataloguefournisseur CHANGE idFr idFr INT AUTO_INCREMENT NOT NULL, CHANGE nomFr nomFr VARCHAR(255) NOT NULL, CHANGE quantite quantite INT NOT NULL, CHANGE fournisseur fournisseur VARCHAR(255) DEFAULT NULL, CHANGE emailFr emailFr VARCHAR(255) DEFAULT NULL, CHANGE localisationFr localisationFr VARCHAR(255) DEFAULT NULL, CHANGE numTelFr numTelFr VARCHAR(255) DEFAULT NULL, ADD PRIMARY KEY (idFr)');
        $this->addSql('ALTER TABLE events ADD price NUMERIC(10, 2) DEFAULT NULL, ADD latitude NUMERIC(10, 8) DEFAULT NULL, ADD longitude NUMERIC(11, 8) DEFAULT NULL, CHANGE idEv idEv INT AUTO_INCREMENT NOT NULL, CHANGE descriptionEv descriptionEv LONGTEXT DEFAULT NULL, ADD PRIMARY KEY (idEv)');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574AB2C5F4EA FOREIGN KEY (idGerant) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_5387574AB2C5F4EA ON events (idGerant)');
        $this->addSql('ALTER TABLE investments CHANGE idInv idInv INT AUTO_INCREMENT NOT NULL, CHANGE bud_minInv bud_minInv DOUBLE PRECISION NOT NULL, CHANGE bud_maxInv bud_maxInv DOUBLE PRECISION NOT NULL, CHANGE CurrencyInv CurrencyInv VARCHAR(255) NOT NULL, CHANGE idProj idProj INT DEFAULT NULL, CHANGE idUser idUser INT DEFAULT NULL, ADD PRIMARY KEY (idInv)');
        $this->addSql('ALTER TABLE investments ADD CONSTRAINT FK_74FD72E021F1620E FOREIGN KEY (idProj) REFERENCES projects (idProj)');
        $this->addSql('ALTER TABLE investments ADD CONSTRAINT FK_74FD72E0FE6E88D7 FOREIGN KEY (idUser) REFERENCES user (idUser)');
        $this->addSql('CREATE INDEX IDX_74FD72E021F1620E ON investments (idProj)');
        $this->addSql('CREATE INDEX IDX_74FD72E0FE6E88D7 ON investments (idUser)');
        $this->addSql('ALTER TABLE notification ADD id INT AUTO_INCREMENT NOT NULL, CHANGE title title VARCHAR(255) NOT NULL, CHANGE description description VARCHAR(255) NOT NULL, CHANGE target_role target_role VARCHAR(255) DEFAULT NULL, ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE objectives CHANGE idOb idOb INT AUTO_INCREMENT NOT NULL, CHANGE priorityOb priorityOb INT DEFAULT NULL, CHANGE ids ids INT DEFAULT NULL, CHANGE nomObj nomObj VARCHAR(255) DEFAULT NULL, ADD PRIMARY KEY (idOb)');
        $this->addSql('ALTER TABLE objectives ADD CONSTRAINT FK_6CB0696C70DAA798 FOREIGN KEY (ids) REFERENCES strategies (idStrategie)');
        $this->addSql('CREATE INDEX IDX_6CB0696C70DAA798 ON objectives (ids)');
        $this->addSql('ALTER TABLE otp_code CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE purpose purpose VARCHAR(255) NOT NULL, CHANGE code_hash code_hash VARCHAR(255) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE password_reset CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE attempts attempts INT NOT NULL, ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE projects CHANGE idProj idProj INT AUTO_INCREMENT NOT NULL, CHANGE descriptionProj descriptionProj LONGTEXT DEFAULT NULL, CHANGE budgetProj budgetProj DOUBLE PRECISION DEFAULT 0 NOT NULL, CHANGE stateProj stateProj ENUM(\'PENDING\',\'ACCEPTED\',\'REFUSED\',\'ARCHIVED\') NOT NULL DEFAULT \'PENDING\', CHANGE avancementProj avancementProj DOUBLE PRECISION DEFAULT 0 NOT NULL, ADD PRIMARY KEY (idProj)');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A4A455ACCF FOREIGN KEY (idClient) REFERENCES user (idUser) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_5C93B3A4A455ACCF ON projects (idClient)');
        $this->addSql('ALTER TABLE project_resources DROP qtyAllocated, ADD PRIMARY KEY (idProj, idRs)');
        $this->addSql('ALTER TABLE project_resources ADD CONSTRAINT FK_FE5AAE7921F1620E FOREIGN KEY (idProj) REFERENCES projects (idProj) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_resources ADD CONSTRAINT FK_FE5AAE7969351B39 FOREIGN KEY (idRs) REFERENCES resources (idRs) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_FE5AAE7921F1620E ON project_resources (idProj)');
        $this->addSql('CREATE INDEX IDX_FE5AAE7969351B39 ON project_resources (idRs)');
        $this->addSql('ALTER TABLE resource_market_delivery CHANGE idDelivery idDelivery INT AUTO_INCREMENT NOT NULL, CHANGE recipientName recipientName VARCHAR(255) NOT NULL, CHANGE city city VARCHAR(255) NOT NULL, CHANGE addressLine addressLine VARCHAR(255) NOT NULL, CHANGE phone phone VARCHAR(255) DEFAULT NULL, CHANGE phone2 phone2 VARCHAR(255) DEFAULT NULL, CHANGE resourceName resourceName VARCHAR(255) DEFAULT NULL, CHANGE quantity quantity INT NOT NULL, CHANGE totalPrice totalPrice DOUBLE PRECISION NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE provider provider VARCHAR(255) NOT NULL, CHANGE trackingCode trackingCode VARCHAR(255) DEFAULT NULL, CHANGE labelUrl labelUrl VARCHAR(255) DEFAULT NULL, CHANGE providerMessage providerMessage VARCHAR(255) DEFAULT NULL, CHANGE createdAt createdAt DATETIME NOT NULL, CHANGE updatedAt updatedAt DATETIME NOT NULL, ADD PRIMARY KEY (idDelivery)');
        $this->addSql('ALTER TABLE resource_market_listing CHANGE idListing idListing INT AUTO_INCREMENT NOT NULL, CHANGE unitPrice unitPrice DOUBLE PRECISION NOT NULL, CHANGE note note VARCHAR(255) DEFAULT NULL, CHANGE imageUrl imageUrl VARCHAR(255) DEFAULT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE createdAt createdAt DATETIME NOT NULL, CHANGE updatedAt updatedAt DATETIME NOT NULL, ADD PRIMARY KEY (idListing)');
        $this->addSql('ALTER TABLE resource_market_order CHANGE idOrder idOrder INT AUTO_INCREMENT NOT NULL, CHANGE unitPrice unitPrice DOUBLE PRECISION NOT NULL, CHANGE totalPrice totalPrice DOUBLE PRECISION NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE createdAt createdAt DATETIME NOT NULL, ADD PRIMARY KEY (idOrder)');
        $this->addSql('ALTER TABLE resource_market_review CHANGE idReview idReview INT AUTO_INCREMENT NOT NULL, CHANGE comment comment VARCHAR(255) DEFAULT NULL, CHANGE createdAt createdAt DATETIME NOT NULL, ADD PRIMARY KEY (idReview)');
        $this->addSql('ALTER TABLE resource_wallet_account CHANGE idUser idUser INT AUTO_INCREMENT NOT NULL, CHANGE balanceCoins balanceCoins DOUBLE PRECISION NOT NULL, CHANGE updatedAt updatedAt DATETIME NOT NULL, ADD PRIMARY KEY (idUser)');
        $this->addSql('ALTER TABLE resource_wallet_topup CHANGE idTopup idTopup INT AUTO_INCREMENT NOT NULL, CHANGE provider provider VARCHAR(255) NOT NULL, CHANGE amountMoney amountMoney DOUBLE PRECISION NOT NULL, CHANGE coinAmount coinAmount DOUBLE PRECISION NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE externalRef externalRef VARCHAR(255) DEFAULT NULL, CHANGE paymentUrl paymentUrl VARCHAR(255) DEFAULT NULL, CHANGE createdAt createdAt DATETIME NOT NULL, ADD PRIMARY KEY (idTopup)');
        $this->addSql('ALTER TABLE resource_wallet_txn CHANGE idTxn idTxn INT AUTO_INCREMENT NOT NULL, CHANGE txnType txnType VARCHAR(255) NOT NULL, CHANGE amountCoins amountCoins DOUBLE PRECISION NOT NULL, CHANGE balanceAfter balanceAfter DOUBLE PRECISION NOT NULL, CHANGE ref ref VARCHAR(255) DEFAULT NULL, CHANGE createdAt createdAt DATETIME NOT NULL, ADD PRIMARY KEY (idTxn)');
        $this->addSql('ALTER TABLE resources CHANGE idRs idRs INT AUTO_INCREMENT NOT NULL, CHANGE availabilityStatusRs availabilityStatusRs VARCHAR(255) NOT NULL, CHANGE nomRs nomRs VARCHAR(255) NOT NULL, CHANGE QuantiteRs QuantiteRs INT NOT NULL, CHANGE prixRs prixRs DOUBLE PRECISION NOT NULL, CHANGE idFr idFr INT DEFAULT NULL, CHANGE imageUrlRs imageUrlRs VARCHAR(255) DEFAULT NULL, CHANGE thumbnailUrlRs thumbnailUrlRs VARCHAR(255) DEFAULT NULL, ADD PRIMARY KEY (idRs)');
        $this->addSql('ALTER TABLE resources ADD CONSTRAINT FK_EF66EBAE309CFCFA FOREIGN KEY (idFr) REFERENCES cataloguefournisseur (idFr)');
        $this->addSql('CREATE INDEX IDX_EF66EBAE309CFCFA ON resources (idFr)');
        $this->addSql('ALTER TABLE strategies DROP versions, CHANGE idStrategie idStrategie INT AUTO_INCREMENT NOT NULL, CHANGE statusStrategie statusStrategie VARCHAR(255) NOT NULL, CHANGE CreatedAtS CreatedAtS DATETIME NOT NULL, CHANGE nomStrategie nomStrategie VARCHAR(255) NOT NULL, CHANGE justification justification VARCHAR(255) DEFAULT NULL, CHANGE DureeTerme DureeTerme VARCHAR(255) NOT NULL, ADD PRIMARY KEY (idStrategie)');
        $this->addSql('ALTER TABLE strategies ADD CONSTRAINT FK_611F221321F1620E FOREIGN KEY (idProj) REFERENCES projects (idProj)');
        $this->addSql('ALTER TABLE strategies ADD CONSTRAINT FK_611F2213FE6E88D7 FOREIGN KEY (idUser) REFERENCES user (idUser)');
        $this->addSql('CREATE INDEX IDX_611F221321F1620E ON strategies (idProj)');
        $this->addSql('CREATE INDEX IDX_611F2213FE6E88D7 ON strategies (idUser)');
        $this->addSql('ALTER TABLE swot_item CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE description description LONGTEXT NOT NULL, ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE swot_item ADD CONSTRAINT FK_E0E3A2F43AE1B9BD FOREIGN KEY (strategie_id) REFERENCES strategies (idStrategie)');
        $this->addSql('CREATE INDEX IDX_E0E3A2F43AE1B9BD ON swot_item (strategie_id)');
        $this->addSql('ALTER TABLE task CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE project_id project_id INT DEFAULT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE weight weight INT NOT NULL, CHANGE duration_days duration_days INT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25166D1F9C FOREIGN KEY (project_id) REFERENCES projects (idProj)');
        $this->addSql('CREATE INDEX IDX_527EDB25166D1F9C ON task (project_id)');
        $this->addSql('ALTER TABLE user ADD interests JSON DEFAULT NULL, CHANGE idUser idUser INT AUTO_INCREMENT NOT NULL, CHANGE cin cin VARCHAR(255) DEFAULT NULL, CHANGE EmailUser EmailUser VARCHAR(255) NOT NULL, CHANGE nomUser nomUser VARCHAR(255) NOT NULL, CHANGE PrenomUser PrenomUser VARCHAR(255) NOT NULL, CHANGE NumTelUser NumTelUser VARCHAR(255) DEFAULT NULL, CHANGE roleUser roleUser VARCHAR(255) NOT NULL, CHANGE expertiseAreaUser expertiseAreaUser VARCHAR(255) DEFAULT NULL, CHANGE createdAt createdAt DATETIME NOT NULL, CHANGE updatedAt updatedAt DATETIME NOT NULL, CHANGE totp_secret totp_secret VARCHAR(255) DEFAULT NULL, CHANGE totp_enabled totp_enabled TINYINT NOT NULL, CHANGE failed_login_count failed_login_count INT NOT NULL, ADD PRIMARY KEY (idUser)');
        $this->addSql('ALTER TABLE userlog CHANGE id_log id_log INT AUTO_INCREMENT NOT NULL, CHANGE user_id user_id INT DEFAULT NULL, CHANGE dateLog dateLog DATETIME NOT NULL, CHANGE ip_address ip_address VARCHAR(255) DEFAULT NULL, ADD PRIMARY KEY (id_log)');
        $this->addSql('ALTER TABLE userlog ADD CONSTRAINT FK_4E107E60A76ED395 FOREIGN KEY (user_id) REFERENCES user (idUser)');
        $this->addSql('CREATE INDEX IDX_4E107E60A76ED395 ON userlog (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ext_translations (id INT NOT NULL, locale VARCHAR(8) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, object_class VARCHAR(191) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, field VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, foreign_key VARCHAR(64) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, content LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE decisions DROP FOREIGN KEY FK_638DAA1721F1620E');
        $this->addSql('ALTER TABLE decisions DROP FOREIGN KEY FK_638DAA17FE6E88D7');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1DB17A61A');
        $this->addSql('DROP TABLE decisions');
        $this->addSql('DROP TABLE transaction');
        $this->addSql('ALTER TABLE auth_session CHANGE id id BIGINT NOT NULL, CHANGE token_hash token_hash VARCHAR(64) NOT NULL, CHANGE device_name device_name VARCHAR(120) DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE ip_address ip_address VARCHAR(45) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uq_token_hash ON auth_session (token_hash)');
        $this->addSql('CREATE INDEX idx_user ON auth_session (user_id)');
        $this->addSql('ALTER TABLE bookings DROP FOREIGN KEY FK_7A853C351CDC6B20');
        $this->addSql('ALTER TABLE bookings DROP FOREIGN KEY FK_7A853C35FE6E88D7');
        $this->addSql('ALTER TABLE bookings CHANGE idBk idBk INT NOT NULL, CHANGE totalPrixBk totalPrixBk DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE bookings RENAME INDEX idx_7a853c351cdc6b20 TO fk_bookings_event');
        $this->addSql('ALTER TABLE bookings RENAME INDEX idx_7a853c35fe6e88d7 TO fk_bookings_user');
        $this->addSql('ALTER TABLE cataloguefournisseur MODIFY idFr INT NOT NULL');
        $this->addSql('ALTER TABLE cataloguefournisseur CHANGE idFr idFr INT NOT NULL, CHANGE nomFr nomFr VARCHAR(160) NOT NULL, CHANGE quantite quantite INT DEFAULT 0 NOT NULL, CHANGE fournisseur fournisseur VARCHAR(160) DEFAULT NULL, CHANGE emailFr emailFr VARCHAR(190) DEFAULT NULL, CHANGE localisationFr localisationFr VARCHAR(190) DEFAULT NULL, CHANGE numTelFr numTelFr VARCHAR(30) DEFAULT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE events DROP FOREIGN KEY FK_5387574AB2C5F4EA');
        $this->addSql('DROP INDEX IDX_5387574AB2C5F4EA ON events');
        $this->addSql('ALTER TABLE events MODIFY idEv INT NOT NULL');
        $this->addSql('ALTER TABLE events DROP price, DROP latitude, DROP longitude, CHANGE idEv idEv INT NOT NULL, CHANGE descriptionEv descriptionEv TEXT DEFAULT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE investments DROP FOREIGN KEY FK_74FD72E021F1620E');
        $this->addSql('ALTER TABLE investments DROP FOREIGN KEY FK_74FD72E0FE6E88D7');
        $this->addSql('DROP INDEX IDX_74FD72E021F1620E ON investments');
        $this->addSql('DROP INDEX IDX_74FD72E0FE6E88D7 ON investments');
        $this->addSql('ALTER TABLE investments MODIFY idInv INT NOT NULL');
        $this->addSql('ALTER TABLE investments CHANGE idInv idInv INT NOT NULL, CHANGE bud_minInv bud_minInv DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE bud_maxInv bud_maxInv DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE CurrencyInv CurrencyInv VARCHAR(10) DEFAULT \'TND\' NOT NULL, CHANGE idProj idProj INT NOT NULL, CHANGE idUser idUser INT NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE notification MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE notification DROP id, CHANGE title title VARCHAR(100) NOT NULL, CHANGE description description VARCHAR(400) NOT NULL, CHANGE target_role target_role VARCHAR(20) DEFAULT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE objectives DROP FOREIGN KEY FK_6CB0696C70DAA798');
        $this->addSql('DROP INDEX IDX_6CB0696C70DAA798 ON objectives');
        $this->addSql('ALTER TABLE objectives MODIFY idOb INT NOT NULL');
        $this->addSql('ALTER TABLE objectives CHANGE idOb idOb INT NOT NULL, CHANGE priorityOb priorityOb INT DEFAULT 0, CHANGE nomObj nomObj VARCHAR(200) DEFAULT NULL, CHANGE ids ids INT NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE otp_code MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE otp_code CHANGE id id INT NOT NULL, CHANGE purpose purpose VARCHAR(20) NOT NULL, CHANGE code_hash code_hash VARCHAR(64) NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE password_reset MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE password_reset CHANGE id id INT NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE attempts attempts INT DEFAULT 0 NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE project_resources DROP FOREIGN KEY FK_FE5AAE7921F1620E');
        $this->addSql('ALTER TABLE project_resources DROP FOREIGN KEY FK_FE5AAE7969351B39');
        $this->addSql('DROP INDEX IDX_FE5AAE7921F1620E ON project_resources');
        $this->addSql('DROP INDEX IDX_FE5AAE7969351B39 ON project_resources');
        $this->addSql('ALTER TABLE project_resources ADD qtyAllocated INT DEFAULT 1 NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE projects DROP FOREIGN KEY FK_5C93B3A4A455ACCF');
        $this->addSql('DROP INDEX IDX_5C93B3A4A455ACCF ON projects');
        $this->addSql('ALTER TABLE projects MODIFY idProj INT NOT NULL');
        $this->addSql('ALTER TABLE projects CHANGE idProj idProj INT NOT NULL, CHANGE descriptionProj descriptionProj TEXT DEFAULT NULL, CHANGE budgetProj budgetProj DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE stateProj stateProj ENUM(\'PENDING\', \'ACCEPTED\', \'REFUSED\', \'ARCHIVED\') DEFAULT \'PENDING\' NOT NULL, CHANGE avancementProj avancementProj DOUBLE PRECISION DEFAULT \'0\' NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE resource_market_delivery MODIFY idDelivery INT NOT NULL');
        $this->addSql('ALTER TABLE resource_market_delivery CHANGE idDelivery idDelivery INT NOT NULL, CHANGE recipientName recipientName VARCHAR(160) NOT NULL, CHANGE city city VARCHAR(120) NOT NULL, CHANGE addressLine addressLine VARCHAR(260) NOT NULL, CHANGE phone phone VARCHAR(40) DEFAULT NULL, CHANGE phone2 phone2 VARCHAR(40) DEFAULT NULL, CHANGE resourceName resourceName VARCHAR(200) DEFAULT NULL, CHANGE quantity quantity INT DEFAULT 1 NOT NULL, CHANGE totalPrice totalPrice NUMERIC(12, 3) DEFAULT \'0.000\' NOT NULL, CHANGE status status VARCHAR(30) DEFAULT \'EN_PREPARATION\' NOT NULL, CHANGE provider provider VARCHAR(40) DEFAULT \'FIABILO\' NOT NULL, CHANGE trackingCode trackingCode VARCHAR(140) DEFAULT NULL, CHANGE labelUrl labelUrl VARCHAR(700) DEFAULT NULL, CHANGE providerMessage providerMessage VARCHAR(400) DEFAULT NULL, CHANGE createdAt createdAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updatedAt updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE resource_market_listing MODIFY idListing INT NOT NULL');
        $this->addSql('ALTER TABLE resource_market_listing CHANGE idListing idListing INT NOT NULL, CHANGE unitPrice unitPrice NUMERIC(12, 3) NOT NULL, CHANGE note note VARCHAR(300) DEFAULT NULL, CHANGE imageUrl imageUrl VARCHAR(600) DEFAULT NULL, CHANGE status status VARCHAR(20) DEFAULT \'LISTED\' NOT NULL, CHANGE createdAt createdAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updatedAt updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE resource_market_order MODIFY idOrder INT NOT NULL');
        $this->addSql('ALTER TABLE resource_market_order CHANGE idOrder idOrder INT NOT NULL, CHANGE unitPrice unitPrice NUMERIC(12, 3) NOT NULL, CHANGE totalPrice totalPrice NUMERIC(12, 3) NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'COMPLETED\' NOT NULL, CHANGE createdAt createdAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE resource_market_review MODIFY idReview INT NOT NULL');
        $this->addSql('ALTER TABLE resource_market_review CHANGE idReview idReview INT NOT NULL, CHANGE comment comment VARCHAR(400) DEFAULT NULL, CHANGE createdAt createdAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE resource_wallet_account MODIFY idUser INT NOT NULL');
        $this->addSql('ALTER TABLE resource_wallet_account CHANGE idUser idUser INT NOT NULL, CHANGE balanceCoins balanceCoins NUMERIC(14, 3) DEFAULT \'0.000\' NOT NULL, CHANGE updatedAt updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE resource_wallet_topup MODIFY idTopup INT NOT NULL');
        $this->addSql('ALTER TABLE resource_wallet_topup CHANGE idTopup idTopup INT NOT NULL, CHANGE provider provider VARCHAR(20) NOT NULL, CHANGE amountMoney amountMoney NUMERIC(14, 3) NOT NULL, CHANGE coinAmount coinAmount NUMERIC(14, 3) NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'PENDING\' NOT NULL, CHANGE externalRef externalRef VARCHAR(140) DEFAULT NULL, CHANGE paymentUrl paymentUrl VARCHAR(700) DEFAULT NULL, CHANGE createdAt createdAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE resource_wallet_txn MODIFY idTxn INT NOT NULL');
        $this->addSql('ALTER TABLE resource_wallet_txn CHANGE idTxn idTxn INT NOT NULL, CHANGE txnType txnType VARCHAR(30) NOT NULL, CHANGE amountCoins amountCoins NUMERIC(14, 3) NOT NULL, CHANGE balanceAfter balanceAfter NUMERIC(14, 3) NOT NULL, CHANGE ref ref VARCHAR(180) DEFAULT NULL, CHANGE createdAt createdAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE resources DROP FOREIGN KEY FK_EF66EBAE309CFCFA');
        $this->addSql('DROP INDEX IDX_EF66EBAE309CFCFA ON resources');
        $this->addSql('ALTER TABLE resources MODIFY idRs INT NOT NULL');
        $this->addSql('ALTER TABLE resources CHANGE idRs idRs INT NOT NULL, CHANGE availabilityStatusRs availabilityStatusRs ENUM(\'AVAILABLE\', \'RESERVED\', \'UNAVAILABLE\') DEFAULT \'AVAILABLE\' NOT NULL, CHANGE nomRs nomRs VARCHAR(160) NOT NULL, CHANGE QuantiteRs QuantiteRs INT DEFAULT 0 NOT NULL, CHANGE prixRs prixRs DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE imageUrlRs imageUrlRs VARCHAR(700) DEFAULT NULL, CHANGE thumbnailUrlRs thumbnailUrlRs VARCHAR(512) DEFAULT NULL, CHANGE idFr idFr INT NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE strategies DROP FOREIGN KEY FK_611F221321F1620E');
        $this->addSql('ALTER TABLE strategies DROP FOREIGN KEY FK_611F2213FE6E88D7');
        $this->addSql('DROP INDEX IDX_611F221321F1620E ON strategies');
        $this->addSql('DROP INDEX IDX_611F2213FE6E88D7 ON strategies');
        $this->addSql('ALTER TABLE strategies MODIFY idStrategie INT NOT NULL');
        $this->addSql('ALTER TABLE strategies ADD versions INT DEFAULT 1 NOT NULL, CHANGE idStrategie idStrategie INT NOT NULL, CHANGE statusStrategie statusStrategie ENUM(\'En_cours\', \'Acceptée\', \'Refusée\', \'En_attente\', \'Non_affectée\') DEFAULT \'En_attente\' NOT NULL, CHANGE CreatedAtS CreatedAtS DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE nomStrategie nomStrategie VARCHAR(50) NOT NULL, CHANGE justification justification VARCHAR(500) DEFAULT NULL, CHANGE DureeTerme DureeTerme INT DEFAULT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE swot_item DROP FOREIGN KEY FK_E0E3A2F43AE1B9BD');
        $this->addSql('DROP INDEX IDX_E0E3A2F43AE1B9BD ON swot_item');
        $this->addSql('ALTER TABLE swot_item MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE swot_item CHANGE id id INT NOT NULL, CHANGE description description TEXT NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25166D1F9C');
        $this->addSql('DROP INDEX IDX_527EDB25166D1F9C ON task');
        $this->addSql('ALTER TABLE task MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE task CHANGE id id INT NOT NULL, CHANGE status status ENUM(\'TODO\', \'IN_PROGRESS\', \'DONE\') DEFAULT \'TODO\' NOT NULL, CHANGE weight weight INT DEFAULT 1 NOT NULL, CHANGE duration_days duration_days INT DEFAULT 1 NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE project_id project_id INT NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE user MODIFY idUser INT NOT NULL');
        $this->addSql('ALTER TABLE user DROP interests, CHANGE idUser idUser INT NOT NULL, CHANGE cin cin VARCHAR(20) DEFAULT NULL, CHANGE EmailUser EmailUser VARCHAR(190) NOT NULL, CHANGE nomUser nomUser VARCHAR(100) NOT NULL, CHANGE PrenomUser PrenomUser VARCHAR(100) NOT NULL, CHANGE NumTelUser NumTelUser VARCHAR(30) DEFAULT NULL, CHANGE roleUser roleUser ENUM(\'client\', \'gerant\', \'admin\') DEFAULT \'client\' NOT NULL, CHANGE expertiseAreaUser expertiseAreaUser VARCHAR(150) DEFAULT NULL, CHANGE createdAt createdAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updatedAt updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE totp_secret totp_secret VARCHAR(64) DEFAULT NULL, CHANGE totp_enabled totp_enabled TINYINT DEFAULT 0 NOT NULL, CHANGE failed_login_count failed_login_count INT DEFAULT 0 NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE userlog DROP FOREIGN KEY FK_4E107E60A76ED395');
        $this->addSql('DROP INDEX IDX_4E107E60A76ED395 ON userlog');
        $this->addSql('ALTER TABLE userlog MODIFY id_log INT NOT NULL');
        $this->addSql('ALTER TABLE userlog CHANGE id_log id_log INT NOT NULL, CHANGE dateLog dateLog DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE ip_address ip_address VARCHAR(45) DEFAULT NULL, CHANGE user_id user_id INT NOT NULL, DROP PRIMARY KEY');
    }
}
