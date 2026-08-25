<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track the completion date of training sessions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session ADD completed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session DROP completed_at');
    }
}
