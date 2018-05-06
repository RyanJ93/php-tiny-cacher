<?php
require dirname(__FILE__) . '/php-tiny-cacher.php';

use PHPTinyCacher\PHPTinyCacher;

$namespace = 'demo';
$ttl = 120;
$increment = 4;

$total = microtime(true);
$strategies = PHPTinyCacher::getSupportedStrategies();
echo 'Supported strategies: ' . implode(', ', $strategies) . PHP_EOL;
$cache = new PHPTinyCacher();
$cache->setNamespace($namespace);
$cache->setVerbose(true);
$cache->setDefaultTTL($ttl);
foreach ( $strategies as $key => $value ){
	echo 'Starting test using "' . $value . '" as strategy...' . PHP_EOL;
	switch ( $value ){
		case 'redis':{
			$cache->connectToRedis('127.0.0.1', 6379);
		}break;
		case 'memcached':{
			$cache->connectToMemcached(array(
				array('127.0.0.1', 11211)
			));
		}break;
		case 'sqlite3':{
			$cache->connectToSQLite('cache.db');
		}break;
		case 'file':{
			$cache->setStorageDirectory('cache');
		}break;
	}
	$cache->setStrategy($value);
	$start = microtime(true);
	echo 'Pushing some elements into the cache...' . PHP_EOL;
	$cache->pushMulti(array(
		'cache-entry' => 'Some data that should be cached for next uses 🍭',
		'foo' => 'bar',
		'serialised' => array(1, 2, 3, 5, 'a', true),
		'numeric' => 10
	), true);
	echo 'Elements pushed into the cache, checking if one element exists...' . PHP_EOL;
	echo 'Does the element exist? ' . ( $cache->has('cache-entry') === true ? 'Yes.' : 'No.' ) . PHP_EOL;
	echo 'Retrieving the same element...' . PHP_EOL;
	echo 'Value: ' . $cache->pull('cache-entry', true) . PHP_EOL;
	echo 'Incrementing the numeric entry...' . PHP_EOL;
	$cache->increment('numeric', $increment);
	echo 'Incremented value now is: ' . $cache->pull('numeric') . PHP_EOL;
	echo 'Removing the element...' . PHP_EOL;
	$cache->remove('cache-entry');
	echo 'The element has been removed, dropping all elements from the cache...' . PHP_EOL;
	$cache->invalidate(true);
	echo 'Cache content cleared.' . PHP_EOL;
	echo 'Test completed in ' . ( microtime(true) - $start ) . ' seconds.' . PHP_EOL . PHP_EOL;
}
echo 'All tests completed in ' . ( microtime(true) - $total ) . ' seconds.' . PHP_EOL . PHP_EOL;