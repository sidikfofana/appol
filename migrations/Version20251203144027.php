<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251203144027 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, order_id_id INT DEFAULT NULL, payment_id INT DEFAULT NULL, transaction_reference VARCHAR(255) NOT NULL, transaction_status VARCHAR(255) DEFAULT NULL, INDEX IDX_6D28840DFCDAEAAA (order_id_id), INDEX IDX_6D28840D4C3A3BB (payment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DFCDAEAAA FOREIGN KEY (order_id_id) REFERENCES `order` (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment_method (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DFCDAEAAA');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D4C3A3BB');
        $this->addSql('DROP TABLE payment');
    }
}
