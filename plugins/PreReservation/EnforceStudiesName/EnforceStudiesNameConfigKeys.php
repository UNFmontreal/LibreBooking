<?php

require_once(ROOT_DIR . 'lib/Config/PluginConfigKeys.php');

class EnforceStudiesNameConfigKeys extends PluginConfigKeys
{
    public const CONFIG_ID = 'EnforceStudiesName';

    public const STUDIES_URL = [
        'key' => 'studies.url',
        'type' => 'string',
        'default' => 'https://raw.githubusercontent.com/UNFmontreal/bids_configs/refs/heads/master/studies.json',
        'label' => 'Studies JSON URL',
        'description' => 'URL of a JSON object whose entries contain a "regex" string. Leave empty to disable the restriction',
        'section' => 'enforcestudiesname',
    ];

    public const CUSTOM_ATTRIBUTE_IDS = [
        'key' => 'custom.attribute.ids',
        'type' => 'string',
        'default' => '',
        'label' => 'Custom Attribute IDs',
        'description' => 'Comma separated custom attribute IDs whose values must match one of the studies regexes',
        'section' => 'enforcestudiesname',
    ];

    public const CACHE_TTL_SECONDS = [
        'key' => 'cache.ttl.seconds',
        'type' => 'integer',
        'default' => 86400,
        'label' => 'Studies Cache Duration',
        'description' => 'Seconds the downloaded studies file is cached. Use 0 to download it for every validation',
        'section' => 'enforcestudiesname',
    ];

    public const MESSAGE = [
        'key' => 'message',
        'type' => 'string',
        'default' => 'The study name does not match any valid study name pattern.',
        'label' => 'Invalid Study Name Message',
        'description' => 'Message shown when a custom attribute value does not match any studies regex',
        'section' => 'enforcestudiesname',
    ];

    public const UNAVAILABLE_MESSAGE = [
        'key' => 'unavailable.message',
        'type' => 'string',
        'default' => 'The study name cannot be verified right now. Please try again later.',
        'label' => 'Studies Unavailable Message',
        'description' => 'Message shown when the studies file cannot be downloaded and no cached copy exists',
        'section' => 'enforcestudiesname',
    ];
}
