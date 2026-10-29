<?php

require_once(dirname(__FILE__) . '/LimesurveyScreeningPreConfigKeys.php');

/**
 * Access to the LimesurveyScreeningPre configuration, shared by the pre-reservation plugin, the post-reservation
 * plugin and the cron jobs.
 */
class LimesurveyScreeningPreConfig
{
    private static bool $registered = false;

    /**
     * Registers the config file once per request (Configuration::Register throws on duplicates).
     */
    public static function Register(): void
    {
        if (self::$registered) {
            return;
        }

        Configuration::Instance()->Register(
            __DIR__ . '/LimesurveyScreeningPre.config.php',
            __DIR__ . '/.env',
            LimesurveyScreeningPreConfigKeys::CONFIG_ID,
            false,
            LimesurveyScreeningPreConfigKeys::class
        );
        self::$registered = true;
    }

    /**
     * The registered config file.
     */
    public static function File(): ConfigurationFile
    {
        self::Register();

        return Configuration::Instance()->File(LimesurveyScreeningPreConfigKeys::CONFIG_ID);
    }

    /**
     * One setting, converted to its declared type.
     *
     * @param array<string, mixed> $definition One of the LimesurveyScreeningPreConfigKeys constants
     */
    public static function Get(array $definition): mixed
    {
        return self::File()->GetKey($definition);
    }
}
