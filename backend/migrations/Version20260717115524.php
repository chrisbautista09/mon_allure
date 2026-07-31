<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260717115524 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE algorithm_parameter (id INT AUTO_INCREMENT NOT NULL, progression_max_percent DOUBLE PRECISION NOT NULL, recovery_week_frequency INT NOT NULL, max_sessions_discovery INT NOT NULL, max_sessions_intermediate INT NOT NULL, max_sessions_performance INT NOT NULL, long_run_ratio_max DOUBLE PRECISION NOT NULL, coef_endurance DOUBLE PRECISION NOT NULL, coef_active DOUBLE PRECISION NOT NULL, coef_threshold DOUBLE PRECISION NOT NULL, coef_vma DOUBLE PRECISION NOT NULL, default_plan_min_weeks INT NOT NULL, defautl_plan_max_weeks INT NOT NULL, missed_session_tolerance INT NOT NULL, succes_validation_rate DOUBLE PRECISION NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATE NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE intensity_zone (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, heart_rate_min_pcent DOUBLE PRECISION NOT NULL, heart_rate_max_pcent DOUBLE PRECISION NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE session_intensity_zone (id INT AUTO_INCREMENT NOT NULL, duration_pcent DOUBLE PRECISION NOT NULL, session_id INT NOT NULL, INDEX IDX_50E06864613FECDF (session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE session_intensity_zone ADD CONSTRAINT FK_50E06864613FECDF FOREIGN KEY (session_id) REFERENCES session (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE session_intensity_zone DROP FOREIGN KEY FK_50E06864613FECDF');
        $this->addSql('DROP TABLE algorithm_parameter');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE intensity_zone');
        $this->addSql('DROP TABLE session_intensity_zone');
    }
}
