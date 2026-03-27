<?php

declare(strict_types=1);

namespace OCA\StirlingMerge\Controller;

use OCA\StirlingMerge\Service\MergeService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

class MergeController extends OCSController {

    public function __construct(
        string $appName,
        IRequest $request,
        private MergeService $mergeService,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function merge(): DataResponse {
        $fileIds    = $this->request->getParam('fileIds', []);
        $outputName = trim((string) $this->request->getParam('outputName', 'merged.pdf'));

        if (count($fileIds) < 2) {
            return new DataResponse(['error' => 'At least 2 files are required.'], 400);
        }

        if (!str_ends_with(strtolower($outputName), '.pdf')) {
            $outputName .= '.pdf';
        }

        $userId = $this->userSession->getUser()?->getUID();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated.'], 401);
        }

        try {
            $result = $this->mergeService->merge($fileIds, $outputName, $userId);
            return new DataResponse($result);
        } catch (\RuntimeException $e) {
            return new DataResponse(['error' => $e->getMessage()], 500);
        }
    }
}
