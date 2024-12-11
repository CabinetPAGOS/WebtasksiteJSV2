<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241211134649 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client CHANGE pilote pilote_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455F510AAE9 FOREIGN KEY (pilote_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_C7440455F510AAE9 ON client (pilote_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455F510AAE9');
        $this->addSql('DROP INDEX IDX_C7440455F510AAE9 ON client');
        $this->addSql('ALTER TABLE client CHANGE pilote_id pilote VARCHAR(255) DEFAULT NULL');
    }
}
