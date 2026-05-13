<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional receipt attachment reference to stock_items';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('stock_items')) {
            return;
        }

        $table = $schema->getTable('stock_items');

        if (!$table->hasColumn('receipt_attachment_id')) {
            $table->addColumn('receipt_attachment_id', 'integer', ['notnull' => false]);
        }

        if (!$table->hasIndex('IDX_7D0EF5BC79F22B74')) {
            $table->addIndex(['receipt_attachment_id'], 'IDX_7D0EF5BC79F22B74');
        }

        if ($schema->hasTable('attachments') && !$table->hasForeignKey('FK_7D0EF5BC79F22B74')) {
            $table->addForeignKeyConstraint(
                'attachments',
                ['receipt_attachment_id'],
                ['id'],
                ['onDelete' => 'SET NULL'],
                'FK_7D0EF5BC79F22B74'
            );
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('stock_items')) {
            return;
        }

        $table = $schema->getTable('stock_items');

        if ($table->hasForeignKey('FK_7D0EF5BC79F22B74')) {
            $table->removeForeignKey('FK_7D0EF5BC79F22B74');
        }

        if ($table->hasIndex('IDX_7D0EF5BC79F22B74')) {
            $table->dropIndex('IDX_7D0EF5BC79F22B74');
        }

        if ($table->hasColumn('receipt_attachment_id')) {
            $table->dropColumn('receipt_attachment_id');
        }
    }
}
