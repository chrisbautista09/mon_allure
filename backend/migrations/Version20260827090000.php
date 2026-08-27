<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne le type created_at du plan avec le mapping Doctrine';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_plan CHANGE created_at created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_plan CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
