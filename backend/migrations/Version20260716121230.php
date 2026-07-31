<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260716121230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE performance (id INT AUTO_INCREMENT NOT NULL, time_realized INT NOT NULL, distance_realized DOUBLE PRECISION NOT NULL, elevation_d_plus INT DEFAULT NULL, terrain_type VARCHAR(255) NOT NULL, create_at DATE NOT NULL, session_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_82D79681613FECDF (session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE performance ADD CONSTRAINT FK_82D79681613FECDF FOREIGN KEY (session_id) REFERENCES session (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE performance DROP FOREIGN KEY FK_82D79681613FECDF');
        $this->addSql('DROP TABLE performance');
    }
}
