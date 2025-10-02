<?php
namespace OCA\NoCollaboraFilter\AppInfo;

use OCP\AppFramework\App;
use OCA\NoCollaboraFilter\Middleware\CollaboraBlocker;

class Application extends App {
    public function __construct(array $urlParams = []) {
        parent::__construct('nocollabora_filter', $urlParams);
        $container = $this->getContainer();

        $container->registerService('CollaboraBlocker', function($c) {
            return new CollaboraBlocker();
        });

        $container->getServer()->registerMiddleware($container->query('CollaboraBlocker'));
    }
}
