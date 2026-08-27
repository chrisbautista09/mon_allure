<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le temps cible d’une épreuve au plan d’entraînement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_plan ADD target_duration_minutes INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_plan DROP target_duration_minutes');
    }
}
