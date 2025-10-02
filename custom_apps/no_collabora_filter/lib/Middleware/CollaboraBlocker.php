<?php
namespace OCA\NoCollaboraFilter\Middleware;

use OCP\AppFramework\Middleware;
use OCP\IRequest;
use OCP\Files\IRootFolder;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IURLGenerator;

class CollaboraBlocker extends Middleware {
    private IRootFolder $rootFolder;
    private IRequest $request;
    private IURLGenerator $urlGenerator;

    public function __construct() {
        $this->rootFolder = \OC::$server->get(IRootFolder::class);
        $this->request = \OC::$server->get(IRequest::class);
        $this->urlGenerator = \OC::$server->get(IURLGenerator::class);
    }

    public function beforeController($controller, $methodName) {
        $path = $this->request->getPathInfo();

        // Only intercept Collabora file-open routes
        if (strpos($path, '/apps/richdocuments') !== false) {
            $fileId = $this->request->getParam('fileId') ?? $this->request->getParam('file_id');

            if ($fileId) {
                $user = \OC::$server->getUserSession()->getUser();
                $userFolder = $this->rootFolder->getUserFolder($user->getUID());
                $file = $userFolder->getById($fileId)[0] ?? null;

                if ($file) {
                    $size = $file->getSize();

                    // Example rule: block if > 5 MB
                    if ($size > 5 * 1024 * 1024) {
                        $url = $this->urlGenerator->linkToRouteAbsolute('files.view.showFile', ['fileid' => $fileId]);
                        return new RedirectResponse($url);
                    }

                    // Optional: block if tag 'no_collabora' exists
                    $tagMgr = \OC::$server->getTagManager();
                    $tags = $tagMgr->getTagsForObjects([$fileId])[$fileId] ?? [];
                    if (in_array('no_collabora', $tags)) {
                        $url = $this->urlGenerator->linkToRouteAbsolute('files.view.showFile', ['fileid' => $fileId]);
                        return new RedirectResponse($url);
                    }
                }
            }
        }
    }
}
