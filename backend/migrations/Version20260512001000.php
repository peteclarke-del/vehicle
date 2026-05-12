<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stock_items table for general stock ledger';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'mysql' || $platform === 'mariadb') {
            $this->addSql('CREATE TABLE stock_items (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, item_type VARCHAR(32) NOT NULL, category VARCHAR(200) NOT NULL, quantity NUMERIC(10, 2) DEFAULT 0.00 NOT NULL, supplier VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_STOCK_ITEMS_USER (user_id), INDEX IDX_STOCK_ITEMS_LOOKUP (item_type, category, supplier), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE stock_items ADD CONSTRAINT FK_STOCK_ITEMS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        } elseif ($platform === 'postgresql') {
            $this->addSql('CREATE TABLE stock_items (id SERIAL NOT NULL, user_id INT NOT NULL, item_type VARCHAR(32) NOT NULL, category VARCHAR(200) NOT NULL, quantity NUMERIC(10, 2) DEFAULT 0.00 NOT NULL, supplier VARCHAR(100) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE INDEX IDX_STOCK_ITEMS_USER ON stock_items (user_id)');
            $this->addSql('CREATE INDEX IDX_STOCK_ITEMS_LOOKUP ON stock_items (item_type, category, supplier)');
            $this->addSql('ALTER TABLE stock_items ADD CONSTRAINT FK_STOCK_ITEMS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'mysql' || $platform === 'mariadb') {
            $this->addSql('ALTER TABLE stock_items DROP FOREIGN KEY FK_STOCK_ITEMS_USER');
            $this->addSql('DROP TABLE stock_items');
        } elseif ($platform === 'postgresql') {
            $this->addSql('DROP TABLE stock_items');
        }
    }
}
