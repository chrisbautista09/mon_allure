<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize session instructions and history statuses.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session CHANGE description instructions LONGTEXT DEFAULT NULL');
        $this->addSql("UPDATE session SET status = 'completed' WHERE status IN ('done', 'partially_done')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE session SET status = 'done' WHERE status = 'completed'");
        $this->addSql("UPDATE session SET status = 'missed' WHERE status = 'cancelled'");
        $this->addSql('ALTER TABLE session CHANGE instructions description LONGTEXT DEFAULT NULL');
    }
}
