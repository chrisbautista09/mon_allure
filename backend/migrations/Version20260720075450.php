<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260720075450 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE algorithm_parameter CHANGE defautl_plan_max_weeks default_plan_max_weeks INT NOT NULL, CHANGE succes_validation_rate success_validation_rate DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE comment ADD user_id INT NOT NULL, ADD session_id INT NOT NULL, ADD training_plan_id INT NOT NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C613FECDF FOREIGN KEY (session_id) REFERENCES session (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C35A79295 FOREIGN KEY (training_plan_id) REFERENCES training_plan (id)');
        $this->addSql('CREATE INDEX IDX_9474526CA76ED395 ON comment (user_id)');
        $this->addSql('CREATE INDEX IDX_9474526C613FECDF ON comment (session_id)');
        $this->addSql('CREATE INDEX IDX_9474526C35A79295 ON comment (training_plan_id)');
        $this->addSql('ALTER TABLE session ADD training_plan_id INT NOT NULL');
        $this->addSql('ALTER TABLE session ADD CONSTRAINT FK_D044D5D435A79295 FOREIGN KEY (training_plan_id) REFERENCES training_plan (id)');
        $this->addSql('CREATE INDEX IDX_D044D5D435A79295 ON session (training_plan_id)');
        $this->addSql('ALTER TABLE session_intensity_zone ADD intensity_zone_id INT NOT NULL, DROP duration_pcent');
        $this->addSql('ALTER TABLE session_intensity_zone ADD CONSTRAINT FK_50E06864E79D7B4D FOREIGN KEY (intensity_zone_id) REFERENCES intensity_zone (id)');
        $this->addSql('CREATE INDEX IDX_50E06864E79D7B4D ON session_intensity_zone (intensity_zone_id)');
        $this->addSql('ALTER TABLE training_plan ADD algorithm_parameter_id INT NOT NULL');
        $this->addSql('ALTER TABLE training_plan ADD CONSTRAINT FK_D2C01C3E5F001D1 FOREIGN KEY (algorithm_parameter_id) REFERENCES algorithm_parameter (id)');
        $this->addSql('CREATE INDEX IDX_D2C01C3E5F001D1 ON training_plan (algorithm_parameter_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE algorithm_parameter CHANGE default_plan_max_weeks defautl_plan_max_weeks INT NOT NULL, CHANGE success_validation_rate succes_validation_rate DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CA76ED395');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C613FECDF');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C35A79295');
        $this->addSql('DROP INDEX IDX_9474526CA76ED395 ON comment');
        $this->addSql('DROP INDEX IDX_9474526C613FECDF ON comment');
        $this->addSql('DROP INDEX IDX_9474526C35A79295 ON comment');
        $this->addSql('ALTER TABLE comment DROP user_id, DROP session_id, DROP training_plan_id');
        $this->addSql('ALTER TABLE session DROP FOREIGN KEY FK_D044D5D435A79295');
        $this->addSql('DROP INDEX IDX_D044D5D435A79295 ON session');
        $this->addSql('ALTER TABLE session DROP training_plan_id');
        $this->addSql('ALTER TABLE session_intensity_zone DROP FOREIGN KEY FK_50E06864E79D7B4D');
        $this->addSql('DROP INDEX IDX_50E06864E79D7B4D ON session_intensity_zone');
        $this->addSql('ALTER TABLE session_intensity_zone ADD duration_pcent DOUBLE PRECISION NOT NULL, DROP intensity_zone_id');
        $this->addSql('ALTER TABLE training_plan DROP FOREIGN KEY FK_D2C01C3E5F001D1');
        $this->addSql('DROP INDEX IDX_D2C01C3E5F001D1 ON training_plan');
        $this->addSql('ALTER TABLE training_plan DROP algorithm_parameter_id');
    }
}
