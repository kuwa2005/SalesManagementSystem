<?php
require_once __DIR__ . '/../../app/Config/app.php';
require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Helpers/Session.php';
require_once __DIR__ . '/../../app/Helpers/SuperAuth.php';

Session::start();
SuperAuth::logout();
header('Location: /SalesManagementSystem/system/admin/login.php');
exit;
