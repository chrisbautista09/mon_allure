<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723075329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE algorithm_parameter ADD parameter_key VARCHAR(100) NOT NULL, ADD parameter_value DOUBLE PRECISION NOT NULL, ADD description LONGTEXT DEFAULT NULL, ADD updated_at DATETIME NOT NULL, DROP progression_max_percent, DROP recovery_week_frequency, DROP max_sessions_discovery, DROP max_sessions_intermediate, DROP max_sessions_performance, DROP long_run_ratio_max, DROP coef_endurance, DROP coef_active, DROP coef_threshold, DROP coef_vma, DROP default_plan_min_weeks, DROP default_plan_max_weeks, DROP missed_session_tolerance, DROP success_validation_rate');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BE0B375D8E88E200 ON algorithm_parameter (parameter_key)');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY `FK_9474526C35A79295`');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY `FK_9474526C613FECDF`');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY `FK_9474526CA76ED395`');
        $this->addSql('ALTER TABLE comment CHANGE created_at created_at DATETIME NOT NULL, CHANGE session_id session_id INT DEFAULT NULL, CHANGE training_plan_id training_plan_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C35A79295 FOREIGN KEY (training_plan_id) REFERENCES training_plan (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C613FECDF FOREIGN KEY (session_id) REFERENCES session (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE intensity_zone ADD vma_coef_min DOUBLE PRECISION NOT NULL, ADD vma_coef_max DOUBLE PRECISION NOT NULL, ADD fcm_percent_min DOUBLE PRECISION NOT NULL, ADD fcm_percent_max DOUBLE PRECISION NOT NULL, DROP heart_rate_min_pcent, DROP heart_rate_max_pcent, CHANGE name name VARCHAR(10) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C7A8C0C25E237E06 ON intensity_zone (name)');
        $this->addSql('ALTER TABLE performance DROP INDEX UNIQ_82D79681613FECDF, ADD INDEX IDX_82D79681613FECDF (session_id)');
        $this->addSql('ALTER TABLE performance DROP FOREIGN KEY `FK_82D79681613FECDF`');
        $this->addSql('ALTER TABLE performance DROP FOREIGN KEY `FK_82D79681A76ED395`');
        $this->addSql('ALTER TABLE performance ADD avg_hr INT DEFAULT NULL, ADD comment LONGTEXT DEFAULT NULL, ADD created_at DATETIME NOT NULL, DROP terrain_type, DROP create_at, CHANGE distance_realized distance_km DOUBLE PRECISION NOT NULL, CHANGE time_realized duration_sec INT NOT NULL, CHANGE elevation_d_plus elevation_dplus INT DEFAULT NULL');
        $this->addSql('ALTER TABLE performance ADD CONSTRAINT FK_82D79681613FECDF FOREIGN KEY (session_id) REFERENCES session (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE performance ADD CONSTRAINT FK_82D79681A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile DROP FOREIGN KEY `FK_8157AA0FA76ED395`');
        $this->addSql('ALTER TABLE profile DROP profile, CHANGE first_name first_name VARCHAR(100) NOT NULL, CHANGE last_name last_name VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE profile ADD CONSTRAINT FK_8157AA0FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE session DROP FOREIGN KEY `FK_D044D5D435A79295`');
        $this->addSql('ALTER TABLE session ADD week_index INT NOT NULL, ADD day_of_week INT NOT NULL, ADD description LONGTEXT DEFAULT NULL, ADD session_type VARCHAR(30) NOT NULL, ADD planned_distance_km DOUBLE PRECISION DEFAULT NULL, ADD planned_duration_min INT DEFAULT NULL, ADD planned_elevation_dplus INT DEFAULT NULL, ADD planned_vma_coef DOUBLE PRECISION DEFAULT NULL, ADD planned_fcm_zone VARCHAR(10) DEFAULT NULL, DROP instructions, DROP target_duration, DROP target_distance, DROP target_speed, DROP target_pace, DROP target_hr_min, DROP target_hr_max, CHANGE date date DATE NOT NULL, CHANGE title title VARCHAR(150) NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'planned\' NOT NULL');
        $this->addSql('ALTER TABLE session ADD CONSTRAINT FK_D044D5D435A79295 FOREIGN KEY (training_plan_id) REFERENCES training_plan (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE session_intensity_zone DROP FOREIGN KEY `FK_50E06864613FECDF`');
        $this->addSql('ALTER TABLE session_intensity_zone DROP FOREIGN KEY `FK_50E06864E79D7B4D`');
        $this->addSql('ALTER TABLE session_intensity_zone ADD duration_percent DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE session_intensity_zone ADD CONSTRAINT FK_50E06864613FECDF FOREIGN KEY (session_id) REFERENCES session (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE session_intensity_zone ADD CONSTRAINT FK_50E06864E79D7B4D FOREIGN KEY (intensity_zone_id) REFERENCES intensity_zone (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX unique_session_intensity_zone ON session_intensity_zone (session_id, intensity_zone_id)');
        $this->addSql('ALTER TABLE training_plan DROP FOREIGN KEY `FK_D2C01C3EA76ED395`');
        $this->addSql('ALTER TABLE training_plan DROP FOREIGN KEY `FK_D2C01C3E5F001D1`');
        $this->addSql('DROP INDEX IDX_D2C01C3E5F001D1 ON training_plan');
        $this->addSql('ALTER TABLE training_plan DROP algorithm_parameter_id, CHANGE name name VARCHAR(150) NOT NULL, CHANGE pole_type pole_type VARCHAR(30) NOT NULL, CHANGE target_type target_type VARCHAR(20) NOT NULL, CHANGE target_unit target_unit VARCHAR(10) NOT NULL, CHANGE terrain_type terrain_type VARCHAR(20) NOT NULL, CHANGE feasibility_indicator feasibility_indicator VARCHAR(30) NOT NULL, CHANGE is_active is_active TINYINT DEFAULT 1 NOT NULL, CHANGE current_week current_week INT DEFAULT 1 NOT NULL, CHANGE progress_score progress_score DOUBLE PRECISION DEFAULT 0 NOT NULL, CHANGE user_id user_id INT NOT NULL, CHANGE elevation_target elevation_target_dplus INT DEFAULT NULL');
        $this->addSql('ALTER TABLE training_plan ADD CONSTRAINT FK_D2C01C3EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_BE0B375D8E88E200 ON algorithm_parameter');
        $this->addSql('ALTER TABLE algorithm_parameter ADD recovery_week_frequency INT NOT NULL, ADD max_sessions_discovery INT NOT NULL, ADD max_sessions_intermediate INT NOT NULL, ADD max_sessions_performance INT NOT NULL, ADD long_run_ratio_max DOUBLE PRECISION NOT NULL, ADD coef_endurance DOUBLE PRECISION NOT NULL, ADD coef_active DOUBLE PRECISION NOT NULL, ADD coef_threshold DOUBLE PRECISION NOT NULL, ADD coef_vma DOUBLE PRECISION NOT NULL, ADD default_plan_min_weeks INT NOT NULL, ADD default_plan_max_weeks INT NOT NULL, ADD missed_session_tolerance INT NOT NULL, ADD success_validation_rate DOUBLE PRECISION NOT NULL, DROP parameter_key, DROP description, DROP updated_at, CHANGE parameter_value progression_max_percent DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CA76ED395');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C613FECDF');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C35A79295');
        $this->addSql('ALTER TABLE comment CHANGE created_at created_at DATE NOT NULL, CHANGE session_id session_id INT NOT NULL, CHANGE training_plan_id training_plan_id INT NOT NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT `FK_9474526CA76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT `FK_9474526C613FECDF` FOREIGN KEY (session_id) REFERENCES session (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT `FK_9474526C35A79295` FOREIGN KEY (training_plan_id) REFERENCES training_plan (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('DROP INDEX UNIQ_C7A8C0C25E237E06 ON intensity_zone');
        $this->addSql('ALTER TABLE intensity_zone ADD heart_rate_min_pcent DOUBLE PRECISION NOT NULL, ADD heart_rate_max_pcent DOUBLE PRECISION NOT NULL, DROP vma_coef_min, DROP vma_coef_max, DROP fcm_percent_min, DROP fcm_percent_max, CHANGE name name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE performance DROP INDEX IDX_82D79681613FECDF, ADD UNIQUE INDEX UNIQ_82D79681613FECDF (session_id)');
        $this->addSql('ALTER TABLE performance DROP FOREIGN KEY FK_82D79681613FECDF');
        $this->addSql('ALTER TABLE performance DROP FOREIGN KEY FK_82D79681A76ED395');
        $this->addSql('ALTER TABLE performance ADD elevation_d_plus INT DEFAULT NULL, ADD terrain_type VARCHAR(255) NOT NULL, ADD create_at DATE NOT NULL, DROP elevation_dplus, DROP avg_hr, DROP comment, DROP created_at, CHANGE duration_sec time_realized INT NOT NULL, CHANGE distance_km distance_realized DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE performance ADD CONSTRAINT `FK_82D79681613FECDF` FOREIGN KEY (session_id) REFERENCES session (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE performance ADD CONSTRAINT `FK_82D79681A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE profile DROP FOREIGN KEY FK_8157AA0FA76ED395');
        $this->addSql('ALTER TABLE profile ADD profile VARCHAR(255) NOT NULL, CHANGE first_name first_name VARCHAR(255) NOT NULL, CHANGE last_name last_name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE profile ADD CONSTRAINT `FK_8157AA0FA76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE session DROP FOREIGN KEY FK_D044D5D435A79295');
        $this->addSql('ALTER TABLE session ADD instructions VARCHAR(255) NOT NULL, ADD target_duration INT NOT NULL, ADD target_distance DOUBLE PRECISION NOT NULL, ADD target_speed DOUBLE PRECISION NOT NULL, ADD target_pace VARCHAR(255) NOT NULL, ADD target_hr_min INT NOT NULL, ADD target_hr_max INT NOT NULL, DROP week_index, DROP day_of_week, DROP description, DROP session_type, DROP planned_distance_km, DROP planned_duration_min, DROP planned_elevation_dplus, DROP planned_vma_coef, DROP planned_fcm_zone, CHANGE title title VARCHAR(255) NOT NULL, CHANGE date date TIME NOT NULL, CHANGE status status VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE session ADD CONSTRAINT `FK_D044D5D435A79295` FOREIGN KEY (training_plan_id) REFERENCES training_plan (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE session_intensity_zone DROP FOREIGN KEY FK_50E06864E79D7B4D');
        $this->addSql('ALTER TABLE session_intensity_zone DROP FOREIGN KEY FK_50E06864613FECDF');
        $this->addSql('DROP INDEX unique_session_intensity_zone ON session_intensity_zone');
        $this->addSql('ALTER TABLE session_intensity_zone DROP duration_percent');
        $this->addSql('ALTER TABLE session_intensity_zone ADD CONSTRAINT `FK_50E06864E79D7B4D` FOREIGN KEY (intensity_zone_id) REFERENCES intensity_zone (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE session_intensity_zone ADD CONSTRAINT `FK_50E06864613FECDF` FOREIGN KEY (session_id) REFERENCES session (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE training_plan DROP FOREIGN KEY FK_D2C01C3EA76ED395');
        $this->addSql('ALTER TABLE training_plan ADD algorithm_parameter_id INT NOT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE pole_type pole_type VARCHAR(255) NOT NULL, CHANGE target_type target_type VARCHAR(255) NOT NULL, CHANGE target_unit target_unit VARCHAR(255) NOT NULL, CHANGE terrain_type terrain_type VARCHAR(255) NOT NULL, CHANGE feasibility_indicator feasibility_indicator VARCHAR(255) NOT NULL, CHANGE is_active is_active TINYINT NOT NULL, CHANGE current_week current_week INT NOT NULL, CHANGE progress_score progress_score DOUBLE PRECISION NOT NULL, CHANGE user_id user_id INT DEFAULT NULL, CHANGE elevation_target_dplus elevation_target INT DEFAULT NULL');
        $this->addSql('ALTER TABLE training_plan ADD CONSTRAINT `FK_D2C01C3EA76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE training_plan ADD CONSTRAINT `FK_D2C01C3E5F001D1` FOREIGN KEY (algorithm_parameter_id) REFERENCES algorithm_parameter (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_D2C01C3E5F001D1 ON training_plan (algorithm_parameter_id)');
    }
}
