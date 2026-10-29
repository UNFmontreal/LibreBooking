<?php

require_once(dirname(__FILE__) . '/PreReservationChainConfigKeys.php');

/**
 * Runs several pre-reservation plugins, because LibreBooking loads only one plugin per type.
 *
 * Each plugin decorates the one before it, so the plugins run in the order of PLUGINS. A plugin skips its own checks
 * when an earlier one already denied the reservation, which ends the validation at the first denial. Every plugin keeps
 * its own configuration; this plugin only chooses which of them are active.
 */
class PreReservationChain implements IPreReservationFactory
{
    /**
     * The plugins that can be chained, in the order they run.
     */
    public const PLUGINS = ['OffHours', 'EnforceStudiesName', 'LimesurveyScreeningPre'];

    /**
     * @var IPreReservationFactory the outermost plugin of the chain, or the base factory when none is active
     */
    private $chain;

    /**
     * @param IPreReservationFactory $factoryToDecorate
     * @param (callable(string, IPreReservationFactory): IPreReservationFactory)|null $pluginBuilder creates a plugin
     *        decorating the given factory; defaults to loading it from the plugins directory
     */
    public function __construct(IPreReservationFactory $factoryToDecorate, ?callable $pluginBuilder = null)
    {
        Configuration::Instance()->Register(
            dirname(__FILE__) . '/PreReservationChain.config.php',
            __DIR__ . '/.env',
            PreReservationChainConfigKeys::CONFIG_ID,
            false,
            PreReservationChainConfigKeys::class
        );

        $configured = (string) Configuration::Instance()
            ->File(PreReservationChainConfigKeys::CONFIG_ID)
            ->GetKey(PreReservationChainConfigKeys::PLUGINS);
        $active = self::activePlugins($configured);
        $build = $pluginBuilder ?? self::loadPlugin(...);

        $chain = $factoryToDecorate;
        foreach (self::PLUGINS as $name) {
            if (in_array($name, $active, true)) {
                $chain = $build($name, $chain);
            }
        }
        $this->chain = $chain;
    }

    public function CreatePreAddService(UserSession $userSession)
    {
        return $this->chain->CreatePreAddService($userSession);
    }

    public function CreatePreUpdateService(UserSession $userSession)
    {
        return $this->chain->CreatePreUpdateService($userSession);
    }

    public function CreatePreDeleteService(UserSession $userSession)
    {
        return $this->chain->CreatePreDeleteService($userSession);
    }

    public function CreatePreApprovalService(UserSession $userSession)
    {
        return $this->chain->CreatePreApprovalService($userSession);
    }

    public function CreatePreCheckinService(UserSession $userSession)
    {
        return $this->chain->CreatePreCheckinService($userSession);
    }

    public function CreatePreCheckoutService(UserSession $userSession)
    {
        return $this->chain->CreatePreCheckoutService($userSession);
    }

    /**
     * @param string $configured comma separated plugin names, in any order and letter case
     * @return string[] the known plugin names that are listed; unknown names are logged and ignored
     */
    public static function activePlugins(string $configured): array
    {
        $known = [];
        foreach (self::PLUGINS as $name) {
            $known[strtolower($name)] = $name;
        }

        $active = [];
        foreach (explode(',', $configured) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            if (isset($known[strtolower($item)])) {
                $active[] = $known[strtolower($item)];
            } else {
                Log::Error('PreReservationChain ignores an unknown plugin. Plugin=%s', $item);
            }
        }

        return $active;
    }

    private static function loadPlugin(string $name, IPreReservationFactory $inner): IPreReservationFactory
    {
        Log::Debug('Loading chained plugin. Plugin=%s', $name);

        try {
            require_once(ROOT_DIR . "plugins/PreReservation/$name/$name.php");

            return new $name($inner);
        } catch (Throwable $ex) {
            // the plugin manager that loads this chain logs a failure without its reason
            Log::Error('PreReservationChain could not load a plugin. Plugin=%s, Error=%s', $name, $ex);
            throw $ex;
        }
    }
}
