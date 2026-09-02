<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema of the rewritten application (MySQL 8).
 *
 *  - user           accounts (roles as JSON)
 *  - work_schedule  one row per user: weekly hours, lunch breaks, short-break days
 *  - work_day       one row per user and calendar day: the four clocking times or a day off
 */
final class Version20260902000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: user, work_schedule, work_day';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform, 'Migration can only be executed safely on MySQL / MariaDB.');

        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_USER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE work_schedule (id INT AUTO_INCREMENT NOT NULL, weekly_minutes INT NOT NULL, lunch_break_minutes INT NOT NULL, short_lunch_break_minutes INT NOT NULL, short_lunch_break_days JSON NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_8F8D9BA7A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE work_day (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, morning_in TIME DEFAULT NULL, lunch_out TIME DEFAULT NULL, afternoon_in TIME DEFAULT NULL, evening_out TIME DEFAULT NULL, day_off TINYINT DEFAULT 0 NOT NULL, user_id INT NOT NULL, INDEX IDX_9FCE7E0CA76ED395 (user_id), UNIQUE INDEX UNIQ_WORK_DAY_USER_DATE (user_id, date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE work_schedule ADD CONSTRAINT FK_8F8D9BA7A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE work_day ADD CONSTRAINT FK_9FCE7E0CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE work_day DROP FOREIGN KEY FK_9FCE7E0CA76ED395');
        $this->addSql('ALTER TABLE work_schedule DROP FOREIGN KEY FK_8F8D9BA7A76ED395');
        $this->addSql('DROP TABLE work_day');
        $this->addSql('DROP TABLE work_schedule');
        $this->addSql('DROP TABLE `user`');
    }
}
