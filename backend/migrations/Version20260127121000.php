<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260127121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reports table for persisted report metadata';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reports (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(255) NOT NULL, template_key VARCHAR(255) DEFAULT NULL, payload JSON DEFAULT NULL, vehicle_id INT DEFAULT NULL, generated_at DATETIME NOT NULL, INDEX IDX_REPORT_USER (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_REPORT_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reports DROP FOREIGN KEY FK_REPORT_USER');
        $this->addSql('DROP TABLE reports');
    }
}
