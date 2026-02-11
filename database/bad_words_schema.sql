-- =========================================
-- Bad Words Filtering System - Database Schema
-- =========================================
-- Run this SQL on your database to create the tables.
-- The system detects offensive/prohibited words in any language,
-- even when letters are separated or disguised.
-- =========================================

CREATE TABLE IF NOT EXISTS `bad_words` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `word`       VARCHAR(255)    NOT NULL COMMENT 'The bad word or regex pattern',
    `severity`   ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    `is_regex`   TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 if word is a regex pattern',
    `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at` DATETIME        DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_word` (`word`),
    KEY `idx_severity` (`severity`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bad_word_translations` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bad_word_id`   BIGINT UNSIGNED NOT NULL,
    `language_code` VARCHAR(8)      NOT NULL COMMENT 'ISO language code: ar, en, fr, etc.',
    `word`          VARCHAR(255)    NOT NULL COMMENT 'Translated bad word in this language',
    `created_at`    DATETIME        DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_badword_lang` (`bad_word_id`, `language_code`),
    KEY `idx_language` (`language_code`),
    CONSTRAINT `fk_bwt_bad_word` FOREIGN KEY (`bad_word_id`)
        REFERENCES `bad_words` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Sample data (common offensive words)
-- =========================================

-- English examples
INSERT INTO `bad_words` (`word`, `severity`) VALUES
('fuck',     'high'),
('shit',     'high'),
('bitch',    'high'),
('asshole',  'high'),
('damn',     'medium'),
('bastard',  'high'),
('dick',     'medium'),
('crap',     'low'),
('idiot',    'low'),
('stupid',   'low'),
('moron',    'medium'),
('whore',    'high'),
('slut',     'high'),
('nigger',   'high'),
('faggot',   'high'),
('retard',   'high'),
('pussy',    'high'),
('cock',     'medium'),
('ass',      'medium'),
('hell',     'low'),
('dumb',     'low'),
('jerk',     'low'),
('loser',    'low'),
('sucker',   'medium'),
('piss',     'medium'),
('scam',     'medium'),
('fraud',    'high'),
('kill',     'high'),
('murder',   'high'),
('terrorist','high'),
('bomb',     'high'),
('hate',     'medium'),
('ugly',     'low'),
('fat',      'low'),
('racist',   'high'),
('sexist',   'high');

-- Arabic translations
INSERT INTO `bad_word_translations` (`bad_word_id`, `language_code`, `word`) VALUES
((SELECT id FROM bad_words WHERE word='fuck'),     'ar', 'طز'),
((SELECT id FROM bad_words WHERE word='shit'),     'ar', 'خرا'),
((SELECT id FROM bad_words WHERE word='bitch'),    'ar', 'عاهرة'),
((SELECT id FROM bad_words WHERE word='asshole'),  'ar', 'أحمق'),
((SELECT id FROM bad_words WHERE word='damn'),     'ar', 'اللعنة'),
((SELECT id FROM bad_words WHERE word='bastard'),  'ar', 'نغل'),
((SELECT id FROM bad_words WHERE word='idiot'),    'ar', 'غبي'),
((SELECT id FROM bad_words WHERE word='stupid'),   'ar', 'أبله'),
((SELECT id FROM bad_words WHERE word='moron'),    'ar', 'معتوه'),
((SELECT id FROM bad_words WHERE word='whore'),    'ar', 'قحبة'),
((SELECT id FROM bad_words WHERE word='slut'),     'ar', 'فاجرة'),
((SELECT id FROM bad_words WHERE word='retard'),   'ar', 'متخلف'),
((SELECT id FROM bad_words WHERE word='loser'),    'ar', 'خاسر'),
((SELECT id FROM bad_words WHERE word='scam'),     'ar', 'نصب'),
((SELECT id FROM bad_words WHERE word='fraud'),    'ar', 'احتيال'),
((SELECT id FROM bad_words WHERE word='kill'),     'ar', 'قتل'),
((SELECT id FROM bad_words WHERE word='murder'),   'ar', 'جريمة قتل'),
((SELECT id FROM bad_words WHERE word='terrorist'),'ar', 'إرهابي'),
((SELECT id FROM bad_words WHERE word='bomb'),     'ar', 'قنبلة'),
((SELECT id FROM bad_words WHERE word='hate'),     'ar', 'كراهية'),
((SELECT id FROM bad_words WHERE word='ugly'),     'ar', 'قبيح'),
((SELECT id FROM bad_words WHERE word='racist'),   'ar', 'عنصري'),
((SELECT id FROM bad_words WHERE word='sexist'),   'ar', 'متحيز جنسيا');

-- Additional standalone Arabic bad words
INSERT INTO `bad_words` (`word`, `severity`) VALUES
('كلب',     'medium'),
('حمار',    'medium'),
('ابن الكلب','high'),
('يلعن',    'medium'),
('منيوك',   'high'),
('شرموط',   'high'),
('زبال',    'medium'),
('تافه',    'low'),
('حقير',    'medium'),
('وسخ',     'medium'),
('قذر',     'medium'),
('خنزير',   'high'),
('ملعون',   'medium'),
('منافق',   'medium'),
('كذاب',    'low'),
('لص',      'medium'),
('خائن',    'high'),
('جبان',    'low');

-- Arabic words English translations
INSERT INTO `bad_word_translations` (`bad_word_id`, `language_code`, `word`) VALUES
((SELECT id FROM bad_words WHERE word='كلب'),      'en', 'dog (insult)'),
((SELECT id FROM bad_words WHERE word='حمار'),     'en', 'donkey (insult)'),
((SELECT id FROM bad_words WHERE word='ابن الكلب'),'en', 'son of a dog'),
((SELECT id FROM bad_words WHERE word='يلعن'),     'en', 'curse'),
((SELECT id FROM bad_words WHERE word='تافه'),     'en', 'worthless'),
((SELECT id FROM bad_words WHERE word='حقير'),     'en', 'despicable'),
((SELECT id FROM bad_words WHERE word='خنزير'),    'en', 'pig (insult)'),
((SELECT id FROM bad_words WHERE word='ملعون'),    'en', 'cursed'),
((SELECT id FROM bad_words WHERE word='كذاب'),     'en', 'liar'),
((SELECT id FROM bad_words WHERE word='لص'),       'en', 'thief'),
((SELECT id FROM bad_words WHERE word='خائن'),     'en', 'traitor'),
((SELECT id FROM bad_words WHERE word='جبان'),     'en', 'coward');
