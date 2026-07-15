<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260713081705 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE training_plan (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, pole_type VARCHAR(255) NOT NULL, target_type VARCHAR(255) NOT NULL, target_value DOUBLE PRECISION NOT NULL, target_unit VARCHAR(255) NOT NULL, terrain_type VARCHAR(255) NOT NULL, elevation_target INT DEFAULT NULL, feasibility_indicator VARCHAR(255) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, duration_weeks INT NOT NULL, is_active TINYINT NOT NULL, current_week INT NOT NULL, progress_score DOUBLE PRECISION NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE training_plan');
    }
}
