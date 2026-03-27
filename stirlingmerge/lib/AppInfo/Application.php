<?php

declare(strict_types=1);

namespace OCA\StirlingMerge\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\StirlingMerge\Settings\AdminSettings;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Settings\IManager as ISettingsManager;

class Application extends App implements IBootstrap {

    public const APP_ID = 'stirlingmerge';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        // IRegistrationContext in NC32 does not have registerSettings().
        // Settings are registered via ISettingsManager in boot() instead.
    }

    public function boot(IBootContext $context): void {
        $context->injectFn([$this, 'registerScripts']);
        $context->injectFn([$this, 'registerAdminSettings']);
    }

    public function registerAdminSettings(ISettingsManager $settingsManager): void {
        $settingsManager->registerSetting('admin', AdminSettings::class);
    }

    public function registerScripts(IEventDispatcher $dispatcher): void {
        $dispatcher->addListener(LoadAdditionalScriptsEvent::class, function (): void {
            \OCP\Util::addScript(self::APP_ID, 'stirlingmerge-main');
            \OCP\Util::addStyle(self::APP_ID, 'stirlingmerge-main');
        });
        // Also inject into public share pages (Files_Sharing)
        $dispatcher->addListener(BeforeTemplateRenderedEvent::class, function (): void {
            \OCP\Util::addScript(self::APP_ID, 'stirlingmerge-main');
            \OCP\Util::addStyle(self::APP_ID, 'stirlingmerge-main');
        });
    }
}
