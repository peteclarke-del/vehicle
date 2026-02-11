<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * class Version20260210152631
 *
 * Initial schema creation — database-agnostic (MySQL, PostgreSQL, SQLite).
 * Uses the Doctrine Schema API so that each platform generates the correct DDL
 * (e.g. SERIAL on PostgreSQL, AUTO_INCREMENT on MySQL, etc.).
 */
final class Version20260210152631 extends AbstractMigration
{
    /**
     * function getDescription
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Initial schema creation (database-agnostic)';
    }

    /**
     * function preUp
     *
     * Clean up any remnants from a previous failed migration attempt.
     * Drops all tables in reverse dependency order so the up() method
     * can create them cleanly on any platform.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function preUp(Schema $schema): void
    {
        $tables = [
            'vehicle_status_histories', 'vehicle_images', 'vehicle_assignments',
            'user_preferences', 'user_feature_overrides', 'specifications',
            'service_items', 'parts', 'insurance_policy_vehicles', 'fuel_records',
            'consumables', 'service_records', 'mot_records', 'todos',
            'security_features', 'part_categories', 'vehicle_models', 'vehicle_makes',
            'consumable_types', 'attachments', 'vehicles', 'insurance_policies',
            'feature_flags', 'users', 'vehicle_types', 'refresh_tokens',
            'reports', 'road_tax',
        ];

        $platform = $this->connection->getDatabasePlatform()->getName();

        foreach ($tables as $table) {
            if ($platform === 'postgresql') {
                $this->connection->executeStatement("DROP TABLE IF EXISTS \"{$table}\" CASCADE");
            } else {
                $this->connection->executeStatement("DROP TABLE IF EXISTS `{$table}`");
            }
        }
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
        // ── standalone tables (no FK dependencies) ──────────────────────

        $vehicleTypes = $schema->createTable('vehicle_types');
        $vehicleTypes->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $vehicleTypes->addColumn('name', Types::STRING, ['length' => 50]);
        $vehicleTypes->setPrimaryKey(['id']);

        $users = $schema->createTable('users');
        $users->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $users->addColumn('email', Types::STRING, ['length' => 180]);
        $users->addColumn('roles', Types::JSON);
        $users->addColumn('password', Types::STRING, ['length' => 255, 'notnull' => false]);
        $users->addColumn('first_name', Types::STRING, ['length' => 100]);
        $users->addColumn('last_name', Types::STRING, ['length' => 100]);
        $users->addColumn('country', Types::STRING, ['length' => 2, 'default' => 'GB']);
        $users->addColumn('password_change_required', Types::BOOLEAN, ['default' => false]);
        $users->addColumn('created_at', Types::DATETIME_MUTABLE);
        $users->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $users->addColumn('last_login_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $users->addColumn('is_active', Types::BOOLEAN, ['default' => true]);
        $users->addColumn('is_verified', Types::BOOLEAN, ['default' => false]);
        $users->setPrimaryKey(['id']);
        $users->addUniqueIndex(['email'], 'UNIQ_1483A5E9E7927C74');

        $featureFlags = $schema->createTable('feature_flags');
        $featureFlags->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $featureFlags->addColumn('feature_key', Types::STRING, ['length' => 100]);
        $featureFlags->addColumn('label', Types::STRING, ['length' => 150]);
        $featureFlags->addColumn('description', Types::TEXT, ['notnull' => false]);
        $featureFlags->addColumn('category', Types::STRING, ['length' => 50]);
        $featureFlags->addColumn('default_enabled', Types::BOOLEAN, ['default' => true]);
        $featureFlags->addColumn('sort_order', Types::INTEGER, ['default' => 0]);
        $featureFlags->addColumn('created_at', Types::DATETIME_MUTABLE);
        $featureFlags->setPrimaryKey(['id']);
        $featureFlags->addUniqueIndex(['feature_key'], 'UNIQ_C25B4BB3C8FDEE1A');

        $insurancePolicies = $schema->createTable('insurance_policies');
        $insurancePolicies->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $insurancePolicies->addColumn('provider', Types::STRING, ['length' => 100]);
        $insurancePolicies->addColumn('policy_number', Types::STRING, ['length' => 100, 'notnull' => false]);
        $insurancePolicies->addColumn('annual_cost', Types::DECIMAL, ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $insurancePolicies->addColumn('ncd_years', Types::INTEGER, ['notnull' => false]);
        $insurancePolicies->addColumn('start_date', Types::DATE_MUTABLE, ['notnull' => false]);
        $insurancePolicies->addColumn('expiry_date', Types::DATE_MUTABLE, ['notnull' => false]);
        $insurancePolicies->addColumn('coverage_type', Types::STRING, ['length' => 50, 'notnull' => false]);
        $insurancePolicies->addColumn('excess', Types::DECIMAL, ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $insurancePolicies->addColumn('mileage_limit', Types::INTEGER, ['notnull' => false]);
        $insurancePolicies->addColumn('holder_id', Types::INTEGER, ['notnull' => false]);
        $insurancePolicies->addColumn('auto_renewal', Types::BOOLEAN, ['default' => false]);
        $insurancePolicies->addColumn('notes', Types::TEXT, ['notnull' => false]);
        $insurancePolicies->addColumn('created_at', Types::DATETIME_MUTABLE);
        $insurancePolicies->setPrimaryKey(['id']);

        // ── vehicles (FK → users, vehicle_types) ────────────────────────

        $vehicles = $schema->createTable('vehicles');
        $vehicles->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $vehicles->addColumn('owner_id', Types::INTEGER);
        $vehicles->addColumn('vehicle_type_id', Types::INTEGER);
        $vehicles->addColumn('name', Types::STRING, ['length' => 100]);
        $vehicles->addColumn('make', Types::STRING, ['length' => 50, 'notnull' => false]);
        $vehicles->addColumn('model', Types::STRING, ['length' => 50, 'notnull' => false]);
        $vehicles->addColumn('year', Types::INTEGER, ['notnull' => false]);
        $vehicles->addColumn('vin', Types::STRING, ['length' => 17, 'notnull' => false]);
        $vehicles->addColumn('vin_decoded_data', Types::JSON, ['notnull' => false]);
        $vehicles->addColumn('vin_decoded_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $vehicles->addColumn('registration_number', Types::STRING, ['length' => 20, 'notnull' => false]);
        $vehicles->addColumn('engine_number', Types::STRING, ['length' => 50, 'notnull' => false]);
        $vehicles->addColumn('v5_document_number', Types::STRING, ['length' => 50, 'notnull' => false]);
        $vehicles->addColumn('purchase_cost', Types::DECIMAL, ['precision' => 10, 'scale' => 2]);
        $vehicles->addColumn('purchase_date', Types::DATE_MUTABLE);
        $vehicles->addColumn('purchase_mileage', Types::INTEGER, ['notnull' => false]);
        $vehicles->addColumn('security_features', Types::TEXT, ['notnull' => false]);
        $vehicles->addColumn('vehicle_color', Types::STRING, ['length' => 20, 'notnull' => false]);
        $vehicles->addColumn('service_interval_months', Types::INTEGER, ['default' => 12]);
        $vehicles->addColumn('service_interval_miles', Types::INTEGER, ['default' => 4000]);
        $vehicles->addColumn('status', Types::STRING, ['length' => 20, 'default' => 'Live']);
        $vehicles->addColumn('depreciation_method', Types::STRING, ['length' => 20, 'default' => 'automotive_standard']);
        $vehicles->addColumn('depreciation_years', Types::INTEGER, ['default' => 10]);
        $vehicles->addColumn('depreciation_rate', Types::DECIMAL, ['precision' => 5, 'scale' => 2, 'default' => '20.00']);
        $vehicles->addColumn('created_at', Types::DATETIME_MUTABLE);
        $vehicles->addColumn('updated_at', Types::DATETIME_MUTABLE);
        $vehicles->addColumn('road_tax_exempt', Types::BOOLEAN, ['notnull' => false]);
        $vehicles->addColumn('mot_exempt', Types::BOOLEAN, ['notnull' => false]);
        $vehicles->setPrimaryKey(['id']);
        $vehicles->addUniqueIndex(['vin'], 'UNIQ_1FCE69FAB1085141');
        $vehicles->addIndex(['owner_id'], 'IDX_1FCE69FA7E3C61F9');
        $vehicles->addIndex(['vehicle_type_id'], 'IDX_1FCE69FADA3FD1FC');
        $vehicles->addForeignKeyConstraint('users', ['owner_id'], ['id']);
        $vehicles->addForeignKeyConstraint('vehicle_types', ['vehicle_type_id'], ['id']);

        // ── attachments (FK → vehicles, users) ─────────────────────────

        $attachments = $schema->createTable('attachments');
        $attachments->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $attachments->addColumn('vehicle_id', Types::INTEGER, ['notnull' => false]);
        $attachments->addColumn('user_id', Types::INTEGER);
        $attachments->addColumn('filename', Types::STRING, ['length' => 255]);
        $attachments->addColumn('original_name', Types::STRING, ['length' => 255]);
        $attachments->addColumn('mime_type', Types::STRING, ['length' => 100]);
        $attachments->addColumn('file_size', Types::INTEGER);
        $attachments->addColumn('uploaded_at', Types::DATETIME_MUTABLE);
        $attachments->addColumn('entity_type', Types::STRING, ['length' => 50, 'notnull' => false]);
        $attachments->addColumn('entity_id', Types::INTEGER, ['notnull' => false]);
        $attachments->addColumn('description', Types::TEXT, ['notnull' => false]);
        $attachments->addColumn('storage_path', Types::STRING, ['length' => 255, 'notnull' => false]);
        $attachments->addColumn('category', Types::STRING, ['length' => 50, 'notnull' => false]);
        $attachments->setPrimaryKey(['id']);
        $attachments->addIndex(['vehicle_id'], 'IDX_47C4FAD6545317D1');
        $attachments->addIndex(['user_id'], 'IDX_47C4FAD6A76ED395');
        $attachments->addForeignKeyConstraint('vehicles', ['vehicle_id'], ['id'], ['onDelete' => 'CASCADE']);
        $attachments->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);

        // ── reference tables ────────────────────────────────────────────

        $consumableTypes = $schema->createTable('consumable_types');
        $consumableTypes->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $consumableTypes->addColumn('vehicle_type_id', Types::INTEGER);
        $consumableTypes->addColumn('name', Types::STRING, ['length' => 100]);
        $consumableTypes->addColumn('unit', Types::STRING, ['length' => 50, 'notnull' => false]);
        $consumableTypes->addColumn('description', Types::TEXT, ['notnull' => false]);
        $consumableTypes->setPrimaryKey(['id']);
        $consumableTypes->addIndex(['vehicle_type_id'], 'IDX_FDE5C7B3DA3FD1FC');
        $consumableTypes->addForeignKeyConstraint('vehicle_types', ['vehicle_type_id'], ['id']);

        $vehicleMakes = $schema->createTable('vehicle_makes');
        $vehicleMakes->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $vehicleMakes->addColumn('vehicle_type_id', Types::INTEGER);
        $vehicleMakes->addColumn('name', Types::STRING, ['length' => 100]);
        $vehicleMakes->addColumn('is_active', Types::BOOLEAN, ['default' => true]);
        $vehicleMakes->setPrimaryKey(['id']);
        $vehicleMakes->addIndex(['vehicle_type_id'], 'IDX_D3B1CFCEDA3FD1FC');
        $vehicleMakes->addForeignKeyConstraint('vehicle_types', ['vehicle_type_id'], ['id']);

        $vehicleModels = $schema->createTable('vehicle_models');
        $vehicleModels->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $vehicleModels->addColumn('make_id', Types::INTEGER);
        $vehicleModels->addColumn('vehicle_type_id', Types::INTEGER, ['notnull' => false]);
        $vehicleModels->addColumn('name', Types::STRING, ['length' => 100]);
        $vehicleModels->addColumn('start_year', Types::INTEGER, ['notnull' => false]);
        $vehicleModels->addColumn('end_year', Types::INTEGER, ['notnull' => false]);
        $vehicleModels->addColumn('image_url', Types::STRING, ['length' => 255, 'notnull' => false]);
        $vehicleModels->addColumn('is_active', Types::BOOLEAN, ['default' => true]);
        $vehicleModels->setPrimaryKey(['id']);
        $vehicleModels->addIndex(['make_id'], 'IDX_4D0831DACFBF73EB');
        $vehicleModels->addIndex(['vehicle_type_id'], 'IDX_4D0831DADA3FD1FC');
        $vehicleModels->addForeignKeyConstraint('vehicle_makes', ['make_id'], ['id']);
        $vehicleModels->addForeignKeyConstraint('vehicle_types', ['vehicle_type_id'], ['id']);

        $partCategories = $schema->createTable('part_categories');
        $partCategories->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $partCategories->addColumn('vehicle_type_id', Types::INTEGER, ['notnull' => false]);
        $partCategories->addColumn('name', Types::STRING, ['length' => 100]);
        $partCategories->addColumn('description', Types::TEXT, ['notnull' => false]);
        $partCategories->addColumn('created_at', Types::DATETIME_MUTABLE);
        $partCategories->setPrimaryKey(['id']);
        $partCategories->addIndex(['vehicle_type_id'], 'IDX_F86C3ACDA3FD1FC');
        $partCategories->addForeignKeyConstraint('vehicle_types', ['vehicle_type_id'], ['id']);

        $securityFeatures = $schema->createTable('security_features');
        $securityFeatures->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $securityFeatures->addColumn('vehicle_type_id', Types::INTEGER);
        $securityFeatures->addColumn('name', Types::STRING, ['length' => 100]);
        $securityFeatures->addColumn('description', Types::TEXT, ['notnull' => false]);
        $securityFeatures->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $securityFeatures->setPrimaryKey(['id']);
        $securityFeatures->addIndex(['vehicle_type_id'], 'IDX_DCAB5BC1DA3FD1FC');
        $securityFeatures->addForeignKeyConstraint('vehicle_types', ['vehicle_type_id'], ['id'], ['onDelete' => 'CASCADE']);

        // ── todos (referenced by consumables, parts) ────────────────────

        $todos = $schema->createTable('todos');
        $todos->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $todos->addColumn('vehicle_id', Types::INTEGER);
        $todos->addColumn('title', Types::STRING, ['length' => 255]);
        $todos->addColumn('description', Types::TEXT, ['notnull' => false]);
        $todos->addColumn('done', Types::BOOLEAN, ['default' => false]);
        $todos->addColumn('due_date', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $todos->addColumn('completed_by', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $todos->addColumn('created_at', Types::DATETIME_MUTABLE);
        $todos->addColumn('updated_at', Types::DATETIME_MUTABLE);
        $todos->setPrimaryKey(['id']);
        $todos->addIndex(['vehicle_id'], 'IDX_CD826255545317D1');
        $todos->addForeignKeyConstraint('vehicles', ['vehicle_id'], ['id'], ['onDelete' => 'CASCADE']);

        // ── MOT records (referenced by consumables, parts, service_records) ─

        $motRecords = $schema->createTable('mot_records');
        $motRecords->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $motRecords->addColumn('vehicle_id', Types::INTEGER);
        $motRecords->addColumn('receipt_attachment_id', Types::INTEGER, ['notnull' => false]);
        $motRecords->addColumn('test_date', Types::DATE_MUTABLE);
        $motRecords->addColumn('result', Types::STRING, ['length' => 20]);
        $motRecords->addColumn('test_cost', Types::DECIMAL, ['precision' => 10, 'scale' => 2]);
        $motRecords->addColumn('repair_cost', Types::DECIMAL, ['precision' => 10, 'scale' => 2, 'default' => '0.00']);
        $motRecords->addColumn('mileage', Types::INTEGER, ['notnull' => false]);
        $motRecords->addColumn('test_center', Types::STRING, ['length' => 100, 'notnull' => false]);
        $motRecords->addColumn('expiry_date', Types::DATE_MUTABLE, ['notnull' => false]);
        $motRecords->addColumn('mot_test_number', Types::STRING, ['length' => 50, 'notnull' => false]);
        $motRecords->addColumn('tester_name', Types::STRING, ['length' => 100, 'notnull' => false]);
        $motRecords->addColumn('is_retest', Types::BOOLEAN, ['default' => false]);
        $motRecords->addColumn('advisories', Types::TEXT, ['notnull' => false]);
        $motRecords->addColumn('failures', Types::TEXT, ['notnull' => false]);
        $motRecords->addColumn('repair_details', Types::TEXT, ['notnull' => false]);
        $motRecords->addColumn('notes', Types::TEXT, ['notnull' => false]);
        $motRecords->addColumn('created_at', Types::DATETIME_MUTABLE);
        $motRecords->setPrimaryKey(['id']);
        $motRecords->addIndex(['vehicle_id'], 'IDX_F093C1A5545317D1');
        $motRecords->addIndex(['receipt_attachment_id'], 'IDX_F093C1A579F22B74');
        $motRecords->addForeignKeyConstraint('vehicles', ['vehicle_id'], ['id'], ['onDelete' => 'CASCADE']);
        $motRecords->addForeignKeyConstraint('attachments', ['receipt_attachment_id'], ['id'], ['onDelete' => 'SET NULL']);

        // ── service records (referenced by consumables, parts, service_items) ─

        $serviceRecords = $schema->createTable('service_records');
        $serviceRecords->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $serviceRecords->addColumn('vehicle_id', Types::INTEGER);
        $serviceRecords->addColumn('receipt_attachment_id', Types::INTEGER, ['notnull' => false]);
        $serviceRecords->addColumn('mot_record_id', Types::INTEGER, ['notnull' => false]);
        $serviceRecords->addColumn('service_date', Types::DATE_MUTABLE);
        $serviceRecords->addColumn('service_type', Types::STRING, ['length' => 50]);
        $serviceRecords->addColumn('labor_cost', Types::DECIMAL, ['precision' => 10, 'scale' => 2]);
        $serviceRecords->addColumn('parts_cost', Types::DECIMAL, ['precision' => 10, 'scale' => 2, 'default' => '0.00']);
        $serviceRecords->addColumn('consumables_cost', Types::DECIMAL, ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $serviceRecords->addColumn('mileage', Types::INTEGER, ['notnull' => false]);
        $serviceRecords->addColumn('service_provider', Types::STRING, ['length' => 100, 'notnull' => false]);
        $serviceRecords->addColumn('work_performed', Types::TEXT, ['notnull' => false]);
        $serviceRecords->addColumn('additional_costs', Types::DECIMAL, ['precision' => 10, 'scale' => 2, 'default' => '0.00']);
        $serviceRecords->addColumn('next_service_date', Types::DATE_MUTABLE, ['notnull' => false]);
        $serviceRecords->addColumn('next_service_mileage', Types::INTEGER, ['notnull' => false]);
        $serviceRecords->addColumn('notes', Types::TEXT, ['notnull' => false]);
        $serviceRecords->addColumn('created_at', Types::DATETIME_MUTABLE);
        $serviceRecords->addColumn('included_in_mot_cost', Types::BOOLEAN, ['default' => true]);
        $serviceRecords->addColumn('includes_mot_test_cost', Types::BOOLEAN, ['default' => false]);
        $serviceRecords->setPrimaryKey(['id']);
        $serviceRecords->addIndex(['vehicle_id'], 'IDX_53190ADA545317D1');
        $serviceRecords->addIndex(['receipt_attachment_id'], 'IDX_53190ADA79F22B74');
        $serviceRecords->addIndex(['mot_record_id'], 'IDX_53190ADAB17D92CD');
        $serviceRecords->addForeignKeyConstraint('vehicles', ['vehicle_id'], ['id'], ['onDelete' => 'CASCADE']);
        $serviceRecords->addForeignKeyConstraint('attachments', ['receipt_attachment_id'], ['id'], ['onDelete' => 'SET NULL']);
        $serviceRecords->addForeignKeyConstraint('mot_records', ['mot_record_id'], ['id']);

        // ── consumables ─────────────────────────────────────────────────

        $consumables = $schema->createTable('consumables');
        $consumables->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $consumables->addColumn('vehicle_id', Types::INTEGER);
        $consumables->addColumn('consumable_type_id', Types::INTEGER);
        $consumables->addColumn('service_record_id', Types::INTEGER, ['notnull' => false]);
        $consumables->addColumn('todo_id', Types::INTEGER, ['notnull' => false]);
        $consumables->addColumn('mot_record_id', Types::INTEGER, ['notnull' => false]);
        $consumables->addColumn('receipt_attachment_id', Types::INTEGER, ['notnull' => false]);
        $consumables->addColumn('description', Types::STRING, ['length' => 200, 'notnull' => false]);
        $consumables->addColumn('brand', Types::STRING, ['length' => 100, 'notnull' => false]);
        $consumables->addColumn('part_number', Types::STRING, ['length' => 100, 'notnull' => false]);
        $consumables->addColumn('replacement_interval', Types::INTEGER, ['notnull' => false]);
        $consumables->addColumn('next_replacement', Types::INTEGER, ['notnull' => false]);
        $consumables->addColumn('quantity', Types::DECIMAL, ['precision' => 8, 'scale' => 2, 'notnull' => false]);
        $consumables->addColumn('last_changed', Types::DATE_MUTABLE, ['notnull' => false]);
        $consumables->addColumn('mileage_at_change', Types::INTEGER, ['notnull' => false]);
        $consumables->addColumn('cost', Types::DECIMAL, ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $consumables->addColumn('notes', Types::TEXT, ['notnull' => false]);
        $consumables->addColumn('created_at', Types::DATETIME_MUTABLE);
        $consumables->addColumn('updated_at', Types::DATETIME_MUTABLE);
        $consumables->addColumn('product_url', Types::STRING, ['length' => 500, 'notnull' => false]);
        $consumables->addColumn('supplier', Types::STRING, ['length' => 100, 'notnull' => false]);
        $consumables->addColumn('included_in_service_cost', Types::BOOLEAN, ['default' => false]);
        $consumables->setPrimaryKey(['id']);
        $consumables->addIndex(['vehicle_id'], 'IDX_9B2FDD30545317D1');
        $consumables->addIndex(['consumable_type_id'], 'IDX_9B2FDD3044868F59');
        $consumables->addIndex(['service_record_id'], 'IDX_9B2FDD30156C4F46');
        $consumables->addIndex(['todo_id'], 'IDX_9B2FDD30EA1EBC33');
        $consumables->addIndex(['mot_record_id'], 'IDX_9B2FDD30B17D92CD');
        $consumables->addIndex(['receipt_attachment_id'], 'IDX_9B2FDD3079F22B74');
        $consumables->addForeignKeyConstraint('vehicles', ['vehicle_id'], ['id'], ['onDelete' => 'CASCADE']);
        $consumables->addForeignKeyConstraint('consumable_types', ['consumable_type_id'], ['id']);
        $consumables->addForeignKeyConstraint('service_records', ['service_record_id'], ['id'], ['onDelete' => 'SET NULL']);
        $consumables->addForeignKeyConstraint('todos', ['todo_id'], ['id'], ['onDelete' => 'SET NULL']);
        $consumables->addForeignKeyConstraint('mot_records', ['mot_record_id'], ['id'], ['onDelete' => 'SET NULL']);
        $consumables->addForeignKeyConstraint('attachments', ['receipt_attachment_id'], ['id'], ['onDelete' => 'SET NULL']);

        // ── fuel records ────────────────────────────────────────────────

        $fuelRecords = $schema->createTable('fuel_records');
        $fuelRecords->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $fuelRecords->addColumn('vehicle_id', Types::INTEGER);
        $fuelRecords->addColumn('receipt_attachment_id', Types::INTEGER, ['notnull' => false]);
        $fuelRecords->addColumn('date', Types::DATE_MUTABLE);
        $fuelRecords->addColumn('litres', Types::DECIMAL, ['precision' => 8, 'scale' => 2]);
        $fuelRecords->addColumn('cost', Types::DECIMAL, ['precision' => 8, 'scale' => 2]);
        $fuelRecords->addColumn('mileage', Types::INTEGER);
        $fuelRecords->addColumn('fuel_type', Types::STRING, ['length' => 50, 'notnull' => false]);
        $fuelRecords->addColumn('station', Types::STRING, ['length' => 200, 'notnull' => false]);
        $fuelRecords->addColumn('notes', Types::TEXT, ['notnull' => false]);
        $fuelRecords->addColumn('created_at', Types::DATETIME_MUTABLE);
        $fuelRecords->setPrimaryKey(['id']);
        $fuelRecords->addIndex(['vehicle_id'], 'IDX_33A12AE0545317D1');
        $fuelRecords->addIndex(['receipt_attachment_id'], 'IDX_33A12AE079F22B74');
        $fuelRecords->addForeignKeyConstraint('vehicles', ['vehicle_id'], ['id'], ['onDelete' => 'CASCADE']);
        $fuelRecords->addForeignKeyConstraint('attachments', ['receipt_attachment_id'], ['id'], ['onDelete' => 'SET NULL']);

        // ── insurance ↔ vehicles (join table) ───────────────────────────

        $ipv = $schema->createTable('insurance_policy_vehicles');
        $ipv->addColumn('insurance_policy_id', Types::INTEGER);
        $ipv->addColumn('vehicle_id', Types::INTEGER);
        $ipv->setPrimaryKey(['insurance_policy_id', 'vehicle_id']);
        $ipv->addIndex(['insurance_policy_id'], 'IDX_5D859BD9B55D920C');
        $ipv->addIndex(['vehicle_id'], 'IDX_5D859BD9545317D1');
        $ipv->addForeignKeyConstraint('insurance_policies', ['insurance_policy_id'], ['id'], ['onDelete' => 'CASCADE']);
        $ipv->addForeignKeyConstraint('vehicles', ['vehicle_id'], ['id'], ['onDelete' => 'CASCADE']);

        // ── parts ───────────────────────────────────────────────────────

        $parts = $schema->createTable('parts');
        $parts->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $parts->addColumn('vehicle_id', Types::INTEGER);
        $parts->addColumn('part_category_id', Types::INTEGER, ['notnull' => false]);
        $parts->addColumn('service_record_id', Types::INTEGER, ['notnull' => false]);
        $parts->addColumn('todo_id', Types::INTEGER, ['notnull' => false]);
        $parts->addColumn('mot_record_id', Types::INTEGER, ['notnull' => false]);
        $parts->addColumn('receipt_attachment_id', Types::INTEGER, ['notnull' => false]);
        $parts->addColumn('purchase_date', Types::DATE_MUTABLE);
        $parts->addColumn('description', Types::STRING, ['length' => 200]);
        $parts->addColumn('name', Types::STRING, ['length' => 200, 'notnull' => false]);
        $parts->addColumn('price', Types::DECIMAL, ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $parts->addColumn('part_number', Types::STRING, ['length' => 100, 'notnull' => false]);
        $parts->addColumn('sku', Types::STRING, ['length' => 100, 'notnull' => false]);
        $parts->addColumn('manufacturer', Types::STRING, ['length' => 100, 'notnull' => false]);
        $parts->addColumn('supplier', Types::STRING, ['length' => 100, 'notnull' => false]);
        $parts->addColumn('quantity', Types::INTEGER, ['default' => 1]);
        $parts->addColumn('warranty_months', Types::INTEGER, ['notnull' => false]);
        $parts->addColumn('image_url', Types::STRING, ['length' => 500, 'notnull' => false]);
        $parts->addColumn('cost', Types::DECIMAL, ['precision' => 10, 'scale' => 2]);
        $parts->addColumn('installation_date', Types::DATE_MUTABLE, ['notnull' => false]);
        $parts->addColumn('mileage_at_installation', Types::INTEGER, ['notnull' => false]);
        $parts->addColumn('notes', Types::TEXT, ['notnull' => false]);
        $parts->addColumn('created_at', Types::DATETIME_MUTABLE);
        $parts->addColumn('product_url', Types::STRING, ['length' => 500, 'notnull' => false]);
        $parts->addColumn('included_in_service_cost', Types::BOOLEAN, ['default' => false]);
        $parts->setPrimaryKey(['id']);
        $parts->addIndex(['vehicle_id'], 'IDX_6940A7FE545317D1');
        $parts->addIndex(['part_category_id'], 'IDX_6940A7FE8E7AEECE');
        $parts->addIndex(['service_record_id'], 'IDX_6940A7FE156C4F46');
        $parts->addIndex(['todo_id'], 'IDX_6940A7FEEA1EBC33');
        $parts->addIndex(['mot_record_id'], 'IDX_6940A7FEB17D92CD');
        $parts->addIndex(['receipt_attachment_id'], 'IDX_6940A7FE79F22B74');
        $parts->addForeignKeyConstraint('vehicles', ['vehicle_id'], ['id'], ['onDelete' => 'CASCADE']);
        $parts->addForeignKeyConstraint('part_categories', ['part_category_id'], ['id'], ['onDelete' => 'SET NULL']);
        $parts->addForeignKeyConstraint('service_records', ['service_record_id'], ['id'], ['onDelete' => 'SET NULL']);
        $parts->addForeignKeyConstraint('todos', ['todo_id'], ['id'], ['onDelete' => 'SET NULL']);
        $parts->addForeignKeyConstraint('mot_records', ['mot_record_id'], ['id'], ['onDelete' => 'SET NULL']);
        $parts->addForeignKeyConstraint('attachments', ['receipt_attachment_id'], ['id'], ['onDelete' => 'SET NULL']);

        // ── refresh tokens ──────────────────────────────────────────────

        $refreshTokens = $schema->createTable('refresh_tokens');
        $refreshTokens->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $refreshTokens->addColumn('user_id', Types::INTEGER);
        $refreshTokens->addColumn('refresh_token', Types::STRING, ['length' => 255]);
        $refreshTokens->addColumn('expires_at', Types::DATETIME_IMMUTABLE);
        $refreshTokens->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $refreshTokens->setPrimaryKey(['id']);
        $refreshTokens->addUniqueIndex(['refresh_token'], 'UNIQ_9BACE7E1C74F2195');
        $refreshTokens->addIndex(['user_id'], 'IDX_9BACE7E1A76ED395');
        $refreshTokens->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);

        // ── reports ─────────────────────────────────────────────────────

        $reports = $schema->createTable('reports');
        $reports->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $reports->addColumn('user_id', Types::INTEGER);
        $reports->addColumn('name', Types::STRING, ['length' => 255]);
        $reports->addColumn('template_key', Types::STRING, ['length' => 255, 'notnull' => false]);
        $reports->addColumn('payload', Types::JSON, ['notnull' => false]);
        $reports->addColumn('vehicle_id', Types::INTEGER, ['notnull' => false]);
        $reports->addColumn('generated_at', Types::DATETIME_MUTABLE);
        $reports->setPrimaryKey(['id']);
        $reports->addIndex(['user_id'], 'IDX_F11FA745A76ED395');
        $reports->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);

        // ── road tax ────────────────────────────────────────────────────

        $roadTax = $schema->createTable('road_tax');
        $roadTax->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $roadTax->addColumn('vehicle_id', Types::INTEGER);
        $roadTax->addColumn('start_date', Types::DATE_MUTABLE, ['notnull' => false]);
        $roadTax->addColumn('expiry_date', Types::DATE_MUTABLE, ['notnull' => false]);
        $roadTax->addColumn('amount', Types::DECIMAL, ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $roadTax->addColumn('notes', Types::TEXT, ['notnull' => false]);
        $roadTax->addColumn('created_at', Types::DATETIME_MUTABLE);
        $roadTax->addColumn('frequency', Types::STRING, ['length' => 10, 'default' => 'annual']);
        $roadTax->addColumn('sorn', Types::BOOLEAN, ['default' => false]);
        $roadTax->setPrimaryKey(['id']);
        $roadTax->addIndex(['vehicle_id'], 'IDX_BB1D046A545317D1');
        $roadTax->addForeignKeyConstraint('vehicles', ['vehicle_id'], ['id'], ['onDelete' => 'CASCADE']);

        // ── service items ───────────────────────────────────────────────

        $serviceItems = $schema->createTable('service_items');
        $serviceItems->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $serviceItems->addColumn('service_record_id', Types::INTEGER);
        $serviceItems->addColumn('consumable_id', Types::INTEGER, ['notnull' => false]);
        $serviceItems->addColumn('part_id', Types::INTEGER, ['notnull' => false]);
        $serviceItems->addColumn('type', Types::STRING, ['length' => 20]);
        $serviceItems->addColumn('description', Types::STRING, ['length' => 255, 'notnull' => false]);
        $serviceItems->addColumn('cost', Types::DECIMAL, ['precision' => 10, 'scale' => 2]);
        $serviceItems->addColumn('quantity', Types::DECIMAL, ['precision' => 10, 'scale' => 2, 'default' => '1.00']);
        $serviceItems->setPrimaryKey(['id']);
        $serviceItems->addIndex(['service_record_id'], 'IDX_486C04AA156C4F46');
        $serviceItems->addIndex(['consumable_id'], 'IDX_486C04AAA94ADB61');
        $serviceItems->addIndex(['part_id'], 'IDX_486C04AA4CE34BEC');
        $serviceItems->addForeignKeyConstraint('service_records', ['service_record_id'], ['id'], ['onDelete' => 'CASCADE']);
        $serviceItems->addForeignKeyConstraint('consumables', ['consumable_id'], ['id'], ['onDelete' => 'SET NULL']);
        $serviceItems->addForeignKeyConstraint('parts', ['part_id'], ['id'], ['onDelete' => 'SET NULL']);

        // ── specifications ──────────────────────────────────────────────

        $specs = $schema->createTable('specifications');
        $specs->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $specs->addColumn('vehicle_id', Types::INTEGER);
        $specs->addColumn('engine_type', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('displacement', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('power', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('torque', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('compression', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('bore', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('stroke', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('fuel_system', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('cooling', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('sparkplug_type', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('coolant_type', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('coolant_capacity', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('gearbox', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('transmission', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('final_drive', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('clutch', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('engine_oil_type', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('engine_oil_capacity', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('transmission_oil_type', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('transmission_oil_capacity', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('middle_drive_oil_type', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('middle_drive_oil_capacity', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('frame', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('front_suspension', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('rear_suspension', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('static_sag_front', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('static_sag_rear', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('front_brakes', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('rear_brakes', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('front_tyre', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('rear_tyre', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('front_tyre_pressure', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('rear_tyre_pressure', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('front_wheel_travel', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('rear_wheel_travel', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('wheelbase', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('seat_height', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('ground_clearance', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('dry_weight', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('wet_weight', Types::STRING, ['length' => 100, 'notnull' => false]);
        $specs->addColumn('fuel_capacity', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('top_speed', Types::STRING, ['length' => 50, 'notnull' => false]);
        $specs->addColumn('additional_info', Types::TEXT, ['notnull' => false]);
        $specs->addColumn('scraped_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $specs->addColumn('source_url', Types::STRING, ['length' => 255, 'notnull' => false]);
        $specs->setPrimaryKey(['id']);
        $specs->addUniqueIndex(['vehicle_id'], 'UNIQ_BDA8A4B545317D1');
        $specs->addForeignKeyConstraint('vehicles', ['vehicle_id'], ['id'], ['onDelete' => 'CASCADE']);

        // ── user feature overrides ──────────────────────────────────────

        $ufo = $schema->createTable('user_feature_overrides');
        $ufo->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $ufo->addColumn('user_id', Types::INTEGER);
        $ufo->addColumn('feature_flag_id', Types::INTEGER);
        $ufo->addColumn('set_by_id', Types::INTEGER, ['notnull' => false]);
        $ufo->addColumn('enabled', Types::BOOLEAN);
        $ufo->addColumn('created_at', Types::DATETIME_MUTABLE);
        $ufo->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $ufo->setPrimaryKey(['id']);
        $ufo->addIndex(['user_id'], 'IDX_85ECA143A76ED395');
        $ufo->addIndex(['feature_flag_id'], 'IDX_85ECA143A0887FEC');
        $ufo->addIndex(['set_by_id'], 'IDX_85ECA1433E16DC62');
        $ufo->addUniqueIndex(['user_id', 'feature_flag_id'], 'uq_user_feature');
        $ufo->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);
        $ufo->addForeignKeyConstraint('feature_flags', ['feature_flag_id'], ['id'], ['onDelete' => 'CASCADE']);
        $ufo->addForeignKeyConstraint('users', ['set_by_id'], ['id'], ['onDelete' => 'SET NULL']);

        // ── user preferences ────────────────────────────────────────────

        $userPrefs = $schema->createTable('user_preferences');
        $userPrefs->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $userPrefs->addColumn('user_id', Types::INTEGER);
        $userPrefs->addColumn('name', Types::STRING, ['length' => 150]);
        $userPrefs->addColumn('value', Types::TEXT, ['notnull' => false]);
        $userPrefs->addColumn('created_at', Types::DATETIME_MUTABLE);
        $userPrefs->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $userPrefs->setPrimaryKey(['id']);
        $userPrefs->addIndex(['user_id'], 'IDX_402A6F60A76ED395');
        $userPrefs->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);

        // ── vehicle assignments ─────────────────────────────────────────

        $va = $schema->createTable('vehicle_assignments');
        $va->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $va->addColumn('vehicle_id', Types::INTEGER);
        $va->addColumn('assigned_to_id', Types::INTEGER);
        $va->addColumn('assigned_by_id', Types::INTEGER, ['notnull' => false]);
        $va->addColumn('can_view', Types::BOOLEAN, ['default' => true]);
        $va->addColumn('can_edit', Types::BOOLEAN, ['default' => true]);
        $va->addColumn('can_add_records', Types::BOOLEAN, ['default' => true]);
        $va->addColumn('can_delete', Types::BOOLEAN, ['default' => false]);
        $va->addColumn('created_at', Types::DATETIME_MUTABLE);
        $va->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $va->setPrimaryKey(['id']);
        $va->addIndex(['vehicle_id'], 'IDX_BEB12DAB545317D1');
        $va->addIndex(['assigned_to_id'], 'IDX_BEB12DABF4BD7827');
        $va->addIndex(['assigned_by_id'], 'IDX_BEB12DAB6E6F1246');
        $va->addUniqueIndex(['vehicle_id', 'assigned_to_id'], 'uq_vehicle_user');
        $va->addForeignKeyConstraint('vehicles', ['vehicle_id'], ['id'], ['onDelete' => 'CASCADE']);
        $va->addForeignKeyConstraint('users', ['assigned_to_id'], ['id'], ['onDelete' => 'CASCADE']);
        $va->addForeignKeyConstraint('users', ['assigned_by_id'], ['id'], ['onDelete' => 'SET NULL']);

        // ── vehicle images ──────────────────────────────────────────────

        $vehicleImages = $schema->createTable('vehicle_images');
        $vehicleImages->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $vehicleImages->addColumn('vehicle_id', Types::INTEGER);
        $vehicleImages->addColumn('path', Types::STRING, ['length' => 255]);
        $vehicleImages->addColumn('caption', Types::STRING, ['length' => 255, 'notnull' => false]);
        $vehicleImages->addColumn('is_primary', Types::BOOLEAN, ['default' => false]);
        $vehicleImages->addColumn('display_order', Types::INTEGER, ['default' => 0]);
        $vehicleImages->addColumn('is_scraped', Types::BOOLEAN, ['default' => false]);
        $vehicleImages->addColumn('source_url', Types::STRING, ['length' => 255, 'notnull' => false]);
        $vehicleImages->addColumn('uploaded_at', Types::DATETIME_MUTABLE);
        $vehicleImages->setPrimaryKey(['id']);
        $vehicleImages->addIndex(['vehicle_id'], 'IDX_49C1BFB9545317D1');
        $vehicleImages->addForeignKeyConstraint('vehicles', ['vehicle_id'], ['id'], ['onDelete' => 'CASCADE']);

        // ── vehicle status histories ────────────────────────────────────

        $vsh = $schema->createTable('vehicle_status_histories');
        $vsh->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $vsh->addColumn('vehicle_id', Types::INTEGER);
        $vsh->addColumn('user_id', Types::INTEGER, ['notnull' => false]);
        $vsh->addColumn('old_status', Types::STRING, ['length' => 20]);
        $vsh->addColumn('new_status', Types::STRING, ['length' => 20]);
        $vsh->addColumn('change_date', Types::DATETIME_MUTABLE);
        $vsh->addColumn('notes', Types::TEXT, ['notnull' => false]);
        $vsh->addColumn('created_at', Types::DATETIME_MUTABLE);
        $vsh->setPrimaryKey(['id']);
        $vsh->addIndex(['vehicle_id'], 'IDX_259D9C78545317D1');
        $vsh->addIndex(['user_id'], 'IDX_259D9C78A76ED395');
        $vsh->addForeignKeyConstraint('vehicles', ['vehicle_id'], ['id'], ['onDelete' => 'CASCADE']);
        $vsh->addForeignKeyConstraint('users', ['user_id'], ['id']);
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
        // Drop in reverse dependency order
        $schema->dropTable('vehicle_status_histories');
        $schema->dropTable('vehicle_images');
        $schema->dropTable('vehicle_assignments');
        $schema->dropTable('user_preferences');
        $schema->dropTable('user_feature_overrides');
        $schema->dropTable('specifications');
        $schema->dropTable('service_items');
        $schema->dropTable('parts');
        $schema->dropTable('insurance_policy_vehicles');
        $schema->dropTable('fuel_records');
        $schema->dropTable('consumables');
        $schema->dropTable('service_records');
        $schema->dropTable('mot_records');
        $schema->dropTable('todos');
        $schema->dropTable('security_features');
        $schema->dropTable('part_categories');
        $schema->dropTable('vehicle_models');
        $schema->dropTable('vehicle_makes');
        $schema->dropTable('consumable_types');
        $schema->dropTable('attachments');
        $schema->dropTable('vehicles');
        $schema->dropTable('insurance_policies');
        $schema->dropTable('feature_flags');
        $schema->dropTable('users');
        $schema->dropTable('vehicle_types');
        $schema->dropTable('refresh_tokens');
        $schema->dropTable('reports');
        $schema->dropTable('road_tax');
    }
}
