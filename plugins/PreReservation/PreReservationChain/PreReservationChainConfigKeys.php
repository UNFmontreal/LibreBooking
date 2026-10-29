<?php

require_once(ROOT_DIR . 'lib/Config/PluginConfigKeys.php');

class PreReservationChainConfigKeys extends PluginConfigKeys
{
    public const CONFIG_ID = 'PreReservationChain';

    public const PLUGINS = [
        'key' => 'plugins',
        'type' => 'string',
        'default' => 'OffHours,EnforceStudiesName,LimesurveyScreeningPre',
        'label' => 'Chained Plugins',
        'description' => 'Comma separated pre-reservation plugins to activate. Available: OffHours, EnforceStudiesName, LimesurveyScreeningPre. They always run in that order, and a plugin only runs when the ones before it allowed the reservation',
        'section' => 'prereservationchain',
    ];
}
