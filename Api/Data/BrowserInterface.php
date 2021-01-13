<?php
namespace Tony\NotifyOnNewBrowserLogin\Api\Data;

interface BrowserInterface
{
    public function setUserId(int $id);
    public function getUserId() : int;
    public function setBrowser(string $browser);
    public function getBrowser() : string;
    public function setPlatform(string $platform_name);
    public function getPlatform() : string;
    public function setIp(string $ip);
    public function getIp() : string;
}
