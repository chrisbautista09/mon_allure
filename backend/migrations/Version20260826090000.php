<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Guarantee that a training session has at most one performance.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE performance DROP FOREIGN KEY FK_82D79681613FECDF');
        $this->addSql('DROP INDEX IDX_82D79681613FECDF ON performance');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_82D79681613FECDF ON performance (session_id)');
        $this->addSql('ALTER TABLE performance ADD CONSTRAINT FK_82D79681613FECDF FOREIGN KEY (session_id) REFERENCES session (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE performance DROP FOREIGN KEY FK_82D79681613FECDF');
        $this->addSql('DROP INDEX UNIQ_82D79681613FECDF ON performance');
        $this->addSql('CREATE INDEX IDX_82D79681613FECDF ON performance (session_id)');
        $this->addSql('ALTER TABLE performance ADD CONSTRAINT FK_82D79681613FECDF FOREIGN KEY (session_id) REFERENCES session (id) ON DELETE SET NULL');
    }
}
