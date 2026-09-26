<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917092520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the initial WorkTimeFlow database schema.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, contact_phone VARCHAR(20) NOT NULL, contact_email VARCHAR(255) NOT NULL, contact_person VARCHAR(255) NOT NULL, adress VARCHAR(255) DEFAULT NULL, postal_code VARCHAR(255) DEFAULT NULL, country VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE company (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, is_archived TINYINT NOT NULL, deadline DATETIME NOT NULL, priority VARCHAR(255) NOT NULL, estimated_budget NUMERIC(15, 2) DEFAULT NULL, estimated_minutes INT DEFAULT NULL, last_updated DATETIME NOT NULL, is_paid TINYINT NOT NULL, client_id INT NOT NULL, rate_id INT DEFAULT NULL, INDEX IDX_2FB3D0EE19EB6921 (client_id), INDEX IDX_2FB3D0EEBC999F9F (rate_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE rate (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, value NUMERIC(10, 2) NOT NULL, company_id INT NOT NULL, INDEX IDX_DFEC3F39979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team_project (team_id INT NOT NULL, project_id INT NOT NULL, INDEX IDX_D0CAA1D9296CD8AE (team_id), INDEX IDX_D0CAA1D9166D1F9C (project_id), PRIMARY KEY (team_id, project_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE timelog (id INT AUTO_INCREMENT NOT NULL, total_minutes INT NOT NULL, date DATETIME NOT NULL, description VARCHAR(255) DEFAULT NULL, todo_id INT DEFAULT NULL, user_id INT DEFAULT NULL, INDEX IDX_B79470CEEA1EBC33 (todo_id), INDEX IDX_B79470CEA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE todo (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, date_start DATETIME NOT NULL, date_end DATETIME NOT NULL, status VARCHAR(50) DEFAULT \'Pending\' NOT NULL, project_id INT DEFAULT NULL, INDEX IDX_5A0EB6A0166D1F9C (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, username VARCHAR(255) NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_team (user_id INT NOT NULL, team_id INT NOT NULL, INDEX IDX_BE61EAD6A76ED395 (user_id), INDEX IDX_BE61EAD6296CD8AE (team_id), PRIMARY KEY (user_id, team_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEBC999F9F FOREIGN KEY (rate_id) REFERENCES rate (id)');
        $this->addSql('ALTER TABLE rate ADD CONSTRAINT FK_DFEC3F39979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)');
        $this->addSql('ALTER TABLE team_project ADD CONSTRAINT FK_D0CAA1D9296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_project ADD CONSTRAINT FK_D0CAA1D9166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE timelog ADD CONSTRAINT FK_B79470CEEA1EBC33 FOREIGN KEY (todo_id) REFERENCES todo (id)');
        $this->addSql('ALTER TABLE timelog ADD CONSTRAINT FK_B79470CEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE todo ADD CONSTRAINT FK_5A0EB6A0166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE user_team ADD CONSTRAINT FK_BE61EAD6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_team ADD CONSTRAINT FK_BE61EAD6296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE19EB6921');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EEBC999F9F');
        $this->addSql('ALTER TABLE rate DROP FOREIGN KEY FK_DFEC3F39979B1AD6');
        $this->addSql('ALTER TABLE team_project DROP FOREIGN KEY FK_D0CAA1D9296CD8AE');
        $this->addSql('ALTER TABLE team_project DROP FOREIGN KEY FK_D0CAA1D9166D1F9C');
        $this->addSql('ALTER TABLE timelog DROP FOREIGN KEY FK_B79470CEEA1EBC33');
        $this->addSql('ALTER TABLE timelog DROP FOREIGN KEY FK_B79470CEA76ED395');
        $this->addSql('ALTER TABLE todo DROP FOREIGN KEY FK_5A0EB6A0166D1F9C');
        $this->addSql('ALTER TABLE user_team DROP FOREIGN KEY FK_BE61EAD6A76ED395');
        $this->addSql('ALTER TABLE user_team DROP FOREIGN KEY FK_BE61EAD6296CD8AE');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE rate');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE team_project');
        $this->addSql('DROP TABLE timelog');
        $this->addSql('DROP TABLE todo');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_team');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
