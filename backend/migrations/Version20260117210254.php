<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260117210254 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attachments (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT DEFAULT NULL, service_record_id INT DEFAULT NULL, user_id INT NOT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, thumbnail_path VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(100) NOT NULL, file_size INT NOT NULL, uploaded_at DATETIME NOT NULL, entity_type VARCHAR(50) DEFAULT NULL, entity_id INT DEFAULT NULL, description LONGTEXT DEFAULT NULL, storage_path VARCHAR(255) DEFAULT NULL, category VARCHAR(50) DEFAULT NULL, virus_scan_status VARCHAR(20) DEFAULT \'pending\', virus_scan_date DATETIME DEFAULT NULL, INDEX IDX_47C4FAD6545317D1 (vehicle_id), INDEX IDX_47C4FAD6156C4F46 (service_record_id), INDEX IDX_47C4FAD6A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE consumable_types (id INT AUTO_INCREMENT NOT NULL, vehicle_type_id INT NOT NULL, name VARCHAR(100) NOT NULL, unit VARCHAR(50) DEFAULT NULL, description LONGTEXT DEFAULT NULL, category VARCHAR(50) DEFAULT NULL, default_interval_miles INT DEFAULT NULL, default_interval_months INT DEFAULT NULL, typical_cost NUMERIC(10, 2) DEFAULT NULL, icon_name VARCHAR(50) DEFAULT NULL, is_common TINYINT(1) DEFAULT 0 NOT NULL, requires_specialization TINYINT(1) DEFAULT 0 NOT NULL, manufacturer_recommendation LONGTEXT DEFAULT NULL, INDEX IDX_FDE5C7B3DA3FD1FC (vehicle_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE consumables (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, consumable_type_id INT NOT NULL, service_record_id INT DEFAULT NULL, mot_record_id INT DEFAULT NULL, specification VARCHAR(200) NOT NULL, name VARCHAR(200) DEFAULT NULL, brand VARCHAR(100) DEFAULT NULL, part_number VARCHAR(100) DEFAULT NULL, replacement_interval_miles INT DEFAULT NULL, next_replacement_mileage INT DEFAULT NULL, quantity NUMERIC(8, 2) DEFAULT NULL, last_changed DATE NOT NULL, mileage_at_change INT DEFAULT NULL, cost NUMERIC(10, 2) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, receipt_attachment_id INT DEFAULT NULL, product_url VARCHAR(500) DEFAULT NULL, supplier VARCHAR(100) DEFAULT NULL, INDEX IDX_9B2FDD30545317D1 (vehicle_id), INDEX IDX_9B2FDD3044868F59 (consumable_type_id), INDEX IDX_9B2FDD30156C4F46 (service_record_id), INDEX IDX_9B2FDD30B17D92CD (mot_record_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE fuel_records (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, date DATE NOT NULL, litres NUMERIC(8, 2) NOT NULL, cost NUMERIC(8, 2) NOT NULL, mileage INT NOT NULL, fuel_type VARCHAR(50) DEFAULT NULL, full_tank TINYINT(1) DEFAULT 1 NOT NULL, payment_method VARCHAR(50) DEFAULT NULL, trip_computer_mpg NUMERIC(8, 2) DEFAULT NULL, station VARCHAR(200) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, receipt_attachment_id INT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_33A12AE0545317D1 (vehicle_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE insurance (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, provider VARCHAR(100) NOT NULL, policy_number VARCHAR(100) DEFAULT NULL, coverage_type VARCHAR(50) NOT NULL, cover_type VARCHAR(50) DEFAULT NULL, annual_cost NUMERIC(10, 2) NOT NULL, excess NUMERIC(10, 2) DEFAULT NULL, ncd_years INT DEFAULT NULL, mileage_limit INT DEFAULT NULL, auto_renewal TINYINT(1) DEFAULT 0 NOT NULL, start_date DATE NOT NULL, expiry_date DATE NOT NULL, end_date DATE DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_640EAF4C545317D1 (vehicle_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE mot_records (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, test_date DATE NOT NULL, result VARCHAR(20) NOT NULL, test_cost NUMERIC(10, 2) NOT NULL, repair_cost NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, mileage INT DEFAULT NULL, test_center VARCHAR(100) DEFAULT NULL, expiry_date DATE DEFAULT NULL, mot_test_number VARCHAR(50) DEFAULT NULL, tester_name VARCHAR(100) DEFAULT NULL, is_retest TINYINT(1) DEFAULT 0 NOT NULL, advisories LONGTEXT DEFAULT NULL, failures LONGTEXT DEFAULT NULL, repair_details LONGTEXT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_F093C1A5545317D1 (vehicle_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE parts (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, service_record_id INT DEFAULT NULL, mot_record_id INT DEFAULT NULL, purchase_date DATE NOT NULL, description VARCHAR(200) NOT NULL, name VARCHAR(200) DEFAULT NULL, price NUMERIC(10, 2) DEFAULT NULL, part_number VARCHAR(100) DEFAULT NULL, sku VARCHAR(100) DEFAULT NULL, manufacturer VARCHAR(100) DEFAULT NULL, supplier VARCHAR(100) DEFAULT NULL, quantity INT DEFAULT 1 NOT NULL, warranty_months INT DEFAULT NULL, image_url VARCHAR(500) DEFAULT NULL, cost NUMERIC(10, 2) NOT NULL, category VARCHAR(50) NOT NULL, installation_date DATE DEFAULT NULL, mileage_at_installation INT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, receipt_attachment_id INT DEFAULT NULL, product_url VARCHAR(500) DEFAULT NULL, INDEX IDX_6940A7FE545317D1 (vehicle_id), INDEX IDX_6940A7FE156C4F46 (service_record_id), INDEX IDX_6940A7FEB17D92CD (mot_record_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service_records (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, service_date DATE NOT NULL, service_type VARCHAR(50) NOT NULL, labor_cost NUMERIC(10, 2) NOT NULL, parts_cost NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, mileage INT DEFAULT NULL, service_provider VARCHAR(100) DEFAULT NULL, workshop VARCHAR(100) DEFAULT NULL, work_performed LONGTEXT DEFAULT NULL, description LONGTEXT DEFAULT NULL, additional_costs NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, next_service_date DATE DEFAULT NULL, next_service_mileage INT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, receipt_attachment_id INT DEFAULT NULL, INDEX IDX_53190ADA545317D1 (vehicle_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) DEFAULT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, preferred_language VARCHAR(10) DEFAULT \'en\' NOT NULL, theme VARCHAR(20) DEFAULT \'light\' NOT NULL, session_timeout INT DEFAULT 3600 NOT NULL, distance_unit VARCHAR(10) DEFAULT \'miles\' NOT NULL, password_change_required TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, last_login_at DATETIME DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, is_verified TINYINT(1) DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vehicle_makes (id INT AUTO_INCREMENT NOT NULL, vehicle_type_id INT NOT NULL, name VARCHAR(100) NOT NULL, logo_url VARCHAR(255) DEFAULT NULL, country_of_origin VARCHAR(100) DEFAULT NULL, founded_year INT DEFAULT NULL, headquarters VARCHAR(100) DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, popularity INT DEFAULT 0 NOT NULL, INDEX IDX_D3B1CFCEDA3FD1FC (vehicle_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vehicle_models (id INT AUTO_INCREMENT NOT NULL, make_id INT NOT NULL, vehicle_type_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, start_year INT DEFAULT NULL, end_year INT DEFAULT NULL, production_start_year INT DEFAULT NULL, production_end_year INT DEFAULT NULL, image_url VARCHAR(255) DEFAULT NULL, engine_options JSON DEFAULT NULL, transmission_options JSON DEFAULT NULL, generation_count INT DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_4D0831DACFBF73EB (make_id), INDEX IDX_4D0831DADA3FD1FC (vehicle_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vehicle_types (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, category VARCHAR(50) DEFAULT NULL, description LONGTEXT DEFAULT NULL, typical_seating_capacity INT DEFAULT NULL, typical_doors INT DEFAULT NULL, icon_name VARCHAR(50) DEFAULT NULL, is_popular TINYINT(1) DEFAULT 0 NOT NULL, avg_insurance_group INT DEFAULT NULL, fuel_efficiency_rating VARCHAR(10) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vehicles (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, vehicle_type_id INT NOT NULL, name VARCHAR(100) NOT NULL, make VARCHAR(50) DEFAULT NULL, model VARCHAR(50) DEFAULT NULL, year INT DEFAULT NULL, vin VARCHAR(17) DEFAULT NULL, registration_number VARCHAR(20) DEFAULT NULL, engine_number VARCHAR(50) DEFAULT NULL, v5_document_number VARCHAR(50) DEFAULT NULL, purchase_cost NUMERIC(10, 2) NOT NULL, purchase_date DATE NOT NULL, current_mileage INT DEFAULT NULL, last_service_date DATE DEFAULT NULL, mot_expiry_date DATE DEFAULT NULL, road_tax_expiry_date DATE DEFAULT NULL, insurance_expiry_date DATE DEFAULT NULL, security_features LONGTEXT DEFAULT NULL, vehicle_color VARCHAR(20) DEFAULT NULL, service_interval_months INT DEFAULT 12 NOT NULL, service_interval_miles INT DEFAULT 4000 NOT NULL, depreciation_method VARCHAR(20) DEFAULT \'declining_balance\' NOT NULL, depreciation_years INT DEFAULT 10 NOT NULL, depreciation_rate NUMERIC(5, 2) DEFAULT \'5.00\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1FCE69FAB1085141 (vin), INDEX IDX_1FCE69FA7E3C61F9 (owner_id), INDEX IDX_1FCE69FADA3FD1FC (vehicle_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE attachments ADD CONSTRAINT FK_47C4FAD6545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attachments ADD CONSTRAINT FK_47C4FAD6156C4F46 FOREIGN KEY (service_record_id) REFERENCES service_records (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attachments ADD CONSTRAINT FK_47C4FAD6A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consumable_types ADD CONSTRAINT FK_FDE5C7B3DA3FD1FC FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types (id)');
        $this->addSql('ALTER TABLE consumables ADD CONSTRAINT FK_9B2FDD30545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id)');
        $this->addSql('ALTER TABLE consumables ADD CONSTRAINT FK_9B2FDD3044868F59 FOREIGN KEY (consumable_type_id) REFERENCES consumable_types (id)');
        $this->addSql('ALTER TABLE consumables ADD CONSTRAINT FK_9B2FDD30156C4F46 FOREIGN KEY (service_record_id) REFERENCES service_records (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE consumables ADD CONSTRAINT FK_9B2FDD30B17D92CD FOREIGN KEY (mot_record_id) REFERENCES mot_records (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fuel_records ADD CONSTRAINT FK_33A12AE0545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id)');
        $this->addSql('ALTER TABLE insurance ADD CONSTRAINT FK_640EAF4C545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mot_records ADD CONSTRAINT FK_F093C1A5545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FE545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id)');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FE156C4F46 FOREIGN KEY (service_record_id) REFERENCES service_records (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FEB17D92CD FOREIGN KEY (mot_record_id) REFERENCES mot_records (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE service_records ADD CONSTRAINT FK_53190ADA545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_makes ADD CONSTRAINT FK_D3B1CFCEDA3FD1FC FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types (id)');
        $this->addSql('ALTER TABLE vehicle_models ADD CONSTRAINT FK_4D0831DACFBF73EB FOREIGN KEY (make_id) REFERENCES vehicle_makes (id)');
        $this->addSql('ALTER TABLE vehicle_models ADD CONSTRAINT FK_4D0831DADA3FD1FC FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types (id)');
        $this->addSql('ALTER TABLE vehicles ADD CONSTRAINT FK_1FCE69FA7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE vehicles ADD CONSTRAINT FK_1FCE69FADA3FD1FC FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attachments DROP FOREIGN KEY FK_47C4FAD6545317D1');
        $this->addSql('ALTER TABLE attachments DROP FOREIGN KEY FK_47C4FAD6156C4F46');
        $this->addSql('ALTER TABLE attachments DROP FOREIGN KEY FK_47C4FAD6A76ED395');
        $this->addSql('ALTER TABLE consumable_types DROP FOREIGN KEY FK_FDE5C7B3DA3FD1FC');
        $this->addSql('ALTER TABLE consumables DROP FOREIGN KEY FK_9B2FDD30545317D1');
        $this->addSql('ALTER TABLE consumables DROP FOREIGN KEY FK_9B2FDD3044868F59');
        $this->addSql('ALTER TABLE consumables DROP FOREIGN KEY FK_9B2FDD30156C4F46');
        $this->addSql('ALTER TABLE consumables DROP FOREIGN KEY FK_9B2FDD30B17D92CD');
        $this->addSql('ALTER TABLE fuel_records DROP FOREIGN KEY FK_33A12AE0545317D1');
        $this->addSql('ALTER TABLE insurance DROP FOREIGN KEY FK_640EAF4C545317D1');
        $this->addSql('ALTER TABLE mot_records DROP FOREIGN KEY FK_F093C1A5545317D1');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY FK_6940A7FE545317D1');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY FK_6940A7FE156C4F46');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY FK_6940A7FEB17D92CD');
        $this->addSql('ALTER TABLE service_records DROP FOREIGN KEY FK_53190ADA545317D1');
        $this->addSql('ALTER TABLE vehicle_makes DROP FOREIGN KEY FK_D3B1CFCEDA3FD1FC');
        $this->addSql('ALTER TABLE vehicle_models DROP FOREIGN KEY FK_4D0831DACFBF73EB');
        $this->addSql('ALTER TABLE vehicle_models DROP FOREIGN KEY FK_4D0831DADA3FD1FC');
        $this->addSql('ALTER TABLE vehicles DROP FOREIGN KEY FK_1FCE69FA7E3C61F9');
        $this->addSql('ALTER TABLE vehicles DROP FOREIGN KEY FK_1FCE69FADA3FD1FC');
        $this->addSql('DROP TABLE attachments');
        $this->addSql('DROP TABLE consumable_types');
        $this->addSql('DROP TABLE consumables');
        $this->addSql('DROP TABLE fuel_records');
        $this->addSql('DROP TABLE insurance');
        $this->addSql('DROP TABLE mot_records');
        $this->addSql('DROP TABLE parts');
        $this->addSql('DROP TABLE service_records');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE vehicle_makes');
        $this->addSql('DROP TABLE vehicle_models');
        $this->addSql('DROP TABLE vehicle_types');
        $this->addSql('DROP TABLE vehicles');
    }
}
