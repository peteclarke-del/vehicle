<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209160513 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE feature_flags (id INT AUTO_INCREMENT NOT NULL, feature_key VARCHAR(100) NOT NULL, label VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, category VARCHAR(50) NOT NULL, default_enabled TINYINT(1) DEFAULT 1 NOT NULL, sort_order INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_C25B4BB3C8FDEE1A (feature_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_feature_overrides (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, feature_flag_id INT NOT NULL, set_by_id INT DEFAULT NULL, enabled TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_85ECA143A76ED395 (user_id), INDEX IDX_85ECA143A0887FEC (feature_flag_id), INDEX IDX_85ECA1433E16DC62 (set_by_id), UNIQUE INDEX uq_user_feature (user_id, feature_flag_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vehicle_assignments (id INT AUTO_INCREMENT NOT NULL, vehicle_id INT NOT NULL, assigned_to_id INT NOT NULL, assigned_by_id INT DEFAULT NULL, can_view TINYINT(1) DEFAULT 1 NOT NULL, can_edit TINYINT(1) DEFAULT 1 NOT NULL, can_add_records TINYINT(1) DEFAULT 1 NOT NULL, can_delete TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_BEB12DAB545317D1 (vehicle_id), INDEX IDX_BEB12DABF4BD7827 (assigned_to_id), INDEX IDX_BEB12DAB6E6F1246 (assigned_by_id), UNIQUE INDEX uq_vehicle_user (vehicle_id, assigned_to_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_feature_overrides ADD CONSTRAINT FK_85ECA143A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_feature_overrides ADD CONSTRAINT FK_85ECA143A0887FEC FOREIGN KEY (feature_flag_id) REFERENCES feature_flags (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_feature_overrides ADD CONSTRAINT FK_85ECA1433E16DC62 FOREIGN KEY (set_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE vehicle_assignments ADD CONSTRAINT FK_BEB12DAB545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_assignments ADD CONSTRAINT FK_BEB12DABF4BD7827 FOREIGN KEY (assigned_to_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_assignments ADD CONSTRAINT FK_BEB12DAB6E6F1246 FOREIGN KEY (assigned_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_feature_overrides DROP FOREIGN KEY FK_85ECA143A76ED395');
        $this->addSql('ALTER TABLE user_feature_overrides DROP FOREIGN KEY FK_85ECA143A0887FEC');
        $this->addSql('ALTER TABLE user_feature_overrides DROP FOREIGN KEY FK_85ECA1433E16DC62');
        $this->addSql('ALTER TABLE vehicle_assignments DROP FOREIGN KEY FK_BEB12DAB545317D1');
        $this->addSql('ALTER TABLE vehicle_assignments DROP FOREIGN KEY FK_BEB12DABF4BD7827');
        $this->addSql('ALTER TABLE vehicle_assignments DROP FOREIGN KEY FK_BEB12DAB6E6F1246');
        $this->addSql('DROP TABLE feature_flags');
        $this->addSql('DROP TABLE user_feature_overrides');
        $this->addSql('DROP TABLE vehicle_assignments');
    }
}
