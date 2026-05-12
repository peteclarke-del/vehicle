<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add vehicle_type_id to stock_items for vehicle-type-scoped stock';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_items ADD vehicle_type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_items ADD CONSTRAINT FK_4F1A2A68F46DCC53 FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_4F1A2A68F46DCC53 ON stock_items (vehicle_type_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_4F1A2A68F46DCC53 ON stock_items');
        $this->addSql('ALTER TABLE stock_items DROP FOREIGN KEY FK_4F1A2A68F46DCC53');
        $this->addSql('ALTER TABLE stock_items DROP vehicle_type_id');
    }
}
