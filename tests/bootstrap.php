<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
define('ROOT', dirname(__DIR__) . DS);
define('CORE_PATH', ROOT . 'vendor' . DS . 'cakephp' . DS . 'cakephp' . DS);
define('CAKE', CORE_PATH . 'src' . DS);
define('APP', ROOT . 'tests' . DS . 'test_app' . DS);
define('APP_DIR', 'test_app');
define('WWW_ROOT', APP . 'webroot' . DS);
define('TMP', ROOT . 'tests' . DS . 'tmp' . DS);
define('CACHE', TMP);
define('LOGS', TMP);
define('CONFIG', APP . 'config' . DS);

@mkdir(TMP, 0777, true);

require_once CORE_PATH . 'config/bootstrap.php';
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');
Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'App',
    'encoding' => 'UTF-8',
    'base' => false,
    'baseUrl' => false,
    'dir' => 'src',
    'webroot' => 'webroot',
    'wwwRoot' => WWW_ROOT,
    'fullBaseUrl' => 'http://localhost',
    'paths' => [
        'plugins' => [APP . 'plugins' . DS],
        'templates' => [APP . 'templates' . DS],
    ],
]);

Cache::setConfig([
    '_cake_translations_' => ['engine' => 'File', 'prefix' => 'cake_translations_', 'serialize' => true],
    '_cake_model_' => ['engine' => 'File', 'prefix' => 'cake_model_', 'serialize' => true],
    'default' => ['engine' => 'File', 'prefix' => 'default_', 'serialize' => true],
]);

if (!getenv('DB_URL')) {
    putenv('DB_URL=sqlite:///:memory:');
}
ConnectionManager::setConfig('test', ['url' => getenv('DB_URL'), 'timezone' => 'UTC']);
ConnectionManager::setConfig('default', ['url' => getenv('DB_URL'), 'timezone' => 'UTC']);

Log::setConfig([
    'debug' => ['engine' => 'Cake\Log\Engine\FileLog', 'levels' => ['notice', 'info', 'debug'], 'file' => 'debug'],
    'error' => ['engine' => 'Cake\Log\Engine\FileLog', 'levels' => ['warning', 'error', 'critical', 'alert', 'emergency'], 'file' => 'error'],
]);
