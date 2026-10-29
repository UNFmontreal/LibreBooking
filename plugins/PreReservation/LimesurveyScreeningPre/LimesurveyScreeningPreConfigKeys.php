<?php

require_once(ROOT_DIR . 'lib/Config/PluginConfigKeys.php');
require_once(dirname(__FILE__) . '/../../MRI/autoload.php');

/**
 * The settings of the MRI screening: the pre-reservation plugin, the post-reservation plugin and the cron jobs.
 *
 * The labels and descriptions shown in the admin configuration page are not declared here. They are the entries
 * "config.<key>" and "config.<key>.description" of the language files in plugins/MRI/lang.
 */
class LimesurveyScreeningPreConfigKeys extends PluginConfigKeys
{
    public const CONFIG_ID = 'LimesurveyScreeningPre';
    private const SECTION = 'limesurveyscreeningpre';

    public const LS_BASE_URL = ['key' => 'ls.base.url', 'type' => 'string', 'default' => '', 'section' => self::SECTION];

    public const LS_USER = ['key' => 'ls.user', 'type' => 'string', 'default' => '', 'section' => self::SECTION];

    public const LS_PASSWORD = ['key' => 'ls.password', 'type' => 'string', 'default' => '', 'section' => self::SECTION];

    public const SCREENING_SURVEY_ID = [
        'key' => 'ls.screening.survey.id',
        'type' => 'integer',
        'default' => 0,
        'section' => self::SECTION,
    ];

    public const LOGBOOK_SURVEY_ID = [
        'key' => 'ls.logbook.survey.id',
        'type' => 'integer',
        'default' => 0,
        'section' => self::SECTION,
    ];

    public const RESOURCE_NAMES = ['key' => 'resource.names', 'type' => 'string', 'default' => '', 'section' => self::SECTION];

    public const SESSION_NAME_ATTRIBUTE_LABEL = [
        'key' => 'attribute.session.name.label',
        'type' => 'string',
        'default' => '',
        'section' => self::SECTION,
    ];

    public const PHANTOM_ATTRIBUTE_LABEL = [
        'key' => 'attribute.phantom.label',
        'type' => 'string',
        'default' => '',
        'section' => self::SECTION,
    ];

    public const UNF_TECHNICAL_ISSUE_ATTRIBUTE_LABEL = [
        'key' => 'attribute.unf.technical.issue.label',
        'type' => 'string',
        'default' => '',
        'section' => self::SECTION,
    ];

    public const SCREENING_LINK_ATTRIBUTE_LABEL = [
        'key' => 'attribute.screening.link.label',
        'type' => 'string',
        'default' => '',
        'section' => self::SECTION,
    ];

    public const SCREENING_VALID_ATTRIBUTE_LABEL = [
        'key' => 'attribute.screening.valid.label',
        'type' => 'string',
        'default' => '',
        'section' => self::SECTION,
    ];

    public const MRI_OPERATOR_ATTRIBUTE_LABEL = [
        'key' => 'attribute.mri.operator.label',
        'type' => 'string',
        'default' => '',
        'section' => self::SECTION,
    ];

    public const RESEARCH_ASSISTANT_ATTRIBUTE_LABEL = [
        'key' => 'attribute.research.assistant.label',
        'type' => 'string',
        'default' => '',
        'section' => self::SECTION,
    ];

    public const LOGBOOK_LINK_ATTRIBUTE_LABEL = [
        'key' => 'attribute.logbook.link.label',
        'type' => 'string',
        'default' => '',
        'section' => self::SECTION,
    ];

    public const STUDIES_URL = [
        'key' => 'studies.url',
        'type' => 'string',
        'default' => 'https://raw.githubusercontent.com/UNFmontreal/bids_configs/refs/heads/master/studies.json',
        'section' => self::SECTION,
    ];

    public const STUDIES_CACHE_TTL_SECONDS = [
        'key' => 'studies.cache.ttl.seconds',
        'type' => 'integer',
        'default' => 1200,
        'section' => self::SECTION,
    ];

    public const MRI_OPERATORS = ['key' => 'mri.operators', 'type' => 'string', 'default' => '{}', 'section' => self::SECTION];

    public const CRON_LOOK_AHEAD_DAYS = [
        'key' => 'cron.look.ahead.days',
        'type' => 'integer',
        'default' => 0,
        'section' => self::SECTION,
    ];

    public const MAX_DELAY_DAYS_TO_CANCEL = [
        'key' => 'max.delay.days.to.cancel',
        'type' => 'integer',
        'default' => 0,
        'section' => self::SECTION,
    ];

    public const EMAIL_DATE_FORMAT = [
        'key' => 'email.date.format',
        'type' => 'string',
        'default' => '',
        'section' => self::SECTION,
    ];

    public const EMAIL_TIME_FORMAT = [
        'key' => 'email.time.format',
        'type' => 'string',
        'default' => '',
        'section' => self::SECTION,
    ];

    public const SUPPORT_EMAIL = ['key' => 'support.email', 'type' => 'string', 'default' => '', 'section' => self::SECTION];

    public const SUPPORT_EMAIL_NAME = [
        'key' => 'support.email.name',
        'type' => 'string',
        'default' => '',
        'section' => self::SECTION,
    ];

    public const LOGBOOK_OPERATOR_ADDRESS = [
        'key' => 'logbook.operator.address',
        'type' => 'string',
        'default' => '',
        'section' => self::SECTION,
    ];

    public const LINK_TO_LOGBOOK = ['key' => 'link.to.logbook', 'type' => 'string', 'default' => '', 'section' => self::SECTION];

    /**
     * The keys with the labels and descriptions of the language file.
     *
     * @return list<ConfigKey>
     */
    public static function all(): array
    {
        static $keys = null;

        return $keys ??= array_map(
            fn (ConfigKey $key): ConfigKey => new ConfigKey(
                key: $key->key,
                type: $key->type,
                default: $key->default,
                section: $key->section,
                label: \LibreBooking\Mri\MriMessages::find('config.' . $key->key),
                description: \LibreBooking\Mri\MriMessages::find('config.' . $key->key . '.description'),
                choices: $key->choices,
                configFileComment: $key->configFileComment,
                legacy: $key->legacy,
                isPrivate: $key->isPrivate,
                isHidden: $key->isHidden,
                allowCustom: $key->allowCustom,
                isProtected: $key->isProtected,
            ),
            parent::all()
        );
    }
}
