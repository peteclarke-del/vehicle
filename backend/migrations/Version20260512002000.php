<?php

/**
 * Migration to add stock item metadata columns.
 */

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * class Version20260512002000
 */
final class Version20260512002000 extends AbstractMigration
{
    /**
     * function getDescription
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Add stock_items metadata columns for description, pricing and product details.';
    }

    /**
     * function up
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'mysql' || $platform === 'mariadb') {
            $this->addSql(
                'ALTER TABLE stock_items ADD description ' .
                'VARCHAR(500) DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD price ' .
                'NUMERIC(10, 2) DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD notes LONGTEXT DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD purchase_date DATE DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD part_number ' .
                'VARCHAR(100) DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD manufacturer ' .
                'VARCHAR(100) DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD warranty ' .
                'VARCHAR(100) DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD mileage_at_installation INT ' .
                'DEFAULT NULL'
            );
        } elseif ($platform === 'postgresql') {
            $this->addSql(
                'ALTER TABLE stock_items ADD description VARCHAR(500) DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD price NUMERIC(10, 2) DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD notes TEXT DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD purchase_date DATE DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD part_number VARCHAR(100) DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD manufacturer VARCHAR(100) DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD warranty VARCHAR(100) DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD mileage_at_installation INT DEFAULT NULL'
            );
        } else {
            $this->addSql(
                'ALTER TABLE stock_items ADD COLUMN description ' .
                'VARCHAR(500) DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD COLUMN price ' .
                'NUMERIC(10, 2) DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD COLUMN notes TEXT DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD COLUMN purchase_date DATE DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD COLUMN part_number ' .
                'VARCHAR(100) DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD COLUMN manufacturer ' .
                'VARCHAR(100) DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD COLUMN warranty ' .
                'VARCHAR(100) DEFAULT NULL'
            );
            $this->addSql(
                'ALTER TABLE stock_items ADD COLUMN mileage_at_installation INT ' .
                'DEFAULT NULL'
            );
        }
    }

    /**
     * function down
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'mysql' || $platform === 'mariadb') {
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN description'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN price'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN notes'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN purchase_date'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN part_number'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN manufacturer'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN warranty'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN mileage_at_installation'
            );
        } elseif ($platform === 'postgresql') {
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN description'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN price'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN notes'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN purchase_date'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN part_number'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN manufacturer'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN warranty'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN mileage_at_installation'
            );
        } else {
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN description'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN price'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN notes'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN purchase_date'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN part_number'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN manufacturer'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN warranty'
            );
            $this->addSql(
                'ALTER TABLE stock_items DROP COLUMN mileage_at_installation'
            );
        }
    }
}