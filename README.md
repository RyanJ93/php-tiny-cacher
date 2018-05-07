# PHP Tiny Cacher

PHP Tiny Cacher is a simple package that provides a simple API for data caching supporting multiple storage options, such as Redis, Memcached, SQLite and files, supporting basic operations, increments and TTL, allowing to store strings as well as other kind of variables that will be serialised into JSON or other formats, according to the storage strategy in use.

## Installation

According with your needs, you may have to install some additional extensions: if you are going to use Redis, you need to install the required extension using the command `pecl install redis`, in a similar way to use Memcached, you need to install the required module using the command `pecl install memcached` (sometimes this extension is shipped with PHP), about SQLite3, it is usually shipped with PHP. Once the requirements are mets, you can install the module running this command:

````bash
composer require ryanj93/php-tiny-cacher
````

## Usage

First you need to set up a class instance according with the storage option that you are going to use, here you are some examples:

### Basic setup

First you need to create an instance of the class, then you can set the general setting like the namespace for cache entries and the default TTL.

````php
$cache = new PHPTinyCacher();
//Set an optional namespace for entries.
$cache->setNamespace('your namespace here');
//Set the default TTL (in seconds), by default entries have no expire.
$cache->setDefaultTTL(120);
````

#### Using internal storage

You can save your data internally within the class instance or in a shared way:

````php
//Store data within the class instance.
$cache->setStrategy(PHPTinyCacher::STRATEGY_LOCAL);
//Store data within the script but shared across class instances.
$cache->setStrategy(PHPTinyCacher::STRATEGY_SHARED);
//Or you can also save data within the session.
$cache->setStrategy(PHPTinyCacher::STRATEGY_SESSION);
````

#### Using Redis

````php
//address, port, DB index
$cache->setStrategy(PHPTinyCacher::STRATEGY_REDIS)->connectToRedis('127.0.0.1', 6379, 0);
````

For more information about the extension used with Redis check the official repository on [GitHub](https://github.com/phpredis/phpredis).

#### Using Memcached

````php
//A sequential array that contains the information about each node as array.
$cache->setStrategy(PHPTinyCacher::STRATEGY_MEMCACHED)->connectToMemcached(array(
	array('127.0.0.1', 11211)
));
````

For more information about the extension used with Memcached check the official page on the [PHP documentation](http://php.net/manual/en/book.memcached.php).

#### Using SQLite3

````php
$cache->setStrategy(PHPTinyCacher::STRATEGY_SQLITE)->connectToSQLite('path/to/sqlite.db');
````

For more information about the SQLite3 extension refer on the official documentation that can be found to the page on the [PHP documentation](http://php.net/manual/en/book.sqlite3.php).

#### Using files

````php
$cache->setStrategy(PHPTinyCacher::STRATEGY_FILE)->setStorageDirectory('path/to/storage/directory');
````

### Operations

Once you created the class instance and completed the connection with the storage service you can start adding items to the cache, here you are an example:

````php
//Save one element.
//key, value, overwrite, ttl
$cache->push('key', 'value', true, 60);
//Save multiple elements.
//elements, overwrite, ttl
$cache->pushMulti(array(
	'someKey' => 'Some value',
	'key' => 'value'
), true, 60);
````

In a similar way you can retrieve the value of one or multiple keys, here an example:

````php
//key, quiet (return null instead of throwing an exception).
//Element will contain the value of the element or null if no such value were found and quiet mode has been enabled.
$element = $cache->pull('key', true);
//You can pull multiple elements by using this method and passing an array of keys.
//array of keys, quiet.
//The elements are returned as associative array having as key the entry key and as value the corresponding value or null if no such value were found and quiet mode has been enabled.
$elements = $cache->pullMulti(array('key', 'some other key'), true);
````

You can check if a key exists as following:

````php
//Result is a boolean value.
$result = $cache->has('key');
//You can check for multiple keys as well.
//Results is an associative array having as key the element key and as value a boolean variable.
$results = $cache->hasMulti(array('key', 'some other key'));
//And you can check if all the given keys exist.
//Result is a boolean value.
$result = $cache->hasAll(array('key', 'some other key'));
````

Then you can remove a key in this way:

````php
$cache->remove('key');
//You can remove multiple elements with a single call as following:
$cache->removeMulti(array('key', 'some other key'));
````

If you are working with numeric values you can use increments, note that currently this feature is not available when using files as storage option:

````php
//Pass the element key and the increment value, it can be a negative value as well, by default 1 is used.
$cache->increment('key', 3);
//Of course you can apply increment on multiple elements.
$cache->incrementMulti(array('key', 'some other key'), 3);
//And decrement values, note that these methods internally call the methods "increment" or "incrementMulti" using a negative increment value.
$cache->decrement('key', 3);
````

You can remove all stored elements using this method, alternatively you can remove all the elements stored under a given namespace:

````php
//If you pass "true" as parameter it will remove all elements created by this class, no matter the namespace.
$cache->invalidate();
````

If you switch to another storage strategy, you may want to close no more used connections, in this case you may want to run this method:

````php
//If you pass "true" as parameters, it will close all connections, no matter the storage option in use.
$cache->closeConnections();
````

## Considerations on TTL

TTL is supported in almost all storage options, anyway currently is not supported when using file as option. TTL is natively supported by Redis and Memcached, while is you are using another storage option you will need to use one of these technique in order to remove dead records, note that expired records will not be considered in data readings so this operation is only required whenever you need to free up some memory, here you are some usage example:

````php
//Remove expired elements saved in shared storage.
PHPTinyCacher::runGlobalGarbageCollector();
//Remove expired elements saved in session.
PHPTinyCacher::runSessionGarbageCollector();
//Remove expired elements saved in local storage.
$cache->runGarbageCollector();
//Remove expired elements saved in a SQLite3 database.
$cache->runSQLite3GarbageCollector();
```` 

If you like this project and think that is useful don't be scared and feel free to contribute reporting bugs, issues or suggestions or if you feel generous, you can send a donation [here](https://www.enricosola.com/about#donations).

Are you looking for the Node.js version? Give a look [here](https://github.com/RyanJ93/tiny-cacher).