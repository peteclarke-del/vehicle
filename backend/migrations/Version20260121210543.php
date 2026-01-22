<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260121210543 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service_records ADD mot_record_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE service_records ADD CONSTRAINT FK_53190ADAB17D92CD FOREIGN KEY (mot_record_id) REFERENCES mot_records (id)');
        $this->addSql('CREATE INDEX IDX_53190ADAB17D92CD ON service_records (mot_record_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service_records DROP FOREIGN KEY FK_53190ADAB17D92CD');
        $this->addSql('DROP INDEX IDX_53190ADAB17D92CD ON service_records');
        $this->addSql('ALTER TABLE service_records DROP mot_record_id');
    }
}
