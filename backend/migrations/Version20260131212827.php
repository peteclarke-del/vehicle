<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260131212827 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE consumables DROP FOREIGN KEY FK_9B2FDD30545317D1');
        $this->addSql('ALTER TABLE consumables ADD CONSTRAINT FK_9B2FDD30545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fuel_records DROP FOREIGN KEY FK_33A12AE0545317D1');
        $this->addSql('ALTER TABLE fuel_records ADD CONSTRAINT FK_33A12AE0545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY FK_6940A7FE545317D1');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FE545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE road_tax DROP FOREIGN KEY FK_BB1D046A545317D1');
        $this->addSql('ALTER TABLE road_tax ADD CONSTRAINT FK_BB1D046A545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE consumables DROP FOREIGN KEY FK_9B2FDD30545317D1');
        $this->addSql('ALTER TABLE consumables ADD CONSTRAINT FK_9B2FDD30545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE fuel_records DROP FOREIGN KEY FK_33A12AE0545317D1');
        $this->addSql('ALTER TABLE fuel_records ADD CONSTRAINT FK_33A12AE0545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY FK_6940A7FE545317D1');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FE545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE road_tax DROP FOREIGN KEY FK_BB1D046A545317D1');
        $this->addSql('ALTER TABLE road_tax ADD CONSTRAINT FK_BB1D046A545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
