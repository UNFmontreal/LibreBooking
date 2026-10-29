<?php

use LibreBooking\Mri\CustomAttributes;
use LibreBooking\Mri\ResourceNames;

/**
 * Named access to the MRI settings used by the post-reservation plugin.
 *
 * The settings are declared by LimesurveyScreeningPreConfigKeys and registered by
 * LimesurveyScreeningPreConfig::Register(). Reading them through this class keeps the notifiers free of raw config
 * lookups.
 */
final class MriPostResSettings
{
    private static ?CustomAttributes $attributes = null;

    /**
     * @return string[] names of the resources that trigger MRI processing
     */
    public static function monitoredResourceNames(): array
    {
        return ResourceNames::parse(self::text(LimesurveyScreeningPreConfigKeys::RESOURCE_NAMES));
    }

    public static function lsBaseUrl(): string
    {
        return self::text(LimesurveyScreeningPreConfigKeys::LS_BASE_URL);
    }

    public static function lsUser(): string
    {
        return self::text(LimesurveyScreeningPreConfigKeys::LS_USER);
    }

    public static function lsPassword(): string
    {
        return self::text(LimesurveyScreeningPreConfigKeys::LS_PASSWORD);
    }

    public static function screeningSurveyId(): int
    {
        return self::number(LimesurveyScreeningPreConfigKeys::SCREENING_SURVEY_ID);
    }

    public static function logbookSurveyId(): int
    {
        return self::number(LimesurveyScreeningPreConfigKeys::LOGBOOK_SURVEY_ID);
    }

    /**
     * Custom attribute holding the session name (the study participant and visit).
     */
    public static function sessionNameAttributeId(): int
    {
        return self::attributeId(LimesurveyScreeningPreConfigKeys::SESSION_NAME_ATTRIBUTE_LABEL);
    }

    /**
     * Custom attribute holding the screening link shown on the reservation.
     */
    public static function screeningLinkAttributeId(): int
    {
        return self::attributeId(LimesurveyScreeningPreConfigKeys::SCREENING_LINK_ATTRIBUTE_LABEL);
    }

    /**
     * Admin-only custom attribute holding the link to the logbook entry.
     */
    public static function logbookLinkAttributeId(): int
    {
        return self::attributeId(LimesurveyScreeningPreConfigKeys::LOGBOOK_LINK_ATTRIBUTE_LABEL);
    }

    /**
     * Custom attribute marking a phantom reservation, which has no screening participant.
     */
    public static function phantomAttributeId(): int
    {
        return self::attributeId(LimesurveyScreeningPreConfigKeys::PHANTOM_ATTRIBUTE_LABEL);
    }

    /**
     * Custom attribute holding the research associate of the reservation.
     */
    public static function raAttributeId(): int
    {
        return self::attributeId(LimesurveyScreeningPreConfigKeys::RESEARCH_ASSISTANT_ATTRIBUTE_LABEL);
    }

    /**
     * Custom attribute of the technical issue checkbox.
     */
    public static function unfTechnicalIssueAttributeId(): int
    {
        return self::attributeId(LimesurveyScreeningPreConfigKeys::UNF_TECHNICAL_ISSUE_ATTRIBUTE_LABEL);
    }

    /**
     * Custom attribute holding the display name of the MRI operator.
     */
    public static function operatorAttributeId(): int
    {
        return self::attributeId(LimesurveyScreeningPreConfigKeys::MRI_OPERATOR_ATTRIBUTE_LABEL);
    }

    /**
     * The LibreBooking user id of an MRI operator, 0 when the display name is empty or unknown.
     */
    public static function operatorUserId(string $displayName): int
    {
        $operators = json_decode(self::text(LimesurveyScreeningPreConfigKeys::MRI_OPERATORS), true);

        return is_array($operators) ? (int) ($operators[$displayName] ?? 0) : 0;
    }

    /**
     * Admin-only checkbox that marks a phantom reservation as validated.
     */
    public static function screeningValidAttributeId(): int
    {
        return self::attributeId(LimesurveyScreeningPreConfigKeys::SCREENING_VALID_ATTRIBUTE_LABEL);
    }

    public static function maxDelayDaysToCancel(): int
    {
        return self::number(LimesurveyScreeningPreConfigKeys::MAX_DELAY_DAYS_TO_CANCEL);
    }

    /**
     * The id of the reservation custom attribute whose label is the setting.
     */
    private static function attributeId(array $definition): int
    {
        self::$attributes ??= new CustomAttributes();

        return self::$attributes->idOf(self::text($definition));
    }

    public static function text(array $definition): string
    {
        return (string) LimesurveyScreeningPreConfig::Get($definition);
    }

    public static function number(array $definition): int
    {
        return (int) LimesurveyScreeningPreConfig::Get($definition);
    }
}
