<?php

declare(strict_types=1);

namespace OCA\StirlingMerge\Service;

use OCP\Files\IRootFolder;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class MergeService {

    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/tiff',
    ];

    public function __construct(
        private IRootFolder $rootFolder,
        private IConfig $config,
        private LoggerInterface $logger,
    ) {}

    /**
     * Merge an ordered list of File nodes via Stirling PDF and return raw PDF bytes.
     * Files must already be validated as images before calling this.
     *
     * @param \OCP\Files\File[] $files
     * @throws \RuntimeException
     */
    public function mergeToBytes(array $files): string {
        foreach ($files as $file) {
            if (!in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
                throw new \RuntimeException('Only images can be merged (JPG, PNG, WebP, TIFF).');
            }
        }
        return $this->callStirling($files);
    }

    /**
     * @param int[]  $fileIds    Ordered list of file IDs to merge
     * @param string $outputName Desired output filename (must end in .pdf)
     * @param string $userId     Owner's user ID
     * @return array{filePath: string, fileId: int}
     * @throws \RuntimeException On any failure (user-safe message)
     */
    public function merge(array $fileIds, string $outputName, string $userId): array {
        $userFolder   = $this->rootFolder->getUserFolder($userId);
        $files        = [];
        $outputFolder = null;

        foreach ($fileIds as $fileId) {
            $nodes = $userFolder->getById((int) $fileId);
            if (empty($nodes)) {
                throw new \RuntimeException('One or more files could not be read.');
            }
            $file = $nodes[0];
            if (!($file instanceof \OCP\Files\File)) {
                throw new \RuntimeException('One or more files could not be read.');
            }
            if (!in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
                throw new \RuntimeException('Only images can be merged (JPG, PNG, WebP, TIFF).');
            }
            $files[] = $file;
            if ($outputFolder === null) {
                $outputFolder = $file->getParent();
            }
        }

        $pdfBytes = $this->callStirling($files);

        $safeName = preg_replace('/[^\w.\-]/u', '_', $outputName);
        try {
            $node = $outputFolder->newFile($safeName, $pdfBytes);
        } catch (\OCP\Files\NotPermittedException) {
            throw new \RuntimeException('Could not save PDF. Check folder permissions.');
        }

        return [
            'filePath' => $userFolder->getRelativePath($node->getPath()),
            'fileId'   => $node->getId(),
        ];
    }

    /**
     * @param \OCP\Files\File[] $files
     * @throws \RuntimeException
     */
    private function callStirling(array $files): string {
        $stirlingUrl = rtrim(
            $this->config->getAppValue('stirlingmerge', 'stirling_url', ''),
            '/'
        );
        if ($stirlingUrl === '') {
            throw new \RuntimeException('PDF service is not configured. Ask your administrator to set stirling_url.');
        }

        $apiKey   = $this->config->getAppValue('stirlingmerge', 'stirling_api_key', '');
        $boundary = '----StirlingMergeBoundary' . bin2hex(random_bytes(8));
        $body     = '';

        foreach ($files as $file) {
            $mime    = $file->getMimeType();
            $name    = $file->getName();
            $content = $file->getContent();
            $body   .= "--{$boundary}\r\n";
            $body   .= "Content-Disposition: form-data; name=\"fileInput\"; filename=\"{$name}\"\r\n";
            $body   .= "Content-Type: {$mime}\r\n\r\n";
            $body   .= $content . "\r\n";
        }
        foreach (['fitOption' => 'maintainAspectRatio', 'colorType' => 'color', 'autoRotate' => 'true'] as $k => $v) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$k}\"\r\n\r\n";
            $body .= "{$v}\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        $headers = [
            "Content-Type: multipart/form-data; boundary={$boundary}",
            'Accept: application/pdf',
        ];
        if ($apiKey !== '') {
            $headers[] = "X-API-KEY: {$apiKey}";
        }

        $ch = curl_init("{$stirlingUrl}/api/v1/convert/img/pdf");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            $this->logger->error('Stirling PDF curl error: ' . $curlErr);
            if (str_contains($curlErr, 'timed out')) {
                throw new \RuntimeException('Request timed out. Try with fewer images.');
            }
            throw new \RuntimeException('PDF service is unavailable. Try again later.');
        }

        if ($httpCode !== 200) {
            $this->logger->error("Stirling PDF returned HTTP {$httpCode}: " . substr((string)$response, 0, 200));
            throw new \RuntimeException('PDF conversion failed. Check file formats.');
        }

        return (string) $response;
    }
}
