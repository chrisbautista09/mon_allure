<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Require every performance to reference a training session.';
    }

    public function up(Schema $schema): void
    {
        $orphanCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM performance WHERE session_id IS NULL',
        );
        $this->abortIf(
            $orphanCount > 0,
            sprintf('%d performance(s) sans séance doivent être régularisées avant la migration.', $orphanCount),
        );

        $this->addSql('ALTER TABLE performance DROP FOREIGN KEY FK_82D79681613FECDF');
        $this->addSql('ALTER TABLE performance MODIFY session_id INT NOT NULL');
        $this->addSql('ALTER TABLE performance ADD CONSTRAINT FK_82D79681613FECDF FOREIGN KEY (session_id) REFERENCES session (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE performance DROP FOREIGN KEY FK_82D79681613FECDF');
        $this->addSql('ALTER TABLE performance MODIFY session_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE performance ADD CONSTRAINT FK_82D79681613FECDF FOREIGN KEY (session_id) REFERENCES session (id) ON DELETE SET NULL');
    }
}
