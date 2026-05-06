<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251120121315 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, pharmacy_id INT DEFAULT NULL, uidn VARCHAR(255) NOT NULL, identity_document VARCHAR(255) NOT NULL, prescription_files JSON NOT NULL, comment LONGTEXT DEFAULT NULL, enum VARCHAR(255) NOT NULL, withdrawal_type VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, qr_code VARCHAR(255) DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME NOT NULL, INDEX IDX_F5299398A76ED395 (user_id), INDEX IDX_F52993988A94ABE2 (pharmacy_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F52993988A94ABE2 FOREIGN KEY (pharmacy_id) REFERENCES pharmacy (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398A76ED395');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993988A94ABE2');
        $this->addSql('DROP TABLE `order`');
    }
}
