<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

/**
 * The texts the MRI plugins send and show: emails, link labels, error messages, and the labels of the settings.
 *
 * They are not configurable. The texts come from the language files in plugins/MRI/lang, in the language of the user,
 * and fall back to English when a language has no file or no entry.
 */
final class MriMessages
{
    public const REGEX_FILE_ERROR = 'message.regex_file_error';
    public const SCREENING_LINK_PRE_TEXT = 'text.screening_link_pre';
    public const SCREENING_LINK_TEXT = 'text.screening_link';
    public const COMPLETED_SURVEY_TEXT = 'text.completed_survey';
    public const NOT_COMPLETED_SURVEY_TEXT = 'text.not_completed_survey';
    public const NO_SCREENING_LINK_TEXT = 'text.no_screening_link';
    public const REQUISITION_LINK_TEXT = 'text.requisition_link';
    public const ADMIN_LINK_TEXT = 'text.admin_link';
    public const LOGBOOK_LINK_TEXT = 'text.logbook_link';
    public const TEXT_SCREENING_NOT_COMPLETED = 'text.screening_not_completed';
    public const TEXT_REQUISITION_NOT_COMPLETED = 'text.requisition_not_completed';
    public const TEXT_LOGBOOK_NOT_COMPLETED = 'text.logbook_not_completed';
    public const EMAIL_SUBJECT = 'email.subject';
    public const EMAIL_BODY = 'email.body';
    public const EMAIL_SCREENING_MISSING_SUBJECT = 'email.screening_missing_subject';
    public const EMAIL_SCREENING_MISSING_BODY = 'email.screening_missing_body';
    public const EMAIL_DELETE_SUBJECT = 'email.delete_subject';
    public const EMAIL_DELETE_BODY = 'email.delete_body';
    public const LOGBOOK_EMAIL_SUBJECT = 'email.logbook_subject';
    public const LOGBOOK_EMAIL_BODY = 'email.logbook_body';
    public const BIRTHDAY_NOT_MATCHING_SUBJECT = 'email.birthday_not_matching_subject';
    public const BIRTHDAY_NOT_MATCHING_BODY = 'email.birthday_not_matching_body';
    public const SESSION_NAME_ERROR = 'message.session_name_error';
    public const SESSION_EXISTS_ERROR = 'message.session_exists_error';
    public const PHANTOM_MULTI_ERROR = 'message.phantom_multi_error';
    public const NOT_ALLOWED_RECURRING = 'message.not_allowed_recurring';
    public const NOT_ALLOWED_OFF_HOURS = 'message.not_allowed_off_hours';
    public const CHANGE_RESOURCE = 'message.change_resource';
    public const DUPLICATED_TOKEN = 'message.duplicated_token';
    public const ETHIC_NUMBER_MISMATCH = 'message.ethic_number_mismatch';
    public const NOT_ALLOWED_UPDATE = 'message.not_allowed_update';
    public const NOT_ALLOWED_UPDATE_MULTIX_NO_SCREENING = 'message.not_allowed_update_multix_no_screening';
    public const SCREENING_DOES_NOT_EXIST = 'message.screening_does_not_exist';
    public const SCREENING_DOES_NOT_EXIST_FOR_MULTI = 'message.screening_does_not_exist_for_multi';
    public const MULTIX_UPDATE_SESSION_NO_SCREENING = 'message.multix_update_session_no_screening';
    public const NOT_MULTI_NAME_CHANGE_SCREENING_COMPLETE = 'message.not_multi_name_change_screening_complete';
    public const NOT_MULTI_NAME_CHANGE_PARTICIPANT_EXISTS = 'message.not_multi_name_change_participant_exists';
    public const NOT_ALLOW_MODIFICATION_IS_PHANTOM = 'message.not_allow_modification_is_phantom';
    public const NOT_ALLOWED_DELETE_MULTI1 = 'message.not_allowed_delete_multi1';
    public const NOT_ALLOWED_DELETE_MULTI1_ADMIN = 'message.not_allowed_delete_multi1_admin';

    public const SCREENING_SESSION_NAME_REQUIRED = 'message.screening_session_name_required';
    public const SCREENING_PHANTOM_MULTI = 'message.screening_phantom_multi';
    public const SCREENING_RECURRING = 'message.screening_recurring';
    public const SCREENING_DUPLICATE_PARTICIPANT = 'message.screening_duplicate_participant';
    public const SCREENING_RESOURCE_CHANGE = 'message.screening_resource_change';
    public const SCREENING_UNAVAILABLE = 'message.screening_unavailable';

    /**
     * @var array<string, string>|null loaded on first use
     */
    private static ?array $texts = null;

    public static function text(string $key): string
    {
        return self::find($key) ?? throw new \LogicException("No MRI text for key $key");
    }

    /**
     * The text for a key, or null when there is none.
     */
    public static function find(string $key): ?string
    {
        self::$texts ??= self::load();

        return self::$texts[$key] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private static function load(): array
    {
        $english = require __DIR__ . '/../../lang/en_us.php';
        $language = basename((string) \Resources::GetInstance()->CurrentLanguage);
        $file = __DIR__ . '/../../lang/' . $language . '.php';

        return is_file($file) ? array_merge($english, require $file) : $english;
    }
}
