<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250226122814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project ADD deadline DATETIME NOT NULL, ADD priority VARCHAR(255) NOT NULL, ADD estimated_budget NUMERIC(15, 2) DEFAULT NULL, ADD estimated_minutes INT DEFAULT NULL, ADD last_updated DATETIME NOT NULL, CHANGE client_id client_id INT NOT NULL, CHANGE active is_archived TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project DROP deadline, DROP priority, DROP estimated_budget, DROP estimated_minutes, DROP last_updated, CHANGE client_id client_id INT DEFAULT NULL, CHANGE is_archived active TINYINT(1) NOT NULL');
    }
}
