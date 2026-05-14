<?php

use Phoenix\Migration\AbstractMigration;

class ChatMigrations extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("
CREATE TABLE `chat_messages`
(
    `id`           INT NOT NULL AUTO_INCREMENT,
    `sender_id`    INT UNSIGNED NOT NULL,
    `recipient_id` INT UNSIGNED NOT NULL,
    `is_read`      TINYINT(1) NOT NULL DEFAULT 0,
    `content`      LONGTEXT CHARACTER SET 'utf8' COLLATE 'utf8_general_ci' NOT NULL,
    `created_at`   DATETIME NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    INDEX          `user_id_idx` (`sender_id` ASC) VISIBLE,
    INDEX          `qw_idx` (`recipient_id` ASC) VISIBLE,
    CONSTRAINT `sender_id_FK`
        FOREIGN KEY (`sender_id`)
            REFERENCES `acl_user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    CONSTRAINT `recipient_ID_FK`
        FOREIGN KEY (`recipient_id`)
            REFERENCES `acl_user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
);
");
    }

    protected function down(): void
    {
        
    }
}
