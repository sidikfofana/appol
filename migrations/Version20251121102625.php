<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251121102625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_item ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F09A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_52EA1F09A76ED395 ON order_item (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D6C15C1E77153098 ON pharmacy (code)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F09A76ED395');
        $this->addSql('DROP INDEX IDX_52EA1F09A76ED395 ON order_item');
        $this->addSql('ALTER TABLE order_item DROP user_id');
        $this->addSql('DROP INDEX UNIQ_D6C15C1E77153098 ON pharmacy');
    }
}
