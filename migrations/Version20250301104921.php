<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250301104921 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project ADD rate_id INT DEFAULT NULL, DROP rate');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEBC999F9F FOREIGN KEY (rate_id) REFERENCES rate (id)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEBC999F9F ON project (rate_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EEBC999F9F');
        $this->addSql('DROP INDEX IDX_2FB3D0EEBC999F9F ON project');
        $this->addSql('ALTER TABLE project ADD rate VARCHAR(255) DEFAULT NULL, DROP rate_id');
    }
}
