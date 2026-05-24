<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create password_reset_tokens table for secure password reset flow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE password_reset_tokens (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                token VARCHAR(255) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                used TINYINT(1) DEFAULT 0 NOT NULL,
                used_at DATETIME NULL COMMENT \'(DC2Type:datetime_immutable)\',
                PRIMARY KEY(id),
                KEY user_id (user_id),
                KEY idx_token (token),
                KEY idx_expires_at (expires_at),
                CONSTRAINT FK_users_password_reset FOREIGN KEY (user_id)
                    REFERENCES users(id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS password_reset_tokens');
    }
}
