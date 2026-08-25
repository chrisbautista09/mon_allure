<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l’historique JSON des adaptations aux plans';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_plan ADD adaptation_history JSON DEFAULT NULL');
        $this->addSql('UPDATE training_plan SET adaptation_history = JSON_ARRAY() WHERE adaptation_history IS NULL');
        $this->addSql('ALTER TABLE training_plan MODIFY adaptation_history JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_plan DROP adaptation_history');
    }
}
