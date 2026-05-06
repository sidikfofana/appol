<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251117171249 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pharmacy ADD city_id INT DEFAULT NULL, DROP city');
        $this->addSql('ALTER TABLE pharmacy ADD CONSTRAINT FK_D6C15C1E8BAC62AF FOREIGN KEY (city_id) REFERENCES city (id)');
        $this->addSql('CREATE INDEX IDX_D6C15C1E8BAC62AF ON pharmacy (city_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pharmacy DROP FOREIGN KEY FK_D6C15C1E8BAC62AF');
        $this->addSql('DROP INDEX IDX_D6C15C1E8BAC62AF ON pharmacy');
        $this->addSql('ALTER TABLE pharmacy ADD city VARCHAR(255) NOT NULL, DROP city_id');
    }
}
