<?php

declare(strict_types=1);

namespace OCA\StirlingMerge\Settings;

use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\AppFramework\Http\TemplateResponse;

class AdminSettings implements ISettings {

    public function __construct(
        private IConfig $config,
        private IL10N $l,
    ) {}

    public function getForm(): TemplateResponse {
        \OCP\Util::addScript('stirlingmerge', 'stirlingmerge-admin');
        return new TemplateResponse('stirlingmerge', 'settings/admin', [
            'stirling_url'     => $this->config->getAppValue('stirlingmerge', 'stirling_url', ''),
            'stirling_api_key' => $this->config->getAppValue('stirlingmerge', 'stirling_api_key', ''),
        ], 'blank');
    }

    public function getSection(): string {
        return 'additional';
    }

    public function getPriority(): int {
        return 50;
    }
}
