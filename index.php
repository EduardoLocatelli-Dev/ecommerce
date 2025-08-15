<?php 

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once("vendor/autoload.php");

use \Slim\Slim;

$app = new \Slim\Slim();

$app->config('debug', true);

require_once("site.php");
require_once("functions.php");
require_once("admin.php");
require_once("admin-users.php");
require_once("admin-categories.php");
require_once("admin-products.php");

$app->run();

 ?>