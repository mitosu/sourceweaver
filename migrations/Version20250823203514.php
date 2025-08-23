<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250823203514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE api_configuration DROP FOREIGN KEY FK_D8F105B82D40A1F
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_D8F105B82D40A1F ON api_configuration
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE api_configuration DROP workspace_id
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE api_configuration ADD workspace_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE api_configuration ADD CONSTRAINT FK_D8F105B82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D8F105B82D40A1F ON api_configuration (workspace_id)
        SQL);
    }
}
