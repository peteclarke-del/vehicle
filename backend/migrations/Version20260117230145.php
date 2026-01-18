<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260117230145 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE specifications (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, engine_type VARCHAR(100) DEFAULT NULL, displacement VARCHAR(50) DEFAULT NULL, power VARCHAR(50) DEFAULT NULL, torque VARCHAR(50) DEFAULT NULL, compression VARCHAR(50) DEFAULT NULL, bore VARCHAR(50) DEFAULT NULL, stroke VARCHAR(50) DEFAULT NULL, fuel_system VARCHAR(50) DEFAULT NULL, cooling VARCHAR(50) DEFAULT NULL, gearbox VARCHAR(100) DEFAULT NULL, transmission VARCHAR(100) DEFAULT NULL, clutch VARCHAR(100) DEFAULT NULL, frame VARCHAR(50) DEFAULT NULL, front_suspension VARCHAR(100) DEFAULT NULL, rear_suspension VARCHAR(100) DEFAULT NULL, front_brakes VARCHAR(100) DEFAULT NULL, rear_brakes VARCHAR(100) DEFAULT NULL, front_tyre VARCHAR(100) DEFAULT NULL, rear_tyre VARCHAR(100) DEFAULT NULL, front_wheel_travel VARCHAR(50) DEFAULT NULL, rear_wheel_travel VARCHAR(50) DEFAULT NULL, wheelbase VARCHAR(50) DEFAULT NULL, seat_height VARCHAR(50) DEFAULT NULL, ground_clearance VARCHAR(50) DEFAULT NULL, dry_weight VARCHAR(50) DEFAULT NULL, wet_weight VARCHAR(50) DEFAULT NULL, fuel_capacity VARCHAR(50) DEFAULT NULL, top_speed VARCHAR(50) DEFAULT NULL, additional_info LONGTEXT DEFAULT NULL, scraped_at DATETIME DEFAULT NULL, source_url VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_BDA8A4B545317D1 (vehicle_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE specifications ADD CONSTRAINT FK_BDA8A4B545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE specifications DROP FOREIGN KEY FK_BDA8A4B545317D1');
        $this->addSql('DROP TABLE specifications');
    }
}
