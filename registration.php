<?php
use Magento\Framework\Component\ComponentRegistrar;

$registrar = new ComponentRegistrar();

if ($registrar->getPath(ComponentRegistrar::MODULE, 'Tonyapps_NotifyOnNewBrowserLogin') === null) {
    ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Tonyapps_NotifyOnNewBrowserLogin', __DIR__);
}
