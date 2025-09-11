CREATE TABLE IF NOT EXISTS `rs_karma` (
    `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `ChallengeId` VARCHAR(255) NOT NULL,
    `playerID` VARCHAR(255) NOT NULL,
    `Vote` ENUM(
        'Plus',
        'PlusPlus',
        'PlusPlusPlus',
        'Minus',
        'MinusMinus',
        'MinusMinusMinus'
    ) NOT NULL DEFAULT 'Plus',
    `Score` TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY `Player_Challenge` (`playerID`, `ChallengeId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
