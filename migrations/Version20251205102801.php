<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251205102801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pharmacy ADD company_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pharmacy ADD CONSTRAINT FK_D6C15C1E979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)');
        $this->addSql('CREATE INDEX IDX_D6C15C1E979B1AD6 ON pharmacy (company_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pharmacy DROP FOREIGN KEY FK_D6C15C1E979B1AD6');
        $this->addSql('DROP INDEX IDX_D6C15C1E979B1AD6 ON pharmacy');
        $this->addSql('ALTER TABLE pharmacy DROP company_id');
    }
}
