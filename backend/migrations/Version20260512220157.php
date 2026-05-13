<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * class Version20260512220157
 */
final class Version20260512220157 extends AbstractMigration
{
    /**
     * function getDescription
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Consolidated baseline migration (database-agnostic for MySQL, PostgreSQL, SQLite).';
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
        // Create all tables first
        $this->createVehicleTypesTables($schema);
        $this->createUsersTables($schema);
        $this->createVehiclesTables($schema);
        $this->createSpecificationsTables($schema);
        $this->createAttachmentsTables($schema);
        $this->createFuelRecordsTables($schema);
        $this->createMotRecordsTables($schema);
        $this->createServiceRecordsTables($schema);
        $this->createPartsTables($schema);
        $this->createConsumablesTables($schema);
        $this->createTodosTables($schema);
        $this->createInsuranceTables($schema);
        $this->createRoadTaxTables($schema);
        $this->createSecurityFeaturesTables($schema);
        $this->createRefreshTokensTables($schema);
        $this->createReportsTables($schema);
        $this->createFeatureFlagsTables($schema);
        $this->createVehicleAssignmentsTables($schema);
        $this->createVehicleImagesTables($schema);
        $this->createVehicleModelsTables($schema);
        $this->createVehicleStatusHistoriesTables($schema);
        $this->createServiceItemsTables($schema);
        $this->createUserPreferencesTables($schema);
        $this->createStockItemsTables($schema);
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
        // Drop all tables in reverse dependency order
        $tables = [
            'vehicle_assignments', 'vehicle_status_histories', 'vehicle_models', 'vehicle_makes',
            'vehicle_images', 'specifications', 'vehicles',
            'stock_items', 'service_items', 'service_records', 'mot_records', 'fuel_records',
            'consumables', 'consumable_types', 'parts', 'part_categories',
            'todos', 'insurance_policy_vehicles', 'insurance_policies',
            'road_tax', 'security_features', 'attachments',
            'refresh_tokens', 'user_feature_overrides', 'user_preferences', 'feature_flags', 'reports',
            'users', 'vehicle_types',
        ];

        foreach ($tables as $tableName) {
            if ($schema->hasTable($tableName)) {
                $schema->dropTable($tableName);
            }
        }
    }

    /**
     * function createVehicleTypesTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createVehicleTypesTables(Schema $schema): void
    {
        if ($schema->hasTable('vehicle_types')) {
            return;
        }

        $table = $schema->createTable('vehicle_types');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('name', 'string', ['length' => 50, 'notnull' => true]);
        $table->setPrimaryKey(['id']);
    }

    /**
     * function createUsersTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createUsersTables(Schema $schema): void
    {
        if ($schema->hasTable('users')) {
            return;
        }

        $table = $schema->createTable('users');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('email', 'string', ['length' => 180, 'notnull' => true]);
        $table->addColumn('roles', 'json', ['notnull' => true]);
        $table->addColumn('password', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('first_name', 'string', ['length' => 100, 'notnull' => true]);
        $table->addColumn('last_name', 'string', ['length' => 100, 'notnull' => true]);
        $table->addColumn('country', 'string', ['length' => 2, 'default' => 'GB', 'notnull' => true]);
        $table->addColumn('password_change_required', 'boolean', ['default' => false, 'notnull' => true]);
        $table->addColumn('is_active', 'boolean', ['default' => true, 'notnull' => true]);
        $table->addColumn('is_verified', 'boolean', ['default' => false, 'notnull' => true]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $table->addColumn('last_login_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['email']);
    }

    /**
     * function createVehiclesTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createVehiclesTables(Schema $schema): void
    {
        if ($schema->hasTable('vehicles')) {
            return;
        }

        $table = $schema->createTable('vehicles');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('owner_id', 'integer', ['notnull' => true]);
        $table->addColumn('vehicle_type_id', 'integer', ['notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 100, 'notnull' => true]);
        $table->addColumn('make', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('model', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('year', 'integer', ['notnull' => false]);
        $table->addColumn('vin', 'string', ['length' => 17, 'notnull' => false]);
        $table->addColumn('vin_decoded_data', 'json', ['notnull' => false]);
        $table->addColumn('vin_decoded_at', 'datetime', ['notnull' => false]);
        $table->addColumn('registration_number', 'string', ['length' => 20, 'notnull' => false]);
        $table->addColumn('engine_number', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('v5_document_number', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('purchase_cost', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => true]);
        $table->addColumn('purchase_date', 'date', ['notnull' => true]);
        $table->addColumn('purchase_mileage', 'integer', ['notnull' => false]);
        $table->addColumn('security_features', 'text', ['notnull' => false]);
        $table->addColumn('vehicle_color', 'string', ['length' => 20, 'notnull' => false]);
        $table->addColumn('service_interval_months', 'integer', ['default' => 12, 'notnull' => true]);
        $table->addColumn('service_interval_miles', 'integer', ['default' => 4000, 'notnull' => true]);
        $table->addColumn('status', 'string', ['length' => 20, 'default' => 'Live', 'notnull' => true]);
        $table->addColumn('depreciation_method', 'string', ['length' => 20, 'default' => 'automotive_standard', 'notnull' => true]);
        $table->addColumn('depreciation_years', 'integer', ['default' => 10, 'notnull' => true]);
        $table->addColumn('depreciation_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => '20.00', 'notnull' => true]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => true]);
        $table->addColumn('road_tax_exempt', 'boolean', ['notnull' => false]);
        $table->addColumn('mot_exempt', 'boolean', ['notnull' => false]);
        $table->addColumn('suppress_notifications', 'boolean', ['default' => false, 'notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['owner_id']);
        $table->addIndex(['vehicle_type_id']);
        $table->addUniqueIndex(['vin']);
    }

    /**
     * function createAttachmentsTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createAttachmentsTables(Schema $schema): void
    {
        if ($schema->hasTable('attachments')) {
            return;
        }

        $table = $schema->createTable('attachments');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('vehicle_id', 'integer', ['notnull' => false]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('filename', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('original_name', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('mime_type', 'string', ['length' => 100, 'notnull' => true]);
        $table->addColumn('file_size', 'integer', ['notnull' => true]);
        $table->addColumn('uploaded_at', 'datetime', ['notnull' => true]);
        $table->addColumn('entity_type', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('entity_id', 'integer', ['notnull' => false]);
        $table->addColumn('description', 'text', ['notnull' => false]);
        $table->addColumn('storage_path', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('category', 'string', ['length' => 50, 'notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['user_id']);
        $table->addIndex(['vehicle_id']);
    }

    /**
     * function createFuelRecordsTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createFuelRecordsTables(Schema $schema): void
    {
        if ($schema->hasTable('fuel_records')) {
            return;
        }

        $table = $schema->createTable('fuel_records');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('vehicle_id', 'integer', ['notnull' => true]);
        $table->addColumn('receipt_attachment_id', 'integer', ['notnull' => false]);
        $table->addColumn('date', 'date', ['notnull' => true]);
        $table->addColumn('litres', 'decimal', ['precision' => 8, 'scale' => 2, 'notnull' => true]);
        $table->addColumn('cost', 'decimal', ['precision' => 8, 'scale' => 2, 'notnull' => true]);
        $table->addColumn('mileage', 'integer', ['notnull' => true]);
        $table->addColumn('fuel_type', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('station', 'string', ['length' => 200, 'notnull' => false]);
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['vehicle_id']);
        $table->addIndex(['receipt_attachment_id']);
    }

    /**
     * function createMotRecordsTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createMotRecordsTables(Schema $schema): void
    {
        if ($schema->hasTable('mot_records')) {
            return;
        }

        $table = $schema->createTable('mot_records');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('vehicle_id', 'integer', ['notnull' => true]);
        $table->addColumn('receipt_attachment_id', 'integer', ['notnull' => false]);
        $table->addColumn('test_date', 'date', ['notnull' => true]);
        $table->addColumn('result', 'string', ['length' => 20, 'notnull' => true]);
        $table->addColumn('test_cost', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => true]);
        $table->addColumn('repair_cost', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'notnull' => true]);
        $table->addColumn('mileage', 'integer', ['notnull' => false]);
        $table->addColumn('test_center', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('expiry_date', 'date', ['notnull' => false]);
        $table->addColumn('mot_test_number', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('tester_name', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('is_retest', 'boolean', ['default' => false, 'notnull' => true]);
        $table->addColumn('advisories', 'text', ['notnull' => false]);
        $table->addColumn('failures', 'text', ['notnull' => false]);
        $table->addColumn('repair_details', 'text', ['notnull' => false]);
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['vehicle_id']);
        $table->addIndex(['receipt_attachment_id']);
    }

    /**
     * function createServiceRecordsTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createServiceRecordsTables(Schema $schema): void
    {
        if ($schema->hasTable('service_records')) {
            return;
        }

        $table = $schema->createTable('service_records');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('vehicle_id', 'integer', ['notnull' => true]);
        $table->addColumn('receipt_attachment_id', 'integer', ['notnull' => false]);
        $table->addColumn('mot_record_id', 'integer', ['notnull' => false]);
        $table->addColumn('service_date', 'date', ['notnull' => true]);
        $table->addColumn('service_type', 'string', ['length' => 50, 'notnull' => true]);
        $table->addColumn('labor_cost', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => true]);
        $table->addColumn('parts_cost', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'notnull' => true]);
        $table->addColumn('consumables_cost', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $table->addColumn('mileage', 'integer', ['notnull' => false]);
        $table->addColumn('service_provider', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('work_performed', 'text', ['notnull' => false]);
        $table->addColumn('additional_costs', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'notnull' => true]);
        $table->addColumn('next_service_date', 'date', ['notnull' => false]);
        $table->addColumn('next_service_mileage', 'integer', ['notnull' => false]);
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('included_in_mot_cost', 'boolean', ['default' => true, 'notnull' => true]);
        $table->addColumn('includes_mot_test_cost', 'boolean', ['default' => false, 'notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['vehicle_id']);
        $table->addIndex(['receipt_attachment_id']);
        $table->addIndex(['mot_record_id']);
    }

    /**
     * function createPartsTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createPartsTables(Schema $schema): void
    {
        if ($schema->hasTable('part_categories')) {
            return;
        }

        $catTable = $schema->createTable('part_categories');
        $catTable->addColumn('id', 'integer', ['autoincrement' => true]);
        $catTable->addColumn('vehicle_type_id', 'integer', ['notnull' => false]);
        $catTable->addColumn('name', 'string', ['length' => 100, 'notnull' => true]);
        $catTable->addColumn('description', 'text', ['notnull' => false]);
        $catTable->addColumn('created_at', 'datetime', ['notnull' => true]);
        $catTable->setPrimaryKey(['id']);
        $catTable->addIndex(['vehicle_type_id']);

        if ($schema->hasTable('parts')) {
            return;
        }

        $table = $schema->createTable('parts');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('vehicle_id', 'integer', ['notnull' => false]);
        $table->addColumn('user_id', 'integer', ['notnull' => false]);
        $table->addColumn('part_category_id', 'integer', ['notnull' => false]);
        $table->addColumn('service_record_id', 'integer', ['notnull' => false]);
        $table->addColumn('todo_id', 'integer', ['notnull' => false]);
        $table->addColumn('mot_record_id', 'integer', ['notnull' => false]);
        $table->addColumn('receipt_attachment_id', 'integer', ['notnull' => false]);
        $table->addColumn('purchase_date', 'date', ['notnull' => true]);
        $table->addColumn('description', 'string', ['length' => 200, 'notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 200, 'notnull' => false]);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $table->addColumn('part_number', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('sku', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('manufacturer', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('supplier', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('quantity', 'decimal', ['precision' => 8, 'scale' => 2, 'default' => '1.00', 'notnull' => true]);
        $table->addColumn('warranty_months', 'integer', ['notnull' => false]);
        $table->addColumn('image_url', 'string', ['length' => 500, 'notnull' => false]);
        $table->addColumn('cost', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => true]);
        $table->addColumn('installation_date', 'date', ['notnull' => false]);
        $table->addColumn('mileage_at_installation', 'integer', ['notnull' => false]);
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('product_url', 'string', ['length' => 500, 'notnull' => false]);
        $table->addColumn('included_in_service_cost', 'boolean', ['default' => false, 'notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['vehicle_id']);
        $table->addIndex(['user_id']);
        $table->addIndex(['part_category_id']);
        $table->addIndex(['service_record_id']);
        $table->addIndex(['todo_id']);
        $table->addIndex(['mot_record_id']);
        $table->addIndex(['receipt_attachment_id']);
    }

    /**
     * function createConsumablesTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createConsumablesTables(Schema $schema): void
    {
        if ($schema->hasTable('consumable_types')) {
            return;
        }

        $typeTable = $schema->createTable('consumable_types');
        $typeTable->addColumn('id', 'integer', ['autoincrement' => true]);
        $typeTable->addColumn('vehicle_type_id', 'integer', ['notnull' => true]);
        $typeTable->addColumn('name', 'string', ['length' => 100, 'notnull' => true]);
        $typeTable->addColumn('unit', 'string', ['length' => 50, 'notnull' => false]);
        $typeTable->addColumn('description', 'text', ['notnull' => false]);
        $typeTable->setPrimaryKey(['id']);
        $typeTable->addIndex(['vehicle_type_id']);

        if ($schema->hasTable('consumables')) {
            return;
        }

        $table = $schema->createTable('consumables');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('vehicle_id', 'integer', ['notnull' => false]);
        $table->addColumn('user_id', 'integer', ['notnull' => false]);
        $table->addColumn('consumable_type_id', 'integer', ['notnull' => true]);
        $table->addColumn('service_record_id', 'integer', ['notnull' => false]);
        $table->addColumn('todo_id', 'integer', ['notnull' => false]);
        $table->addColumn('mot_record_id', 'integer', ['notnull' => false]);
        $table->addColumn('receipt_attachment_id', 'integer', ['notnull' => false]);
        $table->addColumn('description', 'string', ['length' => 200, 'notnull' => false]);
        $table->addColumn('brand', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('part_number', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('replacement_interval', 'integer', ['notnull' => false]);
        $table->addColumn('next_replacement', 'integer', ['notnull' => false]);
        $table->addColumn('quantity', 'decimal', ['precision' => 8, 'scale' => 2, 'notnull' => false]);
        $table->addColumn('last_changed', 'date', ['notnull' => false]);
        $table->addColumn('mileage_at_change', 'integer', ['notnull' => false]);
        $table->addColumn('cost', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => true]);
        $table->addColumn('product_url', 'string', ['length' => 500, 'notnull' => false]);
        $table->addColumn('supplier', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('included_in_service_cost', 'boolean', ['default' => false, 'notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['vehicle_id']);
        $table->addIndex(['user_id']);
        $table->addIndex(['consumable_type_id']);
        $table->addIndex(['service_record_id']);
        $table->addIndex(['todo_id']);
        $table->addIndex(['mot_record_id']);
        $table->addIndex(['receipt_attachment_id']);
    }

    /**
     * function createTodosTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createTodosTables(Schema $schema): void
    {
        if ($schema->hasTable('todos')) {
            return;
        }

        $table = $schema->createTable('todos');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('vehicle_id', 'integer', ['notnull' => true]);
        $table->addColumn('title', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('description', 'text', ['notnull' => false]);
        $table->addColumn('done', 'boolean', ['default' => false, 'notnull' => true]);
        $table->addColumn('due_date', 'datetime', ['notnull' => false]);
        $table->addColumn('completed_by', 'datetime', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['vehicle_id']);
    }

    /**
     * function createInsuranceTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createInsuranceTables(Schema $schema): void
    {
        if ($schema->hasTable('insurance_policies')) {
            return;
        }

        $table = $schema->createTable('insurance_policies');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('provider', 'string', ['length' => 100, 'notnull' => true]);
        $table->addColumn('policy_number', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('annual_cost', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $table->addColumn('ncd_years', 'integer', ['notnull' => false]);
        $table->addColumn('start_date', 'date', ['notnull' => false]);
        $table->addColumn('expiry_date', 'date', ['notnull' => false]);
        $table->addColumn('coverage_type', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('excess', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $table->addColumn('mileage_limit', 'integer', ['notnull' => false]);
        $table->addColumn('holder_id', 'integer', ['notnull' => false]);
        $table->addColumn('auto_renewal', 'boolean', ['default' => false, 'notnull' => true]);
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->setPrimaryKey(['id']);

        if ($schema->hasTable('insurance_policy_vehicles')) {
            return;
        }

        $bridgeTable = $schema->createTable('insurance_policy_vehicles');
        $bridgeTable->addColumn('insurance_policy_id', 'integer', ['notnull' => true]);
        $bridgeTable->addColumn('vehicle_id', 'integer', ['notnull' => true]);
        $bridgeTable->setPrimaryKey(['insurance_policy_id', 'vehicle_id']);
        $bridgeTable->addIndex(['vehicle_id']);
        $bridgeTable->addIndex(['insurance_policy_id']);
        $bridgeTable->addForeignKeyConstraint('insurance_policies', ['insurance_policy_id'], ['id'], ['onDelete' => 'CASCADE']);
        $bridgeTable->addForeignKeyConstraint('vehicles', ['vehicle_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    /**
     * function createRoadTaxTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createRoadTaxTables(Schema $schema): void
    {
        if ($schema->hasTable('road_tax')) {
            return;
        }

        $table = $schema->createTable('road_tax');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('vehicle_id', 'integer', ['notnull' => true]);
        $table->addColumn('start_date', 'date', ['notnull' => false]);
        $table->addColumn('expiry_date', 'date', ['notnull' => false]);
        $table->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('frequency', 'string', ['length' => 10, 'default' => 'annual', 'notnull' => true]);
        $table->addColumn('sorn', 'boolean', ['default' => false, 'notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['vehicle_id']);
    }

    /**
     * function createSecurityFeaturesTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createSecurityFeaturesTables(Schema $schema): void
    {
        if ($schema->hasTable('security_features')) {
            return;
        }

        $table = $schema->createTable('security_features');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('vehicle_type_id', 'integer', ['notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 100, 'notnull' => true]);
        $table->addColumn('description', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['vehicle_type_id']);
    }

    /**
     * function createRefreshTokensTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createRefreshTokensTables(Schema $schema): void
    {
        if ($schema->hasTable('refresh_tokens')) {
            return;
        }

        $table = $schema->createTable('refresh_tokens');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('refresh_token', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('expires_at', 'datetime_immutable', ['notnull' => true]);
        $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['user_id']);
        $table->addUniqueIndex(['refresh_token']);
    }

    /**
     * function createReportsTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createReportsTables(Schema $schema): void
    {
        if ($schema->hasTable('reports')) {
            return;
        }

        $table = $schema->createTable('reports');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('template_key', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('payload', 'json', ['notnull' => false]);
        $table->addColumn('vehicle_id', 'integer', ['notnull' => false]);
        $table->addColumn('generated_at', 'datetime', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['user_id']);
    }

    /**
     * function createFeatureFlagsTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createFeatureFlagsTables(Schema $schema): void
    {
        if ($schema->hasTable('feature_flags')) {
            return;
        }

        $table = $schema->createTable('feature_flags');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('feature_key', 'string', ['length' => 100, 'notnull' => true]);
        $table->addColumn('label', 'string', ['length' => 150, 'notnull' => true]);
        $table->addColumn('description', 'text', ['notnull' => false]);
        $table->addColumn('category', 'string', ['length' => 50, 'notnull' => true]);
        $table->addColumn('default_enabled', 'boolean', ['default' => true, 'notnull' => true]);
        $table->addColumn('sort_order', 'integer', ['default' => 0, 'notnull' => true]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['feature_key']);

        if ($schema->hasTable('user_feature_overrides')) {
            return;
        }

        $overrideTable = $schema->createTable('user_feature_overrides');
        $overrideTable->addColumn('id', 'integer', ['autoincrement' => true]);
        $overrideTable->addColumn('user_id', 'integer', ['notnull' => true]);
        $overrideTable->addColumn('feature_flag_id', 'integer', ['notnull' => true]);
        $overrideTable->addColumn('set_by_id', 'integer', ['notnull' => false]);
        $overrideTable->addColumn('enabled', 'boolean', ['notnull' => true]);
        $overrideTable->addColumn('created_at', 'datetime', ['notnull' => true]);
        $overrideTable->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $overrideTable->setPrimaryKey(['id']);
        $overrideTable->addIndex(['user_id']);
        $overrideTable->addIndex(['feature_flag_id']);
        $overrideTable->addIndex(['set_by_id']);
        $overrideTable->addUniqueIndex(['user_id', 'feature_flag_id']);
    }

    /**
     * function createVehicleAssignmentsTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createVehicleAssignmentsTables(Schema $schema): void
    {
        if ($schema->hasTable('vehicle_assignments')) {
            return;
        }

        $table = $schema->createTable('vehicle_assignments');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('vehicle_id', 'integer', ['notnull' => true]);
        $table->addColumn('assigned_to_id', 'integer', ['notnull' => true]);
        $table->addColumn('assigned_by_id', 'integer', ['notnull' => false]);
        $table->addColumn('can_view', 'boolean', ['default' => true, 'notnull' => true]);
        $table->addColumn('can_edit', 'boolean', ['default' => true, 'notnull' => true]);
        $table->addColumn('can_add_records', 'boolean', ['default' => true, 'notnull' => true]);
        $table->addColumn('can_delete', 'boolean', ['default' => false, 'notnull' => true]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['vehicle_id']);
        $table->addIndex(['assigned_to_id']);
        $table->addIndex(['assigned_by_id']);
        $table->addUniqueIndex(['vehicle_id', 'assigned_to_id']);
    }

    /**
     * function createVehicleImagesTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createVehicleImagesTables(Schema $schema): void
    {
        if ($schema->hasTable('vehicle_images')) {
            return;
        }

        $table = $schema->createTable('vehicle_images');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('vehicle_id', 'integer', ['notnull' => true]);
        $table->addColumn('path', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('caption', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('is_primary', 'boolean', ['default' => false, 'notnull' => true]);
        $table->addColumn('display_order', 'integer', ['default' => 0, 'notnull' => true]);
        $table->addColumn('is_scraped', 'boolean', ['default' => false, 'notnull' => true]);
        $table->addColumn('source_url', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('uploaded_at', 'datetime', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['vehicle_id']);
    }

    /**
     * function createVehicleModelsTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createVehicleModelsTables(Schema $schema): void
    {
        if ($schema->hasTable('vehicle_makes')) {
            return;
        }

        $makeTable = $schema->createTable('vehicle_makes');
        $makeTable->addColumn('id', 'integer', ['autoincrement' => true]);
        $makeTable->addColumn('vehicle_type_id', 'integer', ['notnull' => true]);
        $makeTable->addColumn('name', 'string', ['length' => 100, 'notnull' => true]);
        $makeTable->addColumn('is_active', 'boolean', ['default' => true, 'notnull' => true]);
        $makeTable->setPrimaryKey(['id']);
        $makeTable->addIndex(['vehicle_type_id']);

        if ($schema->hasTable('vehicle_models')) {
            return;
        }

        $table = $schema->createTable('vehicle_models');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('make_id', 'integer', ['notnull' => true]);
        $table->addColumn('vehicle_type_id', 'integer', ['notnull' => false]);
        $table->addColumn('name', 'string', ['length' => 100, 'notnull' => true]);
        $table->addColumn('start_year', 'integer', ['notnull' => false]);
        $table->addColumn('end_year', 'integer', ['notnull' => false]);
        $table->addColumn('image_url', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('is_active', 'boolean', ['default' => true, 'notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['make_id']);
        $table->addIndex(['vehicle_type_id']);
    }

    /**
     * function createVehicleStatusHistoriesTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createVehicleStatusHistoriesTables(Schema $schema): void
    {
        if ($schema->hasTable('vehicle_status_histories')) {
            return;
        }

        $table = $schema->createTable('vehicle_status_histories');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('vehicle_id', 'integer', ['notnull' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => false]);
        $table->addColumn('old_status', 'string', ['length' => 20, 'notnull' => true]);
        $table->addColumn('new_status', 'string', ['length' => 20, 'notnull' => true]);
        $table->addColumn('change_date', 'datetime', ['notnull' => true]);
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['vehicle_id']);
        $table->addIndex(['user_id']);
    }

    /**
     * function createServiceItemsTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createServiceItemsTables(Schema $schema): void
    {
        if ($schema->hasTable('service_items')) {
            return;
        }

        $table = $schema->createTable('service_items');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('service_record_id', 'integer', ['notnull' => true]);
        $table->addColumn('consumable_id', 'integer', ['notnull' => false]);
        $table->addColumn('part_id', 'integer', ['notnull' => false]);
        $table->addColumn('type', 'string', ['length' => 20, 'notnull' => true]);
        $table->addColumn('description', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('cost', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => true]);
        $table->addColumn('quantity', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '1.00', 'notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['service_record_id']);
        $table->addIndex(['consumable_id']);
        $table->addIndex(['part_id']);
    }

    /**
     * function createUserPreferencesTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createUserPreferencesTables(Schema $schema): void
    {
        if ($schema->hasTable('user_preferences')) {
            return;
        }

        $table = $schema->createTable('user_preferences');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 150, 'notnull' => true]);
        $table->addColumn('value', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['user_id']);
    }

    /**
     * function createStockItemsTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createStockItemsTables(Schema $schema): void
    {
        if ($schema->hasTable('stock_items')) {
            return;
        }

        $table = $schema->createTable('stock_items');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('vehicle_type_id', 'integer', ['notnull' => false]);
        $table->addColumn('item_type', 'string', ['length' => 32, 'notnull' => true]);
        $table->addColumn('category', 'string', ['length' => 200, 'notnull' => true]);
        $table->addColumn('quantity', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'notnull' => true]);
        $table->addColumn('supplier', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => true]);
        $table->addColumn('description', 'string', ['length' => 500, 'notnull' => false]);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $table->addColumn('purchase_date', 'date', ['notnull' => false]);
        $table->addColumn('part_number', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('manufacturer', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('warranty', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('receipt_attachment_id', 'integer', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['user_id']);
        $table->addIndex(['vehicle_type_id']);
        $table->addIndex(['item_type', 'category', 'supplier']);
        $table->addIndex(['receipt_attachment_id']);
        $table->addForeignKeyConstraint('attachments', ['receipt_attachment_id'], ['id'], ['onDelete' => 'SET NULL']);
    }

    /**
     * function createSpecificationsTables
     *
     * @param Schema $schema
     *
     * @return void
     */
    private function createSpecificationsTables(Schema $schema): void
    {
        if ($schema->hasTable('specifications')) {
            return;
        }

        $table = $schema->createTable('specifications');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('vehicle_id', 'integer', ['notnull' => true]);
        $table->addColumn('engine_type', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('displacement', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('power', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('torque', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('compression', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('bore', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('stroke', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('fuel_system', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('cooling', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('sparkplug_type', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('coolant_type', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('coolant_capacity', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('gearbox', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('transmission', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('final_drive', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('clutch', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('engine_oil_type', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('engine_oil_capacity', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('transmission_oil_type', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('transmission_oil_capacity', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('middle_drive_oil_type', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('middle_drive_oil_capacity', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('frame', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('front_suspension', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('rear_suspension', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('static_sag_front', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('static_sag_rear', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('front_brakes', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('rear_brakes', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('front_tyre', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('rear_tyre', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('front_tyre_pressure', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('rear_tyre_pressure', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('front_wheel_travel', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('rear_wheel_travel', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('wheelbase', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('seat_height', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('ground_clearance', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('dry_weight', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('wet_weight', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('fuel_capacity', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('top_speed', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('additional_info', 'text', ['notnull' => false]);
        $table->addColumn('scraped_at', 'datetime', ['notnull' => false]);
        $table->addColumn('source_url', 'string', ['length' => 255, 'notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['vehicle_id']);
        $table->addUniqueIndex(['vehicle_id']);
        $table->addForeignKeyConstraint('vehicles', ['vehicle_id'], ['id'], ['onDelete' => 'CASCADE']);
    }
}
