<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250825125332 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE analysis_result (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', target_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', source VARCHAR(100) NOT NULL, data JSON NOT NULL, status VARCHAR(50) NOT NULL, analyzed_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', error_message LONGTEXT DEFAULT NULL, INDEX IDX_A8566F98158E0B66 (target_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE investigation (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', created_by_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', workspace_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, priority VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_C3A58AA6B03A8386 (created_by_id), INDEX IDX_C3A58AA682D40A1F (workspace_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE target (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', investigation_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', type VARCHAR(50) NOT NULL, value VARCHAR(500) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', last_analyzed DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_466F2FFCDC64C387 (investigation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE analysis_result ADD CONSTRAINT FK_A8566F98158E0B66 FOREIGN KEY (target_id) REFERENCES target (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE investigation ADD CONSTRAINT FK_C3A58AA6B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE investigation ADD CONSTRAINT FK_C3A58AA682D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE target ADD CONSTRAINT FK_466F2FFCDC64C387 FOREIGN KEY (investigation_id) REFERENCES investigation (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE analysis_result DROP FOREIGN KEY FK_A8566F98158E0B66
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE investigation DROP FOREIGN KEY FK_C3A58AA6B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE investigation DROP FOREIGN KEY FK_C3A58AA682D40A1F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE target DROP FOREIGN KEY FK_466F2FFCDC64C387
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE analysis_result
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE investigation
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE target
        SQL);
    }
}
