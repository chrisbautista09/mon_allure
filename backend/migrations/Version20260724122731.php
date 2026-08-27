<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Marqueur historique restauré après validation d’une reconstruction complète.
 *
 * Cette version a été exécutée sur la base d’origine, mais son fichier n’a pas
 * été conservé. Ses éventuels changements sont entièrement couverts par les
 * migrations actuellement versionnées.
 */
final class Version20260724122731 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Marqueur historique sans opération : changements couverts par les migrations suivantes';
    }

    public function up(Schema $schema): void
    {
        // Aucune opération : reconstruction complète validée sans cette migration.
    }

    public function down(Schema $schema): void
    {
        // Aucune opération historique à annuler.
    }
}
