<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add company currency defaulting to DKK; leave rate amounts unchanged.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE company ADD currency VARCHAR(3) DEFAULT 'DKK' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company DROP currency');
    }
}
