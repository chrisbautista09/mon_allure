<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the configurable training location to the physiological profile.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile ADD city VARCHAR(100) DEFAULT NULL, ADD postal_code VARCHAR(20) DEFAULT NULL, ADD country VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP city, DROP postal_code, DROP country');
    }
}
