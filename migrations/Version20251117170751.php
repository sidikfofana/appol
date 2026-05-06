<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251117170751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pharmacy (id INT AUTO_INCREMENT NOT NULL, country_id INT NOT NULL, owner_id INT NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(255) DEFAULT NULL, city VARCHAR(255) NOT NULL, is_online LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', opening_day LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', INDEX IDX_D6C15C1EF92F3E70 (country_id), UNIQUE INDEX UNIQ_D6C15C1E7E3C61F9 (owner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pharmacy ADD CONSTRAINT FK_D6C15C1EF92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
        $this->addSql('ALTER TABLE pharmacy ADD CONSTRAINT FK_D6C15C1E7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pharmacy DROP FOREIGN KEY FK_D6C15C1EF92F3E70');
        $this->addSql('ALTER TABLE pharmacy DROP FOREIGN KEY FK_D6C15C1E7E3C61F9');
        $this->addSql('DROP TABLE pharmacy');
    }
}
