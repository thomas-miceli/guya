<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200215123002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE user_collaborations (git_repository_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_D2365BC3DCA00B70 (git_repository_id), INDEX IDX_D2365BC3A76ED395 (user_id), PRIMARY KEY(git_repository_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_collaborations ADD CONSTRAINT FK_D2365BC3DCA00B70 FOREIGN KEY (git_repository_id) REFERENCES git_repository (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_collaborations ADD CONSTRAINT FK_D2365BC3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE user_collaborations');
    }
}
