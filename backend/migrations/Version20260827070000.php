<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la date de création et les index nécessaires au monitoring administratif des plans';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_plan ADD created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('UPDATE training_plan SET created_at = start_date');
        $this->addSql('ALTER TABLE training_plan MODIFY created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_TRAINING_PLAN_MONITORING_CREATED_AT ON training_plan (created_at)');
        $this->addSql('CREATE INDEX IDX_TRAINING_PLAN_MONITORING_FEASIBILITY ON training_plan (feasibility_indicator)');
        $this->addSql('CREATE INDEX IDX_TRAINING_PLAN_MONITORING_PROGRESS ON training_plan (progress_score)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_TRAINING_PLAN_MONITORING_CREATED_AT ON training_plan');
        $this->addSql('DROP INDEX IDX_TRAINING_PLAN_MONITORING_FEASIBILITY ON training_plan');
        $this->addSql('DROP INDEX IDX_TRAINING_PLAN_MONITORING_PROGRESS ON training_plan');
        $this->addSql('ALTER TABLE training_plan DROP created_at');
    }
}
