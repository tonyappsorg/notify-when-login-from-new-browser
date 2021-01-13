<?php
namespace Tony\NotifyOnNewBrowserLogin\Api\Data;

interface BrowserInterface
{
    public function setUserId(int $id);
    public function getUserId() : int;
    public function setBrowser($id);
    public function getBrowser($id) : string;
    public function setIp($id);
    public function getIp($id) : string;
}
