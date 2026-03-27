<?php

declare(strict_types=1);

namespace OCA\StirlingMerge\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

class SettingsController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private IConfig $config,
    ) {
        parent::__construct($appName, $request);
    }

    public function save(): JSONResponse {
        $url    = trim((string) $this->request->getParam('stirling_url', ''));
        $apiKey = (string) $this->request->getParam('stirling_api_key', '');

        // Basic URL validation
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            return new JSONResponse(['status' => 'error', 'error' => 'Invalid URL.'], 400);
        }

        $this->config->setAppValue('stirlingmerge', 'stirling_url',     rtrim($url, '/'));
        $this->config->setAppValue('stirlingmerge', 'stirling_api_key', $apiKey);

        return new JSONResponse(['status' => 'ok']);
    }

    #[NoCSRFRequired]
    public function test(): JSONResponse {
        $url    = rtrim($this->config->getAppValue('stirlingmerge', 'stirling_url', ''), '/');
        $apiKey = $this->config->getAppValue('stirlingmerge', 'stirling_api_key', '');

        if ($url === '') {
            return new JSONResponse(['status' => 'error', 'error' => 'No URL configured.'], 400);
        }

        $headers = ['Accept: application/json'];
        if ($apiKey !== '') {
            $headers[] = "X-API-KEY: {$apiKey}";
        }

        // Try /api/v1/info first (returns version), fall back to /health (Spring Boot actuator)
        foreach (["{$url}/api/v1/info", "{$url}/health"] as $endpoint) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
            ]);
            $body     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err      = curl_error($ch);
            curl_close($ch);

            if ($err !== '') {
                return new JSONResponse(['status' => 'error', 'error' => $err]);
            }
            if ($httpCode === 401 || $httpCode === 403) {
                return new JSONResponse(['status' => 'error', 'error' => "HTTP {$httpCode} — check your API key."]);
            }
            if ($httpCode === 200) {
                $data = json_decode((string) $body, true);
                return new JSONResponse([
                    'status'  => 'ok',
                    'version' => $data['version'] ?? $data['app'] ?? 'Stirling PDF',
                ]);
            }
        }

        return new JSONResponse(['status' => 'error', 'error' => "HTTP {$httpCode} — Stirling PDF unreachable."]);
    }
}
