<?php

declare(strict_types=1);

namespace OCA\StirlingMerge\Controller;

use OCA\StirlingMerge\Service\MergeService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IRequest;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;

class PublicMergeController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private IShareManager $shareManager,
        private MergeService $mergeService,
    ) {
        parent::__construct($appName, $request);
    }

    #[PublicPage]
    #[NoCSRFRequired]
    public function merge(): DataDownloadResponse|JSONResponse {
        $token      = (string) $this->request->getParam('shareToken', '');
        $paths      = (array)  $this->request->getParam('paths', []);
        $outputName = trim((string) $this->request->getParam('outputName', 'merged.pdf'));

        if ($token === '') {
            return new JSONResponse(['error' => 'Missing share token.'], 400);
        }
        if (count($paths) < 2) {
            return new JSONResponse(['error' => 'At least 2 files required.'], 400);
        }
        if (!str_ends_with(strtolower($outputName), '.pdf')) {
            $outputName .= '.pdf';
        }

        try {
            $share = $this->shareManager->getShareByToken($token);
        } catch (ShareNotFound) {
            return new JSONResponse(['error' => 'Invalid or expired share link.'], 404);
        }

        $sharedNode = $share->getNode();
        if (!($sharedNode instanceof Folder)) {
            return new JSONResponse(['error' => 'This share is not a folder.'], 400);
        }

        $files = [];
        foreach ($paths as $path) {
            $path = (string) $path;
            // Block path traversal attempts
            if (str_contains($path, '..') || str_contains($path, "\0")) {
                return new JSONResponse(['error' => 'Invalid file path.'], 400);
            }
            try {
                $node = $sharedNode->get(ltrim($path, '/'));
                if (!($node instanceof File)) {
                    return new JSONResponse(['error' => 'One or more selected items is not a file.'], 400);
                }
                $files[] = $node;
            } catch (\OCP\Files\NotFoundException) {
                return new JSONResponse(['error' => "File not found: {$path}"], 404);
            }
        }

        try {
            $pdfBytes = $this->mergeService->mergeToBytes($files);
        } catch (\RuntimeException $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }

        $safeName = preg_replace('/[^\w.\-]/u', '_', $outputName);
        return new DataDownloadResponse($pdfBytes, $safeName, 'application/pdf');
    }
}
