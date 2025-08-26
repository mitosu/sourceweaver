<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250825131102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE activity_log (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', investigation_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', action VARCHAR(100) NOT NULL, entity_type VARCHAR(100) DEFAULT NULL, entity_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', description LONGTEXT NOT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_FD06F647DC64C387 (investigation_id), INDEX IDX_FD06F647A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F647DC64C387 FOREIGN KEY (investigation_id) REFERENCES investigation (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F647A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F647DC64C387
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F647A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE activity_log
        SQL);
    }
}
