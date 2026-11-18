<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261118191526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attendances (attendance_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, status VARCHAR(20) DEFAULT \'present\' NOT NULL, checked_in_at DATETIME DEFAULT NULL, notes VARCHAR(255) DEFAULT NULL, member_id INTEGER NOT NULL, class_id INTEGER NOT NULL, CONSTRAINT FK_9C6B8FD47597D3FE FOREIGN KEY (member_id) REFERENCES members (member_id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9C6B8FD4EA000B10 FOREIGN KEY (class_id) REFERENCES classes (class_id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX attendances_status_index ON attendances (status)');
        $this->addSql('CREATE UNIQUE INDEX attendance_member_class_unique ON attendances (member_id, class_id)');
        $this->addSql('CREATE INDEX IDX_9C6B8FD47597D3FE ON attendances (member_id)');
        $this->addSql('CREATE INDEX IDX_9C6B8FD4EA000B10 ON attendances (class_id)');
        $this->addSql('CREATE TABLE classes (class_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(100) NOT NULL, class_time DATETIME NOT NULL, capacity INTEGER DEFAULT 20 NOT NULL, coach_id INTEGER DEFAULT NULL, room_id INTEGER DEFAULT NULL, CONSTRAINT FK_2ED7EC53C105691 FOREIGN KEY (coach_id) REFERENCES coaches (coach_id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2ED7EC554177093 FOREIGN KEY (room_id) REFERENCES rooms (room_id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2ED7EC53C105691 ON classes (coach_id)');
        $this->addSql('CREATE INDEX IDX_2ED7EC554177093 ON classes (room_id)');
        $this->addSql('CREATE TABLE coaches (coach_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL)');
        $this->addSql('CREATE TABLE equipment (equipment_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, quantity INTEGER DEFAULT 1 NOT NULL, condition VARCHAR(20) DEFAULT \'good\' NOT NULL, room_id INTEGER DEFAULT NULL, CONSTRAINT FK_D338D58354177093 FOREIGN KEY (room_id) REFERENCES rooms (room_id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX equipment_condition_index ON equipment (condition)');
        $this->addSql('CREATE INDEX IDX_D338D58354177093 ON equipment (room_id)');
        $this->addSql('CREATE TABLE members (member_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, join_date DATE DEFAULT NULL, user_id INTEGER DEFAULT NULL, CONSTRAINT FK_45A0D2FFA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_45A0D2FFA76ED395 ON members (user_id)');
        $this->addSql('CREATE TABLE membership_plans (plan_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, duration_days INTEGER NOT NULL, price NUMERIC(10, 2) NOT NULL, description CLOB DEFAULT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL)');
        $this->addSql('CREATE INDEX membership_plans_is_active_index ON membership_plans (is_active)');
        $this->addSql('CREATE TABLE payments (payment_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, amount NUMERIC(10, 2) NOT NULL, payment_date DATE NOT NULL, method VARCHAR(20) DEFAULT \'card\' NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, sub_id INTEGER NOT NULL, CONSTRAINT FK_65D29B3256992D9 FOREIGN KEY (sub_id) REFERENCES subscriptions (sub_id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX payments_method_index ON payments (method)');
        $this->addSql('CREATE INDEX payments_status_index ON payments (status)');
        $this->addSql('CREATE INDEX IDX_65D29B3256992D9 ON payments (sub_id)');
        $this->addSql('CREATE TABLE registrations (member_id INTEGER NOT NULL, class_id INTEGER NOT NULL, PRIMARY KEY (member_id, class_id), CONSTRAINT FK_53DE51E77597D3FE FOREIGN KEY (member_id) REFERENCES members (member_id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_53DE51E7EA000B10 FOREIGN KEY (class_id) REFERENCES classes (class_id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_53DE51E77597D3FE ON registrations (member_id)');
        $this->addSql('CREATE INDEX IDX_53DE51E7EA000B10 ON registrations (class_id)');
        $this->addSql('CREATE TABLE revoked_tokens (jti VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, PRIMARY KEY (jti))');
        $this->addSql('CREATE INDEX revoked_tokens_expires_at_index ON revoked_tokens (expires_at)');
        $this->addSql('CREATE TABLE rooms (room_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, capacity INTEGER DEFAULT 20 NOT NULL, description VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7CA11A965E237E06 ON rooms (name)');
        $this->addSql('CREATE TABLE subscriptions (sub_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, price NUMERIC(10, 2) DEFAULT NULL, member_id INTEGER NOT NULL, plan_id INTEGER DEFAULT NULL, CONSTRAINT FK_4778A017597D3FE FOREIGN KEY (member_id) REFERENCES members (member_id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_4778A01E899029B FOREIGN KEY (plan_id) REFERENCES membership_plans (plan_id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX subscriptions_plan_id_index ON subscriptions (plan_id)');
        $this->addSql('CREATE INDEX IDX_4778A017597D3FE ON subscriptions (member_id)');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, email_verified_at DATETIME DEFAULT NULL, password VARCHAR(255) NOT NULL, remember_token VARCHAR(100) DEFAULT NULL, role VARCHAR(20) DEFAULT \'client\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE INDEX users_role_index ON users (role)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE attendances');
        $this->addSql('DROP TABLE classes');
        $this->addSql('DROP TABLE coaches');
        $this->addSql('DROP TABLE equipment');
        $this->addSql('DROP TABLE members');
        $this->addSql('DROP TABLE membership_plans');
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE registrations');
        $this->addSql('DROP TABLE revoked_tokens');
        $this->addSql('DROP TABLE rooms');
        $this->addSql('DROP TABLE subscriptions');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
