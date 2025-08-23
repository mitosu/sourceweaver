<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250823172934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE api_configuration (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', created_by_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', workspace_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_D8F105BB03A8386 (created_by_id), INDEX IDX_D8F105B82D40A1F (workspace_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE api_configuration_option (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', api_configuration_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', option_name VARCHAR(255) NOT NULL, option_value LONGTEXT NOT NULL, is_encrypted TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_406DA77B316D888C (api_configuration_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE api_configuration ADD CONSTRAINT FK_D8F105BB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE api_configuration ADD CONSTRAINT FK_D8F105B82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE api_configuration_option ADD CONSTRAINT FK_406DA77B316D888C FOREIGN KEY (api_configuration_id) REFERENCES api_configuration (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE api_configuration DROP FOREIGN KEY FK_D8F105BB03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE api_configuration DROP FOREIGN KEY FK_D8F105B82D40A1F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE api_configuration_option DROP FOREIGN KEY FK_406DA77B316D888C
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE api_configuration
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE api_configuration_option
        SQL);
    }
}
