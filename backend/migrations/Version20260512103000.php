<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove mileage_at_installation from stock_items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_items DROP mileage_at_installation');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_items ADD mileage_at_installation INT DEFAULT NULL');
    }
}
