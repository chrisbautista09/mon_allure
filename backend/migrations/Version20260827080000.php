<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l’historique JSON des anomalies de monitoring aux plans';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_plan ADD monitoring_history JSON DEFAULT NULL');
        $this->addSql('UPDATE training_plan SET monitoring_history = JSON_ARRAY() WHERE monitoring_history IS NULL');
        $this->addSql('ALTER TABLE training_plan MODIFY monitoring_history JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_plan DROP monitoring_history');
    }
}
