<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250527181718 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE calendar_tab (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE dashboard (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', workspace_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_5C94FFF882D40A1F (workspace_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE kanban_tab (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE main_table_tab (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE tab (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', dashboard_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', name VARCHAR(255) NOT NULL, position INT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', type VARCHAR(255) NOT NULL, INDEX IDX_73E3430CB9D04D2B (dashboard_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE workspace (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', owner_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_8D9400197E3C61F9 (owner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE workspace_membership (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', workspace_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', user_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', role VARCHAR(50) NOT NULL, joined_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_6F485B8A82D40A1F (workspace_id), INDEX IDX_6F485B8AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_tab ADD CONSTRAINT FK_8D8221BFBF396750 FOREIGN KEY (id) REFERENCES tab (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE dashboard ADD CONSTRAINT FK_5C94FFF882D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE kanban_tab ADD CONSTRAINT FK_A2BB7D6ABF396750 FOREIGN KEY (id) REFERENCES tab (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE main_table_tab ADD CONSTRAINT FK_5AB06ECABF396750 FOREIGN KEY (id) REFERENCES tab (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE tab ADD CONSTRAINT FK_73E3430CB9D04D2B FOREIGN KEY (dashboard_id) REFERENCES dashboard (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE workspace ADD CONSTRAINT FK_8D9400197E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE workspace_membership ADD CONSTRAINT FK_6F485B8A82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE workspace_membership ADD CONSTRAINT FK_6F485B8AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_tab DROP FOREIGN KEY FK_8D8221BFBF396750
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE dashboard DROP FOREIGN KEY FK_5C94FFF882D40A1F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE kanban_tab DROP FOREIGN KEY FK_A2BB7D6ABF396750
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE main_table_tab DROP FOREIGN KEY FK_5AB06ECABF396750
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE tab DROP FOREIGN KEY FK_73E3430CB9D04D2B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE workspace DROP FOREIGN KEY FK_8D9400197E3C61F9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE workspace_membership DROP FOREIGN KEY FK_6F485B8A82D40A1F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE workspace_membership DROP FOREIGN KEY FK_6F485B8AA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE calendar_tab
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE dashboard
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE kanban_tab
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE main_table_tab
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE tab
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE workspace
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE workspace_membership
        SQL);
    }
}
