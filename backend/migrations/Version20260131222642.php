<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260131222642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attachments (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT DEFAULT NULL, user_id INT NOT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, file_size INT NOT NULL, uploaded_at DATETIME NOT NULL, entity_type VARCHAR(50) DEFAULT NULL, entity_id INT DEFAULT NULL, description LONGTEXT DEFAULT NULL, storage_path VARCHAR(255) DEFAULT NULL, category VARCHAR(50) DEFAULT NULL, INDEX IDX_47C4FAD6545317D1 (vehicle_id), INDEX IDX_47C4FAD6A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE consumable_types (id INT AUTO_INCREMENT NOT NULL, vehicle_type_id INT NOT NULL, name VARCHAR(100) NOT NULL, unit VARCHAR(50) DEFAULT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_FDE5C7B3DA3FD1FC (vehicle_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE consumables (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, consumable_type_id INT NOT NULL, service_record_id INT DEFAULT NULL, todo_id INT DEFAULT NULL, mot_record_id INT DEFAULT NULL, receipt_attachment_id INT DEFAULT NULL, description VARCHAR(200) DEFAULT NULL, brand VARCHAR(100) DEFAULT NULL, part_number VARCHAR(100) DEFAULT NULL, replacement_interval_miles INT DEFAULT NULL, next_replacement_mileage INT DEFAULT NULL, quantity NUMERIC(8, 2) DEFAULT NULL, last_changed DATE DEFAULT NULL, mileage_at_change INT DEFAULT NULL, cost NUMERIC(10, 2) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, product_url VARCHAR(500) DEFAULT NULL, supplier VARCHAR(100) DEFAULT NULL, INDEX IDX_9B2FDD30545317D1 (vehicle_id), INDEX IDX_9B2FDD3044868F59 (consumable_type_id), INDEX IDX_9B2FDD30156C4F46 (service_record_id), INDEX IDX_9B2FDD30EA1EBC33 (todo_id), INDEX IDX_9B2FDD30B17D92CD (mot_record_id), INDEX IDX_9B2FDD3079F22B74 (receipt_attachment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE fuel_records (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, receipt_attachment_id INT DEFAULT NULL, date DATE NOT NULL, litres NUMERIC(8, 2) NOT NULL, cost NUMERIC(8, 2) NOT NULL, mileage INT NOT NULL, fuel_type VARCHAR(50) DEFAULT NULL, station VARCHAR(200) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_33A12AE0545317D1 (vehicle_id), INDEX IDX_33A12AE079F22B74 (receipt_attachment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE insurance_policies (id INT AUTO_INCREMENT NOT NULL, provider VARCHAR(100) NOT NULL, policy_number VARCHAR(100) DEFAULT NULL, annual_cost NUMERIC(10, 2) DEFAULT NULL, ncd_years INT DEFAULT NULL, start_date DATE DEFAULT NULL, expiry_date DATE DEFAULT NULL, coverage_type VARCHAR(50) DEFAULT NULL, excess NUMERIC(10, 2) DEFAULT NULL, mileage_limit INT DEFAULT NULL, holder_id INT DEFAULT NULL, auto_renewal TINYINT(1) DEFAULT 0 NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE insurance_policy_vehicles (insurance_policy_id INT NOT NULL, vehicle_id INT NOT NULL, INDEX IDX_5D859BD9B55D920C (insurance_policy_id), INDEX IDX_5D859BD9545317D1 (vehicle_id), PRIMARY KEY(insurance_policy_id, vehicle_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE mot_records (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, receipt_attachment_id INT DEFAULT NULL, test_date DATE NOT NULL, result VARCHAR(20) NOT NULL, test_cost NUMERIC(10, 2) NOT NULL, repair_cost NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, mileage INT DEFAULT NULL, test_center VARCHAR(100) DEFAULT NULL, expiry_date DATE DEFAULT NULL, mot_test_number VARCHAR(50) DEFAULT NULL, tester_name VARCHAR(100) DEFAULT NULL, is_retest TINYINT(1) DEFAULT 0 NOT NULL, advisories LONGTEXT DEFAULT NULL, failures LONGTEXT DEFAULT NULL, repair_details LONGTEXT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_F093C1A5545317D1 (vehicle_id), INDEX IDX_F093C1A579F22B74 (receipt_attachment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE part_categories (id INT AUTO_INCREMENT NOT NULL, vehicle_type_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_F86C3ACDA3FD1FC (vehicle_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE parts (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, part_category_id INT DEFAULT NULL, service_record_id INT DEFAULT NULL, todo_id INT DEFAULT NULL, mot_record_id INT DEFAULT NULL, receipt_attachment_id INT DEFAULT NULL, purchase_date DATE NOT NULL, description VARCHAR(200) NOT NULL, name VARCHAR(200) DEFAULT NULL, price NUMERIC(10, 2) DEFAULT NULL, part_number VARCHAR(100) DEFAULT NULL, sku VARCHAR(100) DEFAULT NULL, manufacturer VARCHAR(100) DEFAULT NULL, supplier VARCHAR(100) DEFAULT NULL, quantity INT DEFAULT 1 NOT NULL, warranty_months INT DEFAULT NULL, image_url VARCHAR(500) DEFAULT NULL, cost NUMERIC(10, 2) NOT NULL, installation_date DATE DEFAULT NULL, mileage_at_installation INT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, product_url VARCHAR(500) DEFAULT NULL, INDEX IDX_6940A7FE545317D1 (vehicle_id), INDEX IDX_6940A7FE8E7AEECE (part_category_id), INDEX IDX_6940A7FE156C4F46 (service_record_id), INDEX IDX_6940A7FEEA1EBC33 (todo_id), INDEX IDX_6940A7FEB17D92CD (mot_record_id), INDEX IDX_6940A7FE79F22B74 (receipt_attachment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE refresh_tokens (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, refresh_token VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token), INDEX IDX_9BACE7E1A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reports (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(255) NOT NULL, template_key VARCHAR(255) DEFAULT NULL, payload JSON DEFAULT NULL, vehicle_id INT DEFAULT NULL, generated_at DATETIME NOT NULL, INDEX IDX_F11FA745A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE road_tax (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, start_date DATE DEFAULT NULL, expiry_date DATE DEFAULT NULL, amount NUMERIC(10, 2) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, frequency VARCHAR(10) DEFAULT \'annual\' NOT NULL, sorn TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_BB1D046A545317D1 (vehicle_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE security_features (id INT AUTO_INCREMENT NOT NULL, vehicle_type_id INT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL, INDEX IDX_DCAB5BC1DA3FD1FC (vehicle_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service_items (id INT AUTO_INCREMENT NOT NULL, service_record_id INT NOT NULL, consumable_id INT DEFAULT NULL, part_id INT DEFAULT NULL, type VARCHAR(20) NOT NULL, description VARCHAR(255) DEFAULT NULL, cost NUMERIC(10, 2) NOT NULL, quantity NUMERIC(10, 2) DEFAULT \'1.00\' NOT NULL, INDEX IDX_486C04AA156C4F46 (service_record_id), INDEX IDX_486C04AAA94ADB61 (consumable_id), INDEX IDX_486C04AA4CE34BEC (part_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service_records (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, receipt_attachment_id INT DEFAULT NULL, mot_record_id INT DEFAULT NULL, service_date DATE NOT NULL, service_type VARCHAR(50) NOT NULL, labor_cost NUMERIC(10, 2) NOT NULL, parts_cost NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, consumables_cost NUMERIC(10, 2) DEFAULT NULL, mileage INT DEFAULT NULL, service_provider VARCHAR(100) DEFAULT NULL, work_performed LONGTEXT DEFAULT NULL, additional_costs NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, next_service_date DATE DEFAULT NULL, next_service_mileage INT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_53190ADA545317D1 (vehicle_id), INDEX IDX_53190ADA79F22B74 (receipt_attachment_id), INDEX IDX_53190ADAB17D92CD (mot_record_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE specifications (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, engine_type VARCHAR(100) DEFAULT NULL, displacement VARCHAR(50) DEFAULT NULL, power VARCHAR(50) DEFAULT NULL, torque VARCHAR(50) DEFAULT NULL, compression VARCHAR(50) DEFAULT NULL, bore VARCHAR(50) DEFAULT NULL, stroke VARCHAR(50) DEFAULT NULL, fuel_system VARCHAR(50) DEFAULT NULL, cooling VARCHAR(50) DEFAULT NULL, sparkplug_type VARCHAR(100) DEFAULT NULL, coolant_type VARCHAR(100) DEFAULT NULL, coolant_capacity VARCHAR(50) DEFAULT NULL, gearbox VARCHAR(100) DEFAULT NULL, transmission VARCHAR(100) DEFAULT NULL, final_drive VARCHAR(100) DEFAULT NULL, clutch VARCHAR(100) DEFAULT NULL, engine_oil_type VARCHAR(100) DEFAULT NULL, engine_oil_capacity VARCHAR(50) DEFAULT NULL, transmission_oil_type VARCHAR(100) DEFAULT NULL, transmission_oil_capacity VARCHAR(50) DEFAULT NULL, middle_drive_oil_type VARCHAR(100) DEFAULT NULL, middle_drive_oil_capacity VARCHAR(50) DEFAULT NULL, frame VARCHAR(50) DEFAULT NULL, front_suspension VARCHAR(100) DEFAULT NULL, rear_suspension VARCHAR(100) DEFAULT NULL, static_sag_front VARCHAR(50) DEFAULT NULL, static_sag_rear VARCHAR(50) DEFAULT NULL, front_brakes VARCHAR(100) DEFAULT NULL, rear_brakes VARCHAR(100) DEFAULT NULL, front_tyre VARCHAR(100) DEFAULT NULL, rear_tyre VARCHAR(100) DEFAULT NULL, front_tyre_pressure VARCHAR(50) DEFAULT NULL, rear_tyre_pressure VARCHAR(50) DEFAULT NULL, front_wheel_travel VARCHAR(50) DEFAULT NULL, rear_wheel_travel VARCHAR(50) DEFAULT NULL, wheelbase VARCHAR(100) DEFAULT NULL, seat_height VARCHAR(100) DEFAULT NULL, ground_clearance VARCHAR(100) DEFAULT NULL, dry_weight VARCHAR(100) DEFAULT NULL, wet_weight VARCHAR(100) DEFAULT NULL, fuel_capacity VARCHAR(50) DEFAULT NULL, top_speed VARCHAR(50) DEFAULT NULL, additional_info LONGTEXT DEFAULT NULL, scraped_at DATETIME DEFAULT NULL, source_url VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_BDA8A4B545317D1 (vehicle_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE todos (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, done TINYINT(1) DEFAULT 0 NOT NULL, due_date DATETIME DEFAULT NULL, completed_by DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_CD826255545317D1 (vehicle_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_preferences (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(150) NOT NULL, value LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_402A6F60A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) DEFAULT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, country VARCHAR(2) DEFAULT \'GB\' NOT NULL, password_change_required TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, last_login_at DATETIME DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, is_verified TINYINT(1) DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vehicle_images (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, path VARCHAR(255) NOT NULL, caption VARCHAR(255) DEFAULT NULL, is_primary TINYINT(1) DEFAULT 0 NOT NULL, display_order INT DEFAULT 0 NOT NULL, is_scraped TINYINT(1) DEFAULT 0 NOT NULL, source_url VARCHAR(255) DEFAULT NULL, uploaded_at DATETIME NOT NULL, INDEX IDX_49C1BFB9545317D1 (vehicle_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vehicle_makes (id INT AUTO_INCREMENT NOT NULL, vehicle_type_id INT NOT NULL, name VARCHAR(100) NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_D3B1CFCEDA3FD1FC (vehicle_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vehicle_models (id INT AUTO_INCREMENT NOT NULL, make_id INT NOT NULL, vehicle_type_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, start_year INT DEFAULT NULL, end_year INT DEFAULT NULL, image_url VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_4D0831DACFBF73EB (make_id), INDEX IDX_4D0831DADA3FD1FC (vehicle_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vehicle_status_histories (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, user_id INT DEFAULT NULL, old_status VARCHAR(20) NOT NULL, new_status VARCHAR(20) NOT NULL, change_date DATETIME NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_259D9C78545317D1 (vehicle_id), INDEX IDX_259D9C78A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vehicle_types (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vehicles (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, vehicle_type_id INT NOT NULL, name VARCHAR(100) NOT NULL, make VARCHAR(50) DEFAULT NULL, model VARCHAR(50) DEFAULT NULL, year INT DEFAULT NULL, vin VARCHAR(17) DEFAULT NULL, vin_decoded_data JSON DEFAULT NULL, vin_decoded_at DATETIME DEFAULT NULL, registration_number VARCHAR(20) DEFAULT NULL, engine_number VARCHAR(50) DEFAULT NULL, v5_document_number VARCHAR(50) DEFAULT NULL, purchase_cost NUMERIC(10, 2) NOT NULL, purchase_date DATE NOT NULL, purchase_mileage INT DEFAULT NULL, security_features LONGTEXT DEFAULT NULL, vehicle_color VARCHAR(20) DEFAULT NULL, service_interval_months INT DEFAULT 12 NOT NULL, service_interval_miles INT DEFAULT 4000 NOT NULL, status VARCHAR(20) DEFAULT \'Live\' NOT NULL, depreciation_method VARCHAR(20) DEFAULT \'automotive_standard\' NOT NULL, depreciation_years INT DEFAULT 10 NOT NULL, depreciation_rate NUMERIC(5, 2) DEFAULT \'20.00\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, road_tax_exempt TINYINT(1) DEFAULT NULL, mot_exempt TINYINT(1) DEFAULT NULL, UNIQUE INDEX UNIQ_1FCE69FAB1085141 (vin), INDEX IDX_1FCE69FA7E3C61F9 (owner_id), INDEX IDX_1FCE69FADA3FD1FC (vehicle_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE attachments ADD CONSTRAINT FK_47C4FAD6545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attachments ADD CONSTRAINT FK_47C4FAD6A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consumable_types ADD CONSTRAINT FK_FDE5C7B3DA3FD1FC FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types (id)');
        $this->addSql('ALTER TABLE consumables ADD CONSTRAINT FK_9B2FDD30545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consumables ADD CONSTRAINT FK_9B2FDD3044868F59 FOREIGN KEY (consumable_type_id) REFERENCES consumable_types (id)');
        $this->addSql('ALTER TABLE consumables ADD CONSTRAINT FK_9B2FDD30156C4F46 FOREIGN KEY (service_record_id) REFERENCES service_records (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE consumables ADD CONSTRAINT FK_9B2FDD30EA1EBC33 FOREIGN KEY (todo_id) REFERENCES todos (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE consumables ADD CONSTRAINT FK_9B2FDD30B17D92CD FOREIGN KEY (mot_record_id) REFERENCES mot_records (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE consumables ADD CONSTRAINT FK_9B2FDD3079F22B74 FOREIGN KEY (receipt_attachment_id) REFERENCES attachments (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fuel_records ADD CONSTRAINT FK_33A12AE0545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fuel_records ADD CONSTRAINT FK_33A12AE079F22B74 FOREIGN KEY (receipt_attachment_id) REFERENCES attachments (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE insurance_policy_vehicles ADD CONSTRAINT FK_5D859BD9B55D920C FOREIGN KEY (insurance_policy_id) REFERENCES insurance_policies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE insurance_policy_vehicles ADD CONSTRAINT FK_5D859BD9545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mot_records ADD CONSTRAINT FK_F093C1A5545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mot_records ADD CONSTRAINT FK_F093C1A579F22B74 FOREIGN KEY (receipt_attachment_id) REFERENCES attachments (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE part_categories ADD CONSTRAINT FK_F86C3ACDA3FD1FC FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types (id)');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FE545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FE8E7AEECE FOREIGN KEY (part_category_id) REFERENCES part_categories (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FE156C4F46 FOREIGN KEY (service_record_id) REFERENCES service_records (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FEEA1EBC33 FOREIGN KEY (todo_id) REFERENCES todos (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FEB17D92CD FOREIGN KEY (mot_record_id) REFERENCES mot_records (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FE79F22B74 FOREIGN KEY (receipt_attachment_id) REFERENCES attachments (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_9BACE7E1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_F11FA745A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE road_tax ADD CONSTRAINT FK_BB1D046A545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE security_features ADD CONSTRAINT FK_DCAB5BC1DA3FD1FC FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_items ADD CONSTRAINT FK_486C04AA156C4F46 FOREIGN KEY (service_record_id) REFERENCES service_records (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_items ADD CONSTRAINT FK_486C04AAA94ADB61 FOREIGN KEY (consumable_id) REFERENCES consumables (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE service_items ADD CONSTRAINT FK_486C04AA4CE34BEC FOREIGN KEY (part_id) REFERENCES parts (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE service_records ADD CONSTRAINT FK_53190ADA545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_records ADD CONSTRAINT FK_53190ADA79F22B74 FOREIGN KEY (receipt_attachment_id) REFERENCES attachments (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE service_records ADD CONSTRAINT FK_53190ADAB17D92CD FOREIGN KEY (mot_record_id) REFERENCES mot_records (id)');
        $this->addSql('ALTER TABLE specifications ADD CONSTRAINT FK_BDA8A4B545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE todos ADD CONSTRAINT FK_CD826255545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_preferences ADD CONSTRAINT FK_402A6F60A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_images ADD CONSTRAINT FK_49C1BFB9545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_makes ADD CONSTRAINT FK_D3B1CFCEDA3FD1FC FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types (id)');
        $this->addSql('ALTER TABLE vehicle_models ADD CONSTRAINT FK_4D0831DACFBF73EB FOREIGN KEY (make_id) REFERENCES vehicle_makes (id)');
        $this->addSql('ALTER TABLE vehicle_models ADD CONSTRAINT FK_4D0831DADA3FD1FC FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types (id)');
        $this->addSql('ALTER TABLE vehicle_status_histories ADD CONSTRAINT FK_259D9C78545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_status_histories ADD CONSTRAINT FK_259D9C78A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE vehicles ADD CONSTRAINT FK_1FCE69FA7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE vehicles ADD CONSTRAINT FK_1FCE69FADA3FD1FC FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attachments DROP FOREIGN KEY FK_47C4FAD6545317D1');
        $this->addSql('ALTER TABLE attachments DROP FOREIGN KEY FK_47C4FAD6A76ED395');
        $this->addSql('ALTER TABLE consumable_types DROP FOREIGN KEY FK_FDE5C7B3DA3FD1FC');
        $this->addSql('ALTER TABLE consumables DROP FOREIGN KEY FK_9B2FDD30545317D1');
        $this->addSql('ALTER TABLE consumables DROP FOREIGN KEY FK_9B2FDD3044868F59');
        $this->addSql('ALTER TABLE consumables DROP FOREIGN KEY FK_9B2FDD30156C4F46');
        $this->addSql('ALTER TABLE consumables DROP FOREIGN KEY FK_9B2FDD30EA1EBC33');
        $this->addSql('ALTER TABLE consumables DROP FOREIGN KEY FK_9B2FDD30B17D92CD');
        $this->addSql('ALTER TABLE consumables DROP FOREIGN KEY FK_9B2FDD3079F22B74');
        $this->addSql('ALTER TABLE fuel_records DROP FOREIGN KEY FK_33A12AE0545317D1');
        $this->addSql('ALTER TABLE fuel_records DROP FOREIGN KEY FK_33A12AE079F22B74');
        $this->addSql('ALTER TABLE insurance_policy_vehicles DROP FOREIGN KEY FK_5D859BD9B55D920C');
        $this->addSql('ALTER TABLE insurance_policy_vehicles DROP FOREIGN KEY FK_5D859BD9545317D1');
        $this->addSql('ALTER TABLE mot_records DROP FOREIGN KEY FK_F093C1A5545317D1');
        $this->addSql('ALTER TABLE mot_records DROP FOREIGN KEY FK_F093C1A579F22B74');
        $this->addSql('ALTER TABLE part_categories DROP FOREIGN KEY FK_F86C3ACDA3FD1FC');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY FK_6940A7FE545317D1');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY FK_6940A7FE8E7AEECE');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY FK_6940A7FE156C4F46');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY FK_6940A7FEEA1EBC33');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY FK_6940A7FEB17D92CD');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY FK_6940A7FE79F22B74');
        $this->addSql('ALTER TABLE refresh_tokens DROP FOREIGN KEY FK_9BACE7E1A76ED395');
        $this->addSql('ALTER TABLE reports DROP FOREIGN KEY FK_F11FA745A76ED395');
        $this->addSql('ALTER TABLE road_tax DROP FOREIGN KEY FK_BB1D046A545317D1');
        $this->addSql('ALTER TABLE security_features DROP FOREIGN KEY FK_DCAB5BC1DA3FD1FC');
        $this->addSql('ALTER TABLE service_items DROP FOREIGN KEY FK_486C04AA156C4F46');
        $this->addSql('ALTER TABLE service_items DROP FOREIGN KEY FK_486C04AAA94ADB61');
        $this->addSql('ALTER TABLE service_items DROP FOREIGN KEY FK_486C04AA4CE34BEC');
        $this->addSql('ALTER TABLE service_records DROP FOREIGN KEY FK_53190ADA545317D1');
        $this->addSql('ALTER TABLE service_records DROP FOREIGN KEY FK_53190ADA79F22B74');
        $this->addSql('ALTER TABLE service_records DROP FOREIGN KEY FK_53190ADAB17D92CD');
        $this->addSql('ALTER TABLE specifications DROP FOREIGN KEY FK_BDA8A4B545317D1');
        $this->addSql('ALTER TABLE todos DROP FOREIGN KEY FK_CD826255545317D1');
        $this->addSql('ALTER TABLE user_preferences DROP FOREIGN KEY FK_402A6F60A76ED395');
        $this->addSql('ALTER TABLE vehicle_images DROP FOREIGN KEY FK_49C1BFB9545317D1');
        $this->addSql('ALTER TABLE vehicle_makes DROP FOREIGN KEY FK_D3B1CFCEDA3FD1FC');
        $this->addSql('ALTER TABLE vehicle_models DROP FOREIGN KEY FK_4D0831DACFBF73EB');
        $this->addSql('ALTER TABLE vehicle_models DROP FOREIGN KEY FK_4D0831DADA3FD1FC');
        $this->addSql('ALTER TABLE vehicle_status_histories DROP FOREIGN KEY FK_259D9C78545317D1');
        $this->addSql('ALTER TABLE vehicle_status_histories DROP FOREIGN KEY FK_259D9C78A76ED395');
        $this->addSql('ALTER TABLE vehicles DROP FOREIGN KEY FK_1FCE69FA7E3C61F9');
        $this->addSql('ALTER TABLE vehicles DROP FOREIGN KEY FK_1FCE69FADA3FD1FC');
        $this->addSql('DROP TABLE attachments');
        $this->addSql('DROP TABLE consumable_types');
        $this->addSql('DROP TABLE consumables');
        $this->addSql('DROP TABLE fuel_records');
        $this->addSql('DROP TABLE insurance_policies');
        $this->addSql('DROP TABLE insurance_policy_vehicles');
        $this->addSql('DROP TABLE mot_records');
        $this->addSql('DROP TABLE part_categories');
        $this->addSql('DROP TABLE parts');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE reports');
        $this->addSql('DROP TABLE road_tax');
        $this->addSql('DROP TABLE security_features');
        $this->addSql('DROP TABLE service_items');
        $this->addSql('DROP TABLE service_records');
        $this->addSql('DROP TABLE specifications');
        $this->addSql('DROP TABLE todos');
        $this->addSql('DROP TABLE user_preferences');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE vehicle_images');
        $this->addSql('DROP TABLE vehicle_makes');
        $this->addSql('DROP TABLE vehicle_models');
        $this->addSql('DROP TABLE vehicle_status_histories');
        $this->addSql('DROP TABLE vehicle_types');
        $this->addSql('DROP TABLE vehicles');
    }
}
