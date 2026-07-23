<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Auth.php';

Session::start();
Auth::logout();

header('Location: /login.php');
exit;
