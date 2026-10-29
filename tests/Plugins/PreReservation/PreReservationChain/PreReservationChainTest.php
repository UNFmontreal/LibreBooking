<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Application/Reservation/Validation/namespace.php');
require_once(ROOT_DIR . 'plugins/PreReservation/PreReservationChain/PreReservationChain.php');

class PreReservationChainTest extends TestBase
{
    public function testAllPluginsAreChainedInFixedOrderByDefault()
    {
        $built = [];

        $this->chain('', $built);

        $this->assertSame(['OffHours', 'EnforceStudiesName', 'LimesurveyScreeningPre'], $built);
    }

    public function testConfiguredOrderAndLetterCaseDoNotChangeTheRunOrder()
    {
        $built = [];

        $this->chain(' limesurveyscreeningpre, OFFHOURS ', $built);

        $this->assertSame(['OffHours', 'LimesurveyScreeningPre'], $built);
    }

    public function testOnlyTheListedPluginsAreActive()
    {
        $built = [];

        $this->chain('EnforceStudiesName', $built);

        $this->assertSame(['EnforceStudiesName'], $built);
    }

    public function testUnknownPluginsAreIgnored()
    {
        $built = [];

        $this->chain('OffHours,../Evil,Nope', $built);

        $this->assertSame(['OffHours'], $built);
    }

    public function testEachPluginDecoratesThePluginBeforeIt()
    {
        $base = $this->createMock(PreReservationFactory::class);
        $built = [];

        $chain = $this->chain('', $built, $base);

        $this->assertSame('LimesurveyScreeningPre', $chain->CreatePreAddService($this->fakeUser)->plugin);
        $this->assertSame(['OffHours', 'EnforceStudiesName'], $chain->CreatePreAddService($this->fakeUser)->innerPlugins);
    }

    public function testEveryServiceComesFromTheOutermostPlugin()
    {
        $built = [];
        $chain = $this->chain('', $built);

        foreach (['Add', 'Update', 'Delete', 'Approval', 'Checkin', 'Checkout'] as $action) {
            $service = $chain->{"CreatePre{$action}Service"}($this->fakeUser);
            $this->assertSame('LimesurveyScreeningPre', $service->plugin, $action);
        }
    }

    public function testWithoutActivePluginsTheBaseFactoryIsUsed()
    {
        $service = $this->createMock(IReservationValidationService::class);
        $base = $this->createMock(PreReservationFactory::class);
        $base->method('CreatePreAddService')->willReturn($service);
        $built = [];

        $chain = $this->chain('none', $built, $base);

        $this->assertSame($service, $chain->CreatePreAddService($this->fakeUser));
        $this->assertSame([], $built);
    }

    /**
     * @param string[] $built receives the names of the plugins in the order they are created
     */
    private function chain(string $configured, array &$built, ?PreReservationFactory $base = null): PreReservationChain
    {
        $file = new FakeConfigFile();
        $file->SetKey(PreReservationChainConfigKeys::PLUGINS, $configured === '' ? PreReservationChainConfigKeys::PLUGINS['default'] : $configured);
        $this->fakeConfig->SetFile(PreReservationChainConfigKeys::CONFIG_ID, $file);

        $builder = function (string $name, IPreReservationFactory $inner) use (&$built): IPreReservationFactory {
            $built[] = $name;

            return new class ($name, $inner) implements IPreReservationFactory {
                public function __construct(private string $name, private IPreReservationFactory $inner)
                {
                }

                public function CreatePreAddService(UserSession $userSession)
                {
                    return $this->service($this->inner->CreatePreAddService($userSession));
                }

                public function CreatePreUpdateService(UserSession $userSession)
                {
                    return $this->service($this->inner->CreatePreUpdateService($userSession));
                }

                public function CreatePreDeleteService(UserSession $userSession)
                {
                    return $this->service($this->inner->CreatePreDeleteService($userSession));
                }

                public function CreatePreApprovalService(UserSession $userSession)
                {
                    return $this->service($this->inner->CreatePreApprovalService($userSession));
                }

                public function CreatePreCheckinService(UserSession $userSession)
                {
                    return $this->service($this->inner->CreatePreCheckinService($userSession));
                }

                public function CreatePreCheckoutService(UserSession $userSession)
                {
                    return $this->service($this->inner->CreatePreCheckoutService($userSession));
                }

                private function service($innerService): object
                {
                    $innerPlugins = isset($innerService->plugin) ? [...$innerService->innerPlugins, $innerService->plugin] : [];

                    return (object) ['plugin' => $this->name, 'innerPlugins' => $innerPlugins];
                }
            };
        };

        return new PreReservationChain($base ?? $this->createMock(PreReservationFactory::class), $builder);
    }
}
