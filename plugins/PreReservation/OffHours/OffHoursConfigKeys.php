<?php

require_once(ROOT_DIR . 'lib/Config/PluginConfigKeys.php');

class OffHoursConfigKeys extends PluginConfigKeys
{
    public const CONFIG_ID = 'OffHours';

    public const REGULAR_HOURS = [
        'key' => 'regular.hours',
        'type' => 'string',
        'default' => 'Mon-Fri:08:00-12:00,Mon-Fri:13:00-17:00',
        'label' => 'Regular Hours',
        'description' => 'Regular hours as weekdays:start-end entries separated by semicolons, for example Mon-Fri:08:00-18:00;Sat:09:00-12:00. Leave empty to disable the restriction',
        'section' => 'offhours',
    ];

    public const ALLOWED_GROUPS = [
        'key' => 'allowed.groups',
        'type' => 'string',
        'default' => '',
        'label' => 'Groups Allowed Outside Regular Hours',
        'description' => 'semi-column separated group names whose members may book outside regular hours',
        'section' => 'offhours',
    ];

    public const MESSAGE = [
        'key' => 'message',
        'type' => 'string',
        'default' => 'Only members of authorized groups can book outside of regular hours.',
        'label' => 'Outside Regular Hours Message',
        'description' => 'Message shown when a user outside the allowed groups books outside regular hours',
        'section' => 'offhours',
    ];
}
