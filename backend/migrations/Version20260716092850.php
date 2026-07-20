<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260716092850 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE profile (id INT AUTO_INCREMENT NOT NULL, profile VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, age INT NOT NULL, vma DOUBLE PRECISION DEFAULT NULL, vo2max DOUBLE PRECISION DEFAULT NULL, fcm INT DEFAULT NULL, fcr INT DEFAULT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_8157AA0FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE training_plan (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, pole_type VARCHAR(255) NOT NULL, target_type VARCHAR(255) NOT NULL, target_value DOUBLE PRECISION NOT NULL, target_unit VARCHAR(255) NOT NULL, terrain_type VARCHAR(255) NOT NULL, elevation_target INT DEFAULT NULL, feasibility_indicator VARCHAR(255) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, duration_weeks INT NOT NULL, is_active TINYINT NOT NULL, current_week INT NOT NULL, progress_score DOUBLE PRECISION NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_D2C01C3EA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE profile ADD CONSTRAINT FK_8157AA0FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE training_plan ADD CONSTRAINT FK_D2C01C3EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE profile DROP FOREIGN KEY FK_8157AA0FA76ED395');
        $this->addSql('ALTER TABLE training_plan DROP FOREIGN KEY FK_D2C01C3EA76ED395');
        $this->addSql('DROP TABLE profile');
        $this->addSql('DROP TABLE training_plan');
    }
}
