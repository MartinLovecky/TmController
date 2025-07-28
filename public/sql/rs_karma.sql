CREATE TABLE IF NOT EXISTS `rs_karma` (
    `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `ChallengeId` VARCHAR(255) NOT NULL DEFAULT '',
    `playerID` VARCHAR(255) NOT NULL DEFAULT '',
    `Score` TINYINT NOT NULL DEFAULT 0,
    `Plus` TINYINT NOT NULL DEFAULT 0,
    `PlusPlus` TINYINT NOT NULL DEFAULT 0,
    `PlusPlusPlus` TINYINT NOT NULL DEFAULT 0,
    `Minus` TINYINT NOT NULL DEFAULT 0,
    `MinusMinus` TINYINT NOT NULL DEFAULT 0,
    `MinusMinusMinus` TINYINT NOT NULL DEFAULT 0,
    UNIQUE KEY `Player_Challenge` (`playerID`, `ChallengeId`),
    CHECK (
        (`Plus` + `PlusPlus` + `PlusPlusPlus` + `Minus` + `MinusMinus` + `MinusMinusMinus`) <= 1
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
