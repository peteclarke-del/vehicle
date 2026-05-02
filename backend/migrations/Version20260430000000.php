<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260430000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change parts.quantity from integer to decimal(8,2) to support fractional quantities';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parts MODIFY COLUMN quantity DECIMAL(8,2) NOT NULL DEFAULT 1.00');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parts MODIFY COLUMN quantity INT NOT NULL DEFAULT 1');
    }
}
