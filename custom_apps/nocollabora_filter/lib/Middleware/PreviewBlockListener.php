<?php

namespace OCA\NoCollaboraFilter\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Preview\BeforePreviewGeneratedEvent;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TextPlainResponse;

class PreviewBlockListener implements IEventListener {

    public function handle(Event $event): void {
        if (!($event instanceof BeforePreviewGeneratedEvent)) {
            return;
        }

        $logger = \OC::$server->getLogger();
        $file = $event->getFile();
        $fileName = $file->getName();
        $fileSize = $file->getSize();

        $logger->info("[NoCollaboraFilter] Preview attempt: {$fileName} ({$fileSize} bytes)", ['app' => 'nocollabora_filter']);

        // Only block large Excel files
        if (preg_match('/\.xlsx$/i', $fileName) && $fileSize > 5 * 1024 * 1024) {
            $logger->warning("[NoCollaboraFilter] Blocked large Excel preview: {$fileName} ({$fileSize})", ['app' => 'nocollabora_filter']);
            $event->cancel();
        }
    }
}
