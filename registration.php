<?php
use Magento\Framework\Component\ComponentRegistrar;

$registrar = new ComponentRegistrar();

if ($registrar->getPath(ComponentRegistrar::MODULE, 'Tony_NotifyOnNewBrowserLogin') === null) {
    ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Tony_NotifyOnNewBrowserLogin', __DIR__);
}
