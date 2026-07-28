<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260713082656 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE training_plan ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE training_plan ADD CONSTRAINT FK_D2C01C3EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_D2C01C3EA76ED395 ON training_plan (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE training_plan DROP FOREIGN KEY FK_D2C01C3EA76ED395');
        $this->addSql('DROP INDEX IDX_D2C01C3EA76ED395 ON training_plan');
        $this->addSql('ALTER TABLE training_plan DROP user_id');
    }
}
