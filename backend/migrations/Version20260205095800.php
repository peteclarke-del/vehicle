<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add includes_mot_test_cost column to service_records table
 */
final class Version20260205095800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add includes_mot_test_cost column to service_records for tracking if service total includes MOT test cost';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_records ADD includes_mot_test_cost TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_records DROP includes_mot_test_cost');
    }
}
