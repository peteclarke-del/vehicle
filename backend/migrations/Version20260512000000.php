<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Allow parts and consumables to exist without a vehicle (general stock).
 *
 * - Makes vehicle_id nullable in parts and consumables tables
 * - Adds user_id to parts and consumables for ownership tracking of general stock items
 */
final class Version20260512000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow parts and consumables to be general stock (not tied to a vehicle)';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'mysql' || $platform === 'mariadb') {
            // Make vehicle_id nullable in parts
            $this->addSql('ALTER TABLE parts MODIFY vehicle_id INT NULL');
            // Add user_id to parts
            $this->addSql('ALTER TABLE parts ADD COLUMN user_id INT NULL AFTER vehicle_id');
            $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_PARTS_USER FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
            $this->addSql('CREATE INDEX IDX_PARTS_USER ON parts (user_id)');

            // Make vehicle_id nullable in consumables
            $this->addSql('ALTER TABLE consumables MODIFY vehicle_id INT NULL');
            // Add user_id to consumables
            $this->addSql('ALTER TABLE consumables ADD COLUMN user_id INT NULL AFTER vehicle_id');
            $this->addSql('ALTER TABLE consumables ADD CONSTRAINT FK_CONSUMABLES_USER FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
            $this->addSql('CREATE INDEX IDX_CONSUMABLES_USER ON consumables (user_id)');
        } elseif ($platform === 'postgresql') {
            // Make vehicle_id nullable in parts
            $this->addSql('ALTER TABLE parts ALTER COLUMN vehicle_id DROP NOT NULL');
            // Add user_id to parts
            $this->addSql('ALTER TABLE parts ADD COLUMN user_id INT NULL REFERENCES users(id) ON DELETE CASCADE');
            $this->addSql('CREATE INDEX IDX_PARTS_USER ON parts (user_id)');

            // Make vehicle_id nullable in consumables
            $this->addSql('ALTER TABLE consumables ALTER COLUMN vehicle_id DROP NOT NULL');
            // Add user_id to consumables
            $this->addSql('ALTER TABLE consumables ADD COLUMN user_id INT NULL REFERENCES users(id) ON DELETE CASCADE');
            $this->addSql('CREATE INDEX IDX_CONSUMABLES_USER ON consumables (user_id)');
        } else {
            // SQLite fallback (dev/test environments)
            $this->addSql('CREATE TABLE parts_new AS SELECT * FROM parts');
            $this->addSql('DROP TABLE parts');
            $this->addSql('ALTER TABLE parts_new RENAME TO parts');

            $this->addSql('CREATE TABLE consumables_new AS SELECT * FROM consumables');
            $this->addSql('DROP TABLE consumables');
            $this->addSql('ALTER TABLE consumables_new RENAME TO consumables');
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'mysql' || $platform === 'mariadb') {
            $this->addSql('ALTER TABLE parts DROP FOREIGN KEY FK_PARTS_USER');
            $this->addSql('DROP INDEX IDX_PARTS_USER ON parts');
            $this->addSql('ALTER TABLE parts DROP COLUMN user_id');
            $this->addSql('ALTER TABLE parts MODIFY vehicle_id INT NOT NULL');

            $this->addSql('ALTER TABLE consumables DROP FOREIGN KEY FK_CONSUMABLES_USER');
            $this->addSql('DROP INDEX IDX_CONSUMABLES_USER ON consumables');
            $this->addSql('ALTER TABLE consumables DROP COLUMN user_id');
            $this->addSql('ALTER TABLE consumables MODIFY vehicle_id INT NOT NULL');
        } elseif ($platform === 'postgresql') {
            $this->addSql('DROP INDEX IDX_PARTS_USER');
            $this->addSql('ALTER TABLE parts DROP COLUMN user_id');
            $this->addSql('ALTER TABLE parts ALTER COLUMN vehicle_id SET NOT NULL');

            $this->addSql('DROP INDEX IDX_CONSUMABLES_USER');
            $this->addSql('ALTER TABLE consumables DROP COLUMN user_id');
            $this->addSql('ALTER TABLE consumables ALTER COLUMN vehicle_id SET NOT NULL');
        }
    }
}
