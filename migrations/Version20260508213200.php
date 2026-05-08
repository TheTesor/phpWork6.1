<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508213200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users, devices, and refresh token history tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles CLOB NOT NULL --(DC2Type:json)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON app_user (email)');

        $this->addSql('CREATE TABLE device (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, user_agent CLOB DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, refresh_token_hash VARCHAR(255) DEFAULT NULL, expires_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , is_revoked BOOLEAN NOT NULL, is_compromised BOOLEAN NOT NULL, last_used_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_92FB68EBA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_device_user ON device (user_id)');

        $this->addSql('CREATE TABLE refresh_token_history (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, device_id INTEGER NOT NULL, refresh_token_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , expires_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_4C0D7B2DA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_4C0D7B294A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_refresh_token_history_user ON refresh_token_history (user_id)');
        $this->addSql('CREATE INDEX idx_refresh_token_history_device ON refresh_token_history (device_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE refresh_token_history');
        $this->addSql('DROP TABLE device');
        $this->addSql('DROP TABLE app_user');
    }
}
