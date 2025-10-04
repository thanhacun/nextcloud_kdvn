<?php

namespace OCA\NoCollaboraFilter\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCA\NoCollaboraFilter\Listener\PreviewBlockListener;
use OCP\Preview\BeforePreviewGeneratedEvent;

class Application extends App implements IBootstrap {
    public const APP_ID = 'nocollabora_filter';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(BeforePreviewGeneratedEvent::class, PreviewBlockListener::class);
    }

    public function boot(IBootContext $context): void {
        // Nothing to do here
    }
}
