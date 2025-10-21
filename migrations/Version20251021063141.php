<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251021063141 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE campaign (id INT AUTO_INCREMENT NOT NULL, company_id INT NOT NULL, name VARCHAR(255) NOT NULL, template_name VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1F1512DD979B1AD6 (company_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE campaign_result (id INT AUTO_INCREMENT NOT NULL, campaign_id INT DEFAULT NULL, user_id INT DEFAULT NULL, is_sent TINYINT(1) NOT NULL, is_opened TINYINT(1) NOT NULL, is_clicked TINYINT(1) NOT NULL, is_compromised TINYINT(1) NOT NULL, INDEX IDX_CE29B8BEF639F774 (campaign_id), INDEX IDX_CE29B8BEA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE campaign ADD CONSTRAINT FK_1F1512DD979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)');
        $this->addSql('ALTER TABLE campaign_result ADD CONSTRAINT FK_CE29B8BEF639F774 FOREIGN KEY (campaign_id) REFERENCES campaign (id)');
        $this->addSql('ALTER TABLE campaign_result ADD CONSTRAINT FK_CE29B8BEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE campaign DROP FOREIGN KEY FK_1F1512DD979B1AD6');
        $this->addSql('ALTER TABLE campaign_result DROP FOREIGN KEY FK_CE29B8BEF639F774');
        $this->addSql('ALTER TABLE campaign_result DROP FOREIGN KEY FK_CE29B8BEA76ED395');
        $this->addSql('DROP TABLE campaign');
        $this->addSql('DROP TABLE campaign_result');
    }
}
