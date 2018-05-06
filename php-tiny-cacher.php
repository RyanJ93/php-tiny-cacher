<?php
/**
* A simple library that allows to easily cache stuff using several storage options such as Redis, Memcached and SQLite.
*
* @package     php-tiny-cacher
* @author      Enrico Sola <info@enricosola.com>
* @version     v.1.1.0
*/

namespace PHPTinyCacher{
	class PHPTinyCacher{
		/**
		* @const string VERSION A string containing the version of this library.
		*/
		const VERSION = '1.1.0';
		
		/**
		* @const int STRATEGY_INTERNAL Specifies that the cache must be stored in a property within the class instance.
		*/
		const STRATEGY_INTERNAL = 1;
		
		/**
		* @const int STRATEGY_LOCAL Specifies that the cache must be stored in a property within the class instance.
		*/
		const STRATEGY_LOCAL = 1;
		
		/**
		* @const int STRATEGY_SHARED Specifies that the cache must be stored in a variable that is visible across all class instances.
		*/
		const STRATEGY_SHARED = 2;
		
		/**
		* @const int STRATEGY_INTERNAL_SHARED Specifies that the cache must be stored in a variable that is visible across all class instances.
		*/
		const STRATEGY_INTERNAL_SHARED = 2;
		
		/**
		* @const int STRATEGY_SESSION Specifies that the cache must be stored in PHP session, note that this strategy is not available if the script is running in CLI.
		*/
		const STRATEGY_SESSION = 3;
		
		/**
		* @const int STRATEGY_REDIS Specifies that the cache must be stored in a Redis powered database.
		*/
		const STRATEGY_REDIS = 4;
		
		/**
		* @const int STRATEGY_MEMCACHED Specifies that the cache must be stored in Memcached.
		*/
		const STRATEGY_MEMCACHED = 5;
		
		/**
		* @const int STRATEGY_SQLITE3 Specifies that the cache must be stored in a SQLite3 database.
		*/
		const STRATEGY_SQLITE3 = 6;
		
		/**
		* @const int STRATEGY_SQLITE Specifies that the cache must be stored in a SQLite3 database.
		*/
		const STRATEGY_SQLITE = 6;
		
		/**
		* @const int STRATEGY_FILE Specifies that the cache must be stored in files.
		*/
		const STRATEGY_FILE = 7;
		
		/**
		* @var array $staticStorage An associative array that contains the cached data that should be shared across all class instances.
		*/
		protected static $staticStorage = array();
		
		/**
		* @var array $storage An associative array that contains the cached data when using "local" as caching strategy.
		*/
		protected $storage = array();
		
		/**
		* @var bool $ready If set to "true" it means that the class is ready to handle operations, otherwise is waiting connections with external providers.
		*/
		protected $ready = true;
		
		/**
		* @var int $strategy An integer number greater than zero rerpesenting the caching strategy in use.
		*/
		protected $strategy = 1;
		
		/**
		* @var string $namespace A string containing the namespace for cache entries, if set to empty string, no namespace will be used.
		*/
		protected $namespace = '';
		
		/**
		* @var string $namespaceHash A string containing the hash of the namespace in use obtained using the MD5 algorithm, if no namespace is in use, it will be set to "*".
		*/
		protected $namespaceHash = '*';
		
		/**
		* @var int $defaultTTL An integer number greater or equal than zero representing the TTL (Time To Live) used by default for all cache entries, if cache entries have no expire, it will be set to zero.
		*/
		protected $defaultTTL = 0;
		
		/**
		* @var bool $verbose If set to "true" error messages will be displayed, otherwise not.
		*/
		protected $verbose = false;
		
		/**
		* @var Redis $redisConnection An instance of the class "Redis" representing the connection with the Redis server, more documentation on Redis driver can be found here: https://github.com/phpredis/phpredis
		*/
		protected $redisConnection = NULL;
		
		/**
		* @var Memcached $memcachedConnection An instance of the class "Memcached" representing the connection with the Memcached server.
		*/
		protected $memcachedConnection = NULL;
		
		/**
		* @var SQLite3 $sqliteConnection An instance of the class "SQLite3" representing the connection with the SQLite database file.
		*/
		protected $sqliteConnection = NULL;
		
		/**
		* @var string $storagePath A string containing the path to the directory where the cache files are stored in.
		*/
		protected $storagePath = NULL;
		
		/**
		* Initialize the object that contains the cache or the connection with Redis, this method is used internally by the class and is chainable.
		*
		* @throws Exception If the session is not available, usually because the script is running in CLI or because they have been disabled in PHP configuration.
		* @throws Exception If no connection with Redis has been found.
		* @throws Exception If no connection with Memcached has been found.
		* @throws Exception If no connection with the SQLite3 database has been found.
		* @throws Exception If no storage path has been defined.
		*/
		protected function init(): PHPTinyCacher{
			switch ( $this->getStrategy() ){
				case self::STRATEGY_SHARED:{
					if ( self::$staticStorage === NULL || is_array(self::$staticStorage) === false ){
						self::$staticStorage = array();
					}
				}break;
				case self::STRATEGY_SESSION:{
					if ( php_sapi_name() === 'cli' ){
						throw new \Exception('Session is not available.');
					}
					if ( session_status() === \PHP_SESSION_DISABLED ){
						throw new \Exception('Session is not available.');
					}
					session_start();
					if ( session_status() !== \PHP_SESSION_ACTIVE ){
						throw new \Exception('Session is not available.');
					}
					if ( isset($_SESSION['php-tiny-cacher']) === false || $_SESSION['php-tiny-cacher'] === NULL || is_array($_SESSION['php-tiny-cacher']) === false ){
						$_SESSION['php-tiny-cacher'] = array();
					}
				}break;
				case self::STRATEGY_REDIS:{
					if ( $this->redisConnected() === false ){
						throw new \Exception('Redis is not connected.');
					}
				}break;
				case self::STRATEGY_MEMCACHED:{
					if ( $this->memcachedConnected() === false ){
						throw new \Exception('Memcached is not connected.');
					}
				}break;
				case self::STRATEGY_SQLITE3:{
					if ( $this->SQLite3Connected() === false ){
						throw new \Exception('SQLite3 is not connected.');
					}
				}break;
				case self::STRATEGY_FILE:{
					$path = $this->storagePath;
					if ( $path === NULL || $path === '' || is_string($path) === false ){
						throw new \Exception('No storage path defined.');
					}
				}break;
				default:{
					if ( $this->storage === NULL || is_array($this->storage) === false ){
						$this->storage = array();
					}
				}break;
			}
			return $this;
		}
		
		/**
		* Creates a key that can be used as the element's identifier in database archiviation, preventing problems due to namespace or key length or encoding, this method is used internally by the class.
		*
		* @param string $key A string containing the key of the element.
		*
		* @return array An associative array of strings containing the hashed namespace and key (using the MD5 algorithm) and the key that can be used to store the element within the database.
		*/
		protected function createKey(string $key = NULL): array{
			$namespaceHash = $this->namespaceHash;
			if ( $namespaceHash === NULL || $namespaceHash === '' || is_string($namespaceHash) === false ){
				$namespace = $this->getNamespace();
				$this->namespaceHash = $namespaceHash = $namespace === '' ? '*' : hash('md5', $namespace);
			}
			$key = array(
				'namespace' => $this->namespaceHash,
				'key' => $key !== NULL && $key !== '' ? hash('md5', $key) : NULL
			);
			$key['merged'] = $key['key'] !== NULL ? ( 'php-tiny-cacher:' . $key['namespace'] . ':' . $key['key'] ) : NULL;
			return $key;
		}
		
		/**
		* Returns all supported strategies according to installed and loaded extensions (drivers).
		*
		* @param bool $numeric If set to "true" will be returned a sequential array contianing the identifiers of the strategies as integer numbers, otherwise as strings.
		*
		* @return array A sequential array containing the strategies identifiers.
		*/
		public static function getSupportedStrategies(bool $numeric = false): array{
			$strategies = $numeric === true ? array(self::STRATEGY_LOCAL, self::STRATEGY_SHARED) : array('local', 'shared');
			$extensions = get_loaded_extensions(false);
			if ( php_sapi_name() !== 'cli' && session_status() !== \PHP_SESSION_DISABLED ){
				$strategies[] = $numeric === true ? self::STRATEGY_SESSION : 'session';
			}
			if ( in_array('redis', $extensions) === true && class_exists('Redis') === true ){
				$strategies[] = $numeric === true ? self::STRATEGY_REDIS : 'redis';
			}
			if ( in_array('memcached', $extensions) === true && class_exists('Memcached') === true ){
				$strategies[] = $numeric === true ? self::STRATEGY_MEMCACHED : 'memcached';
			}
			if ( in_array('sqlite3', $extensions) === true && class_exists('SQLite3') === true ){
				$strategies[] = $numeric === true ? self::STRATEGY_SQLITE3 : 'sqlite3';
			}
			$strategies[] = $numeric === true ? self::STRATEGY_FILE : 'file';
			return $strategies;
		}
		
		/**
		* Checks if a given strategy is supported or not.
		*
		* @param int|string The identifier of the strategy that will be checked.
		*
		* @return bool If the given strategy is supported will be returned "true", otherwise "false".
		*/
		public static function isSupportedStrategy($strategy): bool{
			if ( is_string($strategy) === true ){
				return in_array(strtolower($strategy), self::getSupportedStrategies(false)) === true ? true : false;
			}
			if ( is_int($strategy) === false || $strategy <= 0 ){
				return false;
			}
			return in_array($strategy, self::getSupportedStrategies(false)) === true ? true : false;
		}
		
		/**
		* Removes all the expired cache entries, accoring with their TTL, note that it will affect only entries contained in the global storage that is shared and available across all class instances.
		*/
		public static function runGlobalGarbageCollector(){
			$now = time();
			foreach ( self::$storage as $key => $value ){
				foreach ( self::$storage[$key] as $_key => $_value ){
					if ( isset($_value['expire']) === true && $_value['expire'] < $now ){
						unset(self::$storage[$key][$_key]);
					}
				}
			}
		}
		
		/**
		* Removes all the expired cache entries, accoring with their TTL, note that it will affect only entries contained in the global storage that is shared and available across all class instances.
		*
		* @throws Exception If the session is not available, usually because the script is running in CLI or because they have been disabled in PHP configuration.
		*/
		public static function runSessionGarbageCollector(){
			if ( php_sapi_name() === 'cli' ){
				throw new \Exception('Session is not available.');
			}
			if ( session_status() === \PHP_SESSION_DISABLED ){
				throw new \Exception('Session is not available.');
			}
			session_start();
			if ( session_status() !== \PHP_SESSION_ACTIVE ){
				throw new \Exception('Session is not available.');
			}
			if ( isset($_SESSION['php-tiny-cacher']) === false || $_SESSION['php-tiny-cacher'] === NULL || is_array($_SESSION['php-tiny-cacher']) === false ){
				$_SESSION['php-tiny-cacher'] = array();
			}
			$now = time();
			foreach ( $_SESSION['php-tiny-cacher'] as $key => $value ){
				foreach ( $_SESSION['php-tiny-cacher'][$key] as $_key => $_value ){
					if ( isset($_value['expire']) === true && $_value['expire'] < $now ){
						unset($_SESSION['php-tiny-cacher'][$key][$_key]);
					}
				}
			}
		}
		
		/**
		* Class constructor.
		*
		* @param int|string $strategy A string containing the name of the strategy to use, alternatively, an integer number representing the strategy, in this case, you can use one of the predefined constants.
		*/
		public function __construct($strategy = 1){
			$this->setStrategy($strategy);
			$this->ready = true;
		}
		
		/**
		* Sets the caching strategy, this method is chainable.
		*
		* @param int|string $strategy A string containing the name of the strategy to use, alternatively, an integer number representing the strategy, in this case, you can use one of the predefined constants.
		*/
		public function setStrategy($strategy): PHPTinyCacher{
			switch ( is_string($strategy) === true ? strtolower($strategy) : $strategy ){
				case self::STRATEGY_SHARED:
				case 'shared':{
					$strategy = self::STRATEGY_SHARED;
				}break;
				case self::STRATEGY_SESSION:
				case 'session':{
					$strategy = self::STRATEGY_SESSION;
				}break;
				case self::STRATEGY_REDIS:
				case 'redis':{
					$strategy = self::STRATEGY_REDIS;
				}break;
				case self::STRATEGY_MEMCACHED:
				case 'memcached':{
					$strategy = self::STRATEGY_MEMCACHED;
				}break;
				case self::STRATEGY_SQLITE3:
				case 'sqlite':
				case 'sqlite3':{
					$strategy = self::STRATEGY_SQLITE3;
				}break;
				case self::STRATEGY_FILE:
				case 'file':{
					$strategy = self::STRATEGY_FILE;
				}break;
				default:{
					$strategy = self::STRATEGY_INTERNAL;
				}break;
			}
			$this->strategy = $strategy;
			return $this;
		}
		
		/**
		* Returns the caching strategy.
		*
		* @return int An integer number representing the strategy.
		*/
		public function getStrategy(): int{
			$strategy = $this->strategy;
			return $strategy !== NULL && is_int($strategy) === true && $strategy > 0 && $strategy <= 7 ? $strategy : 1;
		}
		
		/**
		* Returns the name of the caching strategy.
		*
		* @return string A string containing the name of the caching strategy.
		*/
		public function getStrategyName(){
			$strategy = $this->strategy;
			switch ( is_int($strategy) === true ? $strategy : NULL ){
				case self::STRATEGY_SHARED:{
					return 'shared';
				}break;
				case self::STRATEGY_SESSION:{
					return 'session';
				}break;
				case self::STRATEGY_REDIS:{
					return 'redis';
				}break;
				case self::STRATEGY_MEMCACHED:{
					return 'memcached';
				}break;
				case self::STRATEGY_SQLITE3:{
					return 'sqlite3';
				}break;
				case self::STRATEGY_FILE:{
					return 'file';
				}break;
				default:{
					return 'local';
				}break;
			}
		}
		
		/**
		* Sets an additional string that will be prepend to each key, this method is chainable.
		*
		* @param string $namespace A string containing the namespace, if another kind of variable or if an empty string is given, no namespace is set.
		*/
		public function setNamespace(string $namespace): PHPTinyCacher{
			$namespace = $namespace === NULL ? '' : $namespace;
			$buffer = $this->namespace;
			if ( $buffer === NULL || is_string($buffer) === false || $namespace !== $buffer ){
				$this->namespaceHash = $namespace === '' ? '*' : hash('md5', $namespace);
				$this->namespace = $namespace;
			}
			return $this;
		}
		
		/**
		* Returns the additional string that will be prepend to each key.
		*
		* @return string A string containing the namespace or an empty string if no namespace is going to be used.
		*/
		public function getNamespace(): string{
			$namespace = $this->namespace;
			return $namespace !== NULL && is_string($namespace) === true ? $namespace : '';
		}
		
		/**
		* Sets the default TTL (Time To Live) for the enries, this method is chainable.
		*
		* @param int $ttl An optional integer number greater than zero representing the ammount of seconds until the elements will expire, if set to zero or an invalid value, no TTL will be used by default.
		*/
		public function setDefaultTTL(int $ttl = 0): PHPTinyCacher{
			if ( $ttl === NULL ){
				$this->defaultTTL = 0;
				return this;
			}
			$this->defaultTTL = $ttl <= 0 ? 0 : $ttl;
			return $this;
		}
		
		/**
		* Returns the default TTL (Time To Live) for the enries.
		*
		* @return int An integer number representing the default TTL, if not TTL has been defined, 0 will be returned.
		*/
		public function getDefaultTTL(): int{
			$ttl = $this->defaultTTL;
			if ( $ttl === NULL || is_int($ttl) === false || $ttl <= 0 ){
				return 0;
			}
			return $ttl;
		}
		
		/**
		* Sets if exceptions should be displayed or not, this can be very useful in debug, this method is chainable.
		*
		* @param bool $verbose If set to "true", exceptions and error messages will be displayed, otherwise not.
		*/
		public function setVerbose(bool $verbose = false): PHPTinyCacher{
			$this->verbose = $verbose === true ? true : false;
			return $this;
		}
		
		/**
		* Returns if exceptions should be displayed or not.
		*
		* @return bool If exceptions and messages are going to be displayed, will be returned "true", otherwise "false".
		*/
		public function getVerbose(): bool{
			$verbose = $this->verbose;
			return is_bool($verbose) === true && $verbose === true ? true : false;
		}
		
		/**
		* Removes all the expired cache entries, accoring with their TTL, note that it will affect only entries contained in the local storage of this class instance, this method is chainable.
		*/
		public static function runGarbageCollector(): PHPTinyCacher{
			$now = time();
			foreach ( $this->storage as $key => $value ){
				foreach ( $this->storage[$key] as $_key => $_value ){
					if ( isset($_value['expire']) === true && $_value['expire'] < $now ){
						unset($this->storage[$key][$_key]);
					}
				}
			}
			return $this;
		}
		
		/**
		* Removes all the expired cache entries, accoring with their TTL, from the SQLite database that has been set within the class instance as storage for cache, this method is chainable.
		*
		* @throws Exception If an error occurs during database initialisation.
		* @throws Exception If an error occurs during the transaction with SQLite3.
		*/
		public function runSQLite3GarbageCollector(): PHPTinyCacher{
			$verbose = $this->getVerbose();
			$result = false;
			try{
				$this->init();
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				throw new \Exception('An error occurred while initialising the storage.', NULL, $ex);
			}
			try{
				$result = $this->sqliteConnection->run('DELETE FROM cache_storage WHERE expire < DATETIME("now");');
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				throw new \Exception('An error occurred during the transaction with SQLite3.', NULL, $ex);
			}
			if ( $result === false ){
				throw new \Exception('An error occurred during the transaction with SQLite3.');
			}
			return $this;
		}
		
		/**
		* Stores a value with a given key, this method is chainable.
		*
		* @param string $key A string representin the identifier of the value that will be stored.
		* @param mixed $value The value that will be stored, when using Redis or Memcached, the value is converted into a string.
		* @param bool $overwrite If set to "true" and if the value already exists, it will be overwritten, otherwise an exception will be thrown.
		* @param int $ttl An optional integer number greater than zero representing the ammount of seconds until the element will expire, if not set, default TTL will be used, if no default TTL were found, element has no expire date.
		*
		* @throws InvalidArgumentException If an invalid key were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If the key already exists and if is not going to be overwritten.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs while serializing the given value as JSON string.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		* @throws Exception If an error occurs while creating the storage directory.
		* @throws Exception If an error occurrs while writing the cache file.
		*/
		public function push(string $key, $value, bool $overwrite = false, int $ttl = NULL): PHPTinyCacher{
			if ( $key === NULL || $key === '' ){
				throw new \InvalidArgumentException('Invalid key.');
			}
			$key = $this->createKey($key);
			$verbose = $this->getVerbose();
			$ttl = $ttl !== NULL && $ttl > 0 ? $ttl : $this->getDefaultTTL();
			$timestamp = $ttl > 0 ? ( time() + $ttl ) : 0;
			try{
				$this->init();
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				throw new \Exception('An error occurred while initialising the storage.', NULL, $ex);
			}
			switch ( $this->getStrategy() ){
				case self::STRATEGY_SHARED:{
					if ( isset(self::$staticStorage[$key['namespace']]) === false || is_array(self::$staticStorage[$key['namespace']]) === false ){
						self::$staticStorage[$key['namespace']] = array(
							$key['key'] => array(
								'value' => $value,
								'expire' => $timestamp
							)
						);
						return $this;
					}
					if ( $overwrite !== true && isset(self::$staticStorage[$key['namespace']][$key['key']]) === true ){
						if ( isset(self::$staticStorage[$key['namespace']][$key['key']]['expire']) === false || ( self::$staticStorage[$key['namespace']][$key['key']]['expire'] !== NULL && self::$staticStorage[$key['namespace']][$key['key']]['expire'] >= time() ) ){
							throw new \Exception('This key already exists.');
						}
					}
					self::$staticStorage[$key['namespace']][$key['key']] = array(
						'value' => $value,
						'expire' => $timestamp
					);
				}break;
				case self::STRATEGY_SESSION:{
					if ( isset($_SESSION['php-tiny-cacher'][$key['namespace']]) === false || is_array($_SESSION['php-tiny-cacher'][$key['namespace']]) === false ){
						$_SESSION['php-tiny-cacher'][$key['namespace']] = array(
							$key['key'] => array(
								'value' => $value,
								'expire' => $timestamp
							)
						);
						return $this;
					}
					if ( $overwrite !== true && isset($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]) === true ){
						if ( isset($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['expire']) === false ){
							throw new \Exception('This key already exists.');
						}
						if ( $_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['expire'] !== NULL && $_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['expire'] >= time() ){
							throw new \Exception('This key already exists.');
						}
					}
					$_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']] = array(
						'value' => $value,
						'expire' => $timestamp
					);
				}break;
				case self::STRATEGY_REDIS:{
					$value = json_encode($value, \JSON_NUMERIC_CHECK);
					if ( $value === false ){
						throw new \Exception('Unable to serialise the given value as JSON string.');
					}
					if ( $overwrite === true ){
						try{
							$result = $ttl > 0 ? $this->redisConnection->set($key['merged'], $value, array('ex' => $ttl)) : $this->redisConnection->set($key['merged'], $value);
						}catch(\Exception $ex){
							if ( $verbose === true ){
								echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
							}
							throw new \Exception('An error occurred in Redis transaction.', NULL, $ex);
						}
						if ( $result === false ){
							throw new \Exception('An error occurred in Redis transaction.');
						}
						return $this;
					}
					try{
						$result = $this->redisConnection->exists($key['merged']);
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred in Redis transaction.', NULL, $ex);
					}
					if ( $result !== 0 ){
						throw new \Exception('This key already exists.');
					}
					try{
						$result = $ttl > 0 ? $this->redisConnection->set($key['merged'], $value, array('ex' => $ttl)) : $this->redisConnection->set($key['merged'], $value);
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred in Redis transaction.', NULL, $ex);
					}
					if ( $result === false ){
						throw new \Exception('An error occurred in Redis transaction.');
					}
					return $this;
				}break;
				case self::STRATEGY_MEMCACHED:{
					if ( $overwrite === true ){
						try{
							$result = $this->memcachedConnection->set($key['merged'], $value, $ttl);
						}catch(\Exception $ex){
							if ( $verbose === true ){
								echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
							}
							throw new \Exception('An error occurred in Memcached transaction.', NULL, $ex);
						}
						if ( $result === false ){
							throw new \Exception('An error occurred in Memcached transaction.');
						}
						return $this;
					}
					try{
						$result = $this->memcachedConnection->get($key['merged']);
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred in Memcached transaction.', NULL, $ex);
					}
					if ( $result !== false ){
						throw new \Exception('This key already exists.');
					}
					try{
						$result = $this->memcachedConnection->set($key['merged'], $value, $ttl);
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred in Memcached transaction.', NULL, $ex);
					}
					if ( $result === false ){
						throw new \Exception('An error occurred in Memcached transaction.');
					}
				}break;
				case self::STRATEGY_SQLITE3:{
					$numeric = is_int($value) === true || is_float($value) === true ? 1 : 0;
					$value = json_encode($value, \JSON_NUMERIC_CHECK);
					if ( $value === false ){
						throw new \Exception('Unable to serialise the given value as JSON string.');
					}
					try{
						$query = ( $overwrite === true ? 'INSERT OR REPLACE ' : 'INSERT ' ) . 'INTO cache_storage (namespace, key, value, numeric, date, expire) VALUES (:namespace, :key, :value, :numeric, DATETIME("now"), :expire);';
						$statement = $this->sqliteConnection->prepare($query);
						$statement->bindValue('namespace', $key['namespace'], \SQLITE3_TEXT);
						$statement->bindValue('key', $key['key'], \SQLITE3_TEXT);
						$statement->bindValue('value', $value, \SQLITE3_TEXT);
						$statement->bindValue('numeric', $numeric, \SQLITE3_INTEGER);
						$statement->bindValue('expire', date('Y-m-d H:i:s', $timestamp), \SQLITE3_TEXT);
						$result = $statement->execute();
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred during the transaction with SQLite3.', NULL, $ex);
					}
					if ( $result === false ){
						throw new \Exception('An error occurred during the transaction with SQLite3.');
					}
				}break;
				case self::STRATEGY_FILE:{
					$path = $this->storagePath . '/' . $key['namespace'];
					if ( file_exists($path) === false ){
						if ( mkdir($path, 0777, true) === false ){
							throw new \Exception('An error occurred while creating the storage directory.');
						}
					}
					$path .= '/' . $key['key'] . '.cache';
					if ( $overwrite !== true && ile_exists($path) === true ){
						throw new \Exception('This key already exists.');
					}
					$value = json_encode($value, \JSON_NUMERIC_CHECK);
					if ( $value === false ){
						throw new \Exception('Unable to serialise the given value as JSON string.');
					}
					if ( file_put_contents($path, $value) === false ){
						throw new \Exception('An error occurred while writing the file.');
					}
				}break;
				default:{
					if ( isset($this->storage[$key['namespace']]) === false || is_array($this->storage[$key['namespace']]) === false ){
						$this->storage[$key['namespace']] = array(
							$key['key'] => array(
								'value' => $value,
								'expire' => $timestamp
							)
						);
						return $this;
					}
					if ( $overwrite !== true && isset($this->storage[$key['namespace']][$key['key']]) === true ){
						if ( isset($this->storage[$key['namespace']][$key['key']]['expire']) === false || ( $this->storage[$key['namespace']][$key['key']]['expire'] !== NULL && $this->storage[$key['namespace']][$key['key']]['expire'] >= time() ) ){
							throw new \Exception('This key already exists.');
						}
					}
					$this->storage[$key['namespace']][$key['key']] = array(
						'value' => $value,
						'expire' => $timestamp
					);
				}break;
			}
			return $this;
		}
		
		/**
		* Stores a value with a given key, this method is an alias of the "push" method and is chainable.
		*
		* @param string $key A string representin the identifier of the value that will be stored.
		* @param mixed $value The value that will be stored, when using Redis or Memcached, the value is converted into a string.
		* @param bool $overwrite If set to "true" and if the value already exists, it will be overwritten, otherwise an exception will be thrown.
		* @param int $ttl An optional integer number greater than zero representing the ammount of seconds until the element will expire, if not set, default TTL will be used, if no default TTL were found, element has no expire date.
		*
		* @throws InvalidArgumentException If an invalid key were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If the key already exists and if is not going to be overwritten.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs while serializing the given value as JSON string.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		* @throws Exception If an error occurs while creating the storage directory.
		* @throws Exception If an error occurrs while writing the cache file.
		*/
		public function set(string $key, $value, bool $overwrite = false, int $ttl = NULL): PHPTinyCacher{
			$this->push($key, $value, $overwrite, $ttl);
			return $this;
		}
		
		/**
		* Stores multiple elements within the cache, this method is chainable.
		*
		* @param array $elements An associative array containing the elements that will be stored as key/value pairs.
		* @param bool $overwrite If set to "true" and if the value already exists, it will be overwritten, otherwise an exception will be thrown.
		* @param int $ttl An optional integer number greater than zero representing the ammount of seconds until the element will expire, if not set, default TTL will be used, if no default TTL were found, element has no expire date.
		*
		* @throws InvalidArgumentException If an invalid array is provided.
		* @throws InvalidArgumentException If an invalid key within the array were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If the key already exists and if is not going to be overwritten.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs while serializing the given value as JSON string.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		* @throws Exception If an error occurs while creating the storage directory.
		* @throws Exception If an error occurrs while writing the cache file.
		*/
		public function pushMulti(array $elements, bool $overwrite = false, int $ttl = NULL): PHPTinyCacher{
			if ( $elements === NULL ){
				throw new \InvalidArgumentException('Invalid elements.');
			}
			if ( empty($elements) === true ){
				return $this;
			}
			foreach ( $elements as $key => $value ){
				if ( $key === NULL || $key === '' || is_string($key) === false ){
					throw new \InvalidArgumentException('Invalid key.');
				}
			}
			foreach ( $elements as $key => $value ){
				$this->push($key, $value, $overwrite, $ttl);
			}
			return $this;
		}
		
		/**
		* Stores multiple elements within the cache, this method is an alias of the "pushMulti" method and is chainable.
		*
		* @param array $elements An associative array containing the elements that will be stored as key/value pairs.
		* @param bool $overwrite If set to "true" and if the value already exists, it will be overwritten, otherwise an exception will be thrown.
		* @param int $ttl An optional integer number greater than zero representing the ammount of seconds until the element will expire, if not set, default TTL will be used, if no default TTL were found, element has no expire date.
		*
		* @throws InvalidArgumentException If an invalid array is provided.
		* @throws InvalidArgumentException If an invalid key within the array were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If the key already exists and if is not going to be overwritten.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs while serializing the given value as JSON string.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		* @throws Exception If an error occurs while creating the storage directory.
		* @throws Exception If an error occurrs while writing the cache file.
		*/
		public function setMulti(array $elements, bool $overwrite = false, int $ttl = NULL): PHPTinyCacher{
			$this->pushMulti($elements, $overwrite, $ttl);
			return $this;
		}
		
		/**
		* Returns a value that has been stored within the cache.
		*
		* @param string $key A string representin the identifier of the value that has been stored.
		* @param bool $quiet If set to "true" and if the element were not found, will be returned "null" instead of throw an exception, otherwise an exception will be thrown.
		*
		* @return mixed The value that has been stored.
		*
		* @throws InvalidArgumentException If an invalid key were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If no value with the given identifier were found.
		* @throws Exception If the element found has expired.
		* @throws Exception If the element is not stored properly and some information are missing.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		* @throws Exception If an error occurs while parsing the JSON representation of the serialised value.
		* @throws Exception If an error occurs while checking for the file existence.
		* @throws Exception If an error occurs while reading the file content.
		*/
		public function pull(string $key, bool $quiet = false){
			if ( $key === NULL || $key === '' ){
				throw new \InvalidArgumentException('Key cannot be an empty string.');
			}
			$key = $this->createKey($key);
			$verbose = $this->getVerbose();
			try{
				$this->init();
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				throw new \Exception('An error occurred while initialising the storage.', NULL, $ex);
			}
			switch ( $this->getStrategy() ){
				case self::STRATEGY_SHARED:{
					if ( isset(self::$staticStorage[$key['namespace']][$key['key']]) === true ){
						if ( isset(self::$staticStorage[$key['namespace']][$key['key']]['value']) === false || isset(self::$staticStorage[$key['namespace']][$key['key']]['expire']) === false || is_int(self::$staticStorage[$key['namespace']][$key['key']]['expire']) === false ){
							if ( $quiet !== true ){
								throw new \Exception('Malformed element.');
							}
							return NULL;
						}
						if ( self::$staticStorage[$key['namespace']][$key['key']]['expire'] > 0 && self::$staticStorage[$key['namespace']][$key['key']]['expire'] < time() ){
							unset(self::$staticStorage[$key['namespace']][$key['key']]);
							if ( $quiet !== true ){
								throw new \Exception('Element has expired.');
							}
							return NULL;
						}
						return self::$staticStorage[$key['namespace']][$key['key']]['value'];
					}
					if ( $quiet !== true ){
						throw new \Exception('No such element found.');
					}
					return NULL;
				}break;
				case self::STRATEGY_SESSION:{
					if ( isset($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]) === true ){
						if ( isset($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['value']) === false || isset($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['expire']) === false || is_int($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['expire']) === false ){
							if ( $quiet !== true ){
								throw new \Exception('Malformed element.');
							}
							return NULL;
						}
						if ( $_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['expire'] > 0 && $_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['expire'] < time() ){
							unset($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]);
							if ( $quiet !== true ){
								throw new \Exception('Element has expired.');
							}
							return NULL;
						}
						return $_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['value'];
					}
					if ( $quiet !== true ){
						throw new \Exception('No such element found.');
					}
					return NULL;
				}break;
				case self::STRATEGY_REDIS:{
					try{
						$data = $this->redisConnection->get($key['merged']);
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred in Redis transaction.', NULL, $ex);
					}
					if ( $data === false ){
						if ( $quiet !== true ){
							throw new \Exception('No such element found.');
						}
						return NULL;
					}
					$data = json_decode($data, true);
					if ( $data === NULL ){
						throw new \Exception('An error occurred while parsing the serialised data.');
					}
					return $data;
				}break;
				case self::STRATEGY_MEMCACHED:{
					try{
						$data = $this->memcachedConnection->get($key['merged']);
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred in Memcached transaction.', NULL, $ex);
					}
					if ( $data === false ){
						if ( $quiet !== true ){
							throw new \Exception('No such element found.');
						}
						return NULL;
					}
					return $data;
				}break;
				case self::STRATEGY_SQLITE3:{
					try{
						$statement = $this->sqliteConnection->prepare('SELECT value FROM cache_storage WHERE namespace = :namespace AND key = :key AND ( expire = NULL OR expire >= DATETIME("now") ) LIMIT 1;');
						$statement->bindValue('namespace', $key['namespace'], \SQLITE3_TEXT);
						$statement->bindValue('key', $key['key'], \SQLITE3_TEXT);
						$results = $statement->execute();
						while ( $row = $results->fetchArray() ){
							$data = isset($row['value']) === true && $row['value'] !== '' && is_string($row['value']) === true ? json_decode($row['value'], true) : NULL;
							if ( $data === NULL ){
								if ( $quiet !== true ){
									throw new \Exception('An error occurred while parsing the serialised data.');
								}
								break;
							}
							return $data;
						}
						if ( $quiet !== true ){
							throw new \Exception('No such element found.');
						}
						return NULL;
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred during the transaction with SQLite3.', NULL, $ex);
					}
				}break;
				case self::STRATEGY_FILE:{
					$path = $this->storagePath . '/' . $key['namespace'] . '/' . $key['key'] . '.cache';
					if ( file_exists($path) === false ){
						throw new \Exception('No such element found.');
					}
					$data = file_get_contents($path);
					if ( $data === false ){
						throw new \Exception('An error occurred while reading the file content.');
					}
					$data = json_decode($data, true);
					if ( $data === NULL ){
						throw new \Exception('An error occurred while parsing the serialised data.');
					}
					return $data;
				}break;
				default:{
					if ( isset($this->storage[$key['namespace']][$key['key']]) === true ){
						if ( isset($this->storage[$key['namespace']][$key['key']]['value']) === false || isset($this->storage[$key['namespace']][$key['key']]['expire']) === false || is_int($this->storage[$key['namespace']][$key['key']]['expire']) === false ){
							if ( $quiet !== true ){
								throw new \Exception('Malformed element.');
							}
							return NULL;
						}
						if ( $this->storage[$key['namespace']][$key['key']]['expire'] > 0 && $this->storage[$key['namespace']][$key['key']]['expire'] < time() ){
							unset($this->storage[$key['namespace']][$key['key']]);
							if ( $quiet !== true ){
								throw new \Exception('Element has expired.');
							}
							return NULL;
						}
						return $this->storage[$key['namespace']][$key['key']]['value'];
					}
					if ( $quiet !== true ){
						throw new \Exception('No such element found.');
					}
					return NULL;
				}break;
			}
		}
		
		/**
		* Returns a value that has been stored within the cache, this method is an alias of the "pull" method.
		*
		* @param string $key A string representin the identifier of the value that has been stored.
		* @param bool $quiet If set to "true" and if the element were not found, will be returned "null" instead of throw an exception, otherwise an exception will be thrown.
		*
		* @return mixed The value that has been stored.
		*
		* @throws InvalidArgumentException If an invalid key were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If no value with the given identifier were found.
		* @throws Exception If the element found has expired.
		* @throws Exception If the element is not stored properly and some information are missing.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		* @throws Exception If an error occurs while parsing the JSON representation of the serialised value.
		* @throws Exception If an error occurs while checking for the file existence.
		* @throws Exception If an error occurs while reading the file content.
		*/
		public function get(string $key, bool $quiet = false){
			return $this->pull($key, $quiet);
		}
		
		/**
		* Returns multiple values that have been stored within the cache.
		*
		* @param array $keys A sequential array of strings containing the keys of the elements that will be returned.
		* @param bool $quiet If set to "true" and if the element were not found, will be returned "NULL" instead of throw an exception, otherwise an exception will be thrown.
		* @param bool $omitNotFound If set to "true", all elements not found will not be included in the returned object, otherwise they will be included with "NULL" as value, note that this option takes sense in quiet mode only.
		*
		* @return array An associative array containing as key the entry key and as value its value or "NULL" if the element was not found.
		*
		* @throws InvalidArgumentException If an invalid array were given.
		* @throws InvalidArgumentException If an invalid key within the array were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If no value with the given identifier were found.
		* @throws Exception If the element found has expired.
		* @throws Exception If the element is not stored properly and some information are missing.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		* @throws Exception If an error occurs while parsing the JSON representation of the serialised value.
		* @throws Exception If an error occurs while checking for the file existence.
		* @throws Exception If an error occurs while reading the file content.
		*/
		public function pullMulti(array $keys, bool $quiet = false, bool $omitNotFound = false): array{
			if ( $keys === NULL ){
				throw new \InvalidArgumentException('Invalid keys.');
			}
			if ( empty($keys) === true ){
				return array();
			}
			foreach ( $keys as $key => $value ){
				if ( $value === NULL || $value === '' || is_string($value) === false ){
					throw new \InvalidArgumentException('Invalid key found.');
				}
			}
			$data = array();
			if ( $omitNotFound === true ){
				foreach ( $keys as $key => $value ){
					$data[$value] = $this->pull($value, $quiet);
					if ( $data[$value] === NULL ){
						unset($data[$value]);
					}
				}
				return $data;
			}
			foreach ( $keys as $key => $value ){
				$data[$value] = $this->pull($value, $quiet);
			}
			return $data;
		}
		
		/**
		* Returns multiple values that have been stored within the cache, this method is an alias of the "pullMulti" method.
		*
		* @param array $keys A sequential array of strings containing the keys of the elements that will be returned.
		* @param bool $quiet If set to "true" and if the element were not found, will be returned "NULL" instead of throw an exception, otherwise an exception will be thrown.
		* @param bool $omitNotFound If set to "true", all elements not found will not be included in the returned object, otherwise they will be included with "NULL" as value, note that this option takes sense in quiet mode only.
		*
		* @return array An associative array containing as key the entry key and as value its value or "NULL" if the element was not found.
		*
		* @throws InvalidArgumentException If an invalid array were given.
		* @throws InvalidArgumentException If an invalid key within the array were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If no value with the given identifier were found.
		* @throws Exception If the element found has expired.
		* @throws Exception If the element is not stored properly and some information are missing.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		* @throws Exception If an error occurs while parsing the JSON representation of the serialised value.
		* @throws Exception If an error occurs while checking for the file existence.
		* @throws Exception If an error occurs while reading the file content.
		*/
		public function getMulti(array $keys, bool $quiet = false, bool $omitNotFound = false): array{
			return $this->pullMulti($keys, $quiet, $omitNotFound);
		}
		
		/**
		* Checks if exists an element with the given identifier within the cache..
		*
		* @param string $key A string representin the identifier of the value that will be looked for.
		*
		* @return bool If the element is found will be returned "true", otherwise "false".
		*
		* @throws InvalidArgumentException If an invalid key were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		* @throws Exception If an error occurs while checking for the file existence.
		*/
		public function has(string $key): bool{
			if ( $key === NULL || $key === '' ){
				throw new \InvalidArgumentException('Key cannot be an empty string.');
			}
			$key = $this->createKey($key);
			$verbose = $this->getVerbose();
			try{
				$this->init();
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				throw new \Exception('An error occurred while initialising the storage.', NULL, $ex);
			}
			switch ( $this->getStrategy() ){
				case self::STRATEGY_SHARED:{
					if ( isset(self::$staticStorage[$key['namespace']][$key['key']]) === true ){
						if ( isset(self::$staticStorage[$key['namespace']][$key['key']]['value']) === false || isset(self::$staticStorage[$key['namespace']][$key['key']]['expire']) === false || is_int(self::$staticStorage[$key['namespace']][$key['key']]['expire']) === false ){
							return false;
						}
						if ( self::$staticStorage[$key['namespace']][$key['key']]['expire'] > 0 && self::$staticStorage[$key['namespace']][$key['key']]['expire'] < time() ){
							unset(self::$staticStorage[$key['namespace']][$key['key']]);
							return false;
						}
						return true;
					}
					return false;
				}break;
				case self::STRATEGY_SESSION:{
					if ( isset($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]) === true ){
						if ( isset($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['value']) === false || isset($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['expire']) === false || is_int($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['expire']) === false ){
							return false;
						}
						if ( $_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['expire'] > 0 && $_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['expire'] < time() ){
							unset($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]);
							return false;
						}
						return true;
					}
					return false;
				}break;
				case self::STRATEGY_REDIS:{
					try{
						return $this->redisConnection->exists($key['merged']) === 0 ? false : true;
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred in Redis transaction.', NULL, $ex);
					}
				}break;
				case self::STRATEGY_MEMCACHED:{
					try{
						return $this->memcachedConnection->get($key['merged']) === false ? false : true;
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred in Memcached transaction.', NULL, $ex);
					}
				}break;
				case self::STRATEGY_SQLITE3:{
					try{
						$statement = $this->sqliteConnection->prepare('SELECT date FROM cache_storage WHERE namespace = :namespace AND key = :key AND ( expire = NULL OR expire >= DATETIME("now") ) LIMIT 1;');
						$statement->bindValue('namespace', $key['namespace'], \SQLITE3_TEXT);
						$statement->bindValue('key', $key['key'], \SQLITE3_TEXT);
						$results = $statement->execute();
						while ( $row = $results->fetchArray() ){
							return true;
						}
						return false;
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred during the transaction with SQLite3.', NULL, $ex);
					}
				}break;
				case self::STRATEGY_FILE:{
					return file_exists($this->storagePath . '/' . $key['namespace'] . '/' . $key['key'] . '.cache') === false ? false : true;
				}break;
				default:{
					if ( isset($this->storage[$key['namespace']][$key['key']]) === true ){
						if ( isset($this->storage[$key['namespace']][$key['key']]['value']) === false || isset($this->storage[$key['namespace']][$key['key']]['expire']) === false || is_int($this->storage[$key['namespace']][$key['key']]['expire']) === false ){
							return false;
						}
						if ( $this->storage[$key['namespace']][$key['key']]['expire'] > 0 && $this->storage[$key['namespace']][$key['key']]['expire'] < time() ){
							unset($this->storage[$key['namespace']][$key['key']]);
							return false;
						}
						return true;
					}
					return false;
				}break;
			}
		}
		
		/**
		* Checks if multiple elements exist within the cache.
		*
		* @param array keys A sequential array of strings containing the keys of the elements that will be looked for.
		*
		* @return array An associative array that has as key the element key and as value "true" if it exists, otherwise "false".
		*
		* @throws InvalidArgumentException If an array were given.
		* @throws InvalidArgumentException If an invalid key within the array were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		* @throws Exception If an error occurs while checking for the file existence.
		*/
		public function hasMulti(array $keys): array{
			if ( $keys === NULL || $keys === '' ){
				throw new \InvalidArgumentException('Invalid keys.');
			}
			if ( empty($keys) === true ){
				return array();
			}
			foreach ( $keys as $key => $value ){
				if ( $value === NULL || $value === '' || is_string($value) === true ){
					throw new \InvalidArgumentException('Invalid key found.');
				}
			}
			$data = array();
			foreach ( $keys as $key => $value ){
				$data[$value] = $this->has($value) === true ? true : false;
			}
			return $data;
		}
		
		/**
		* Checks if all the given elements exist within the cache.
		*
		* @param array keys A sequential array of strings containing the keys of the elements that will be looked for.
		*
		* @return bool Even if only an element doesn't exist will be returned "false", otherwise "true".
		*
		* @throws InvalidArgumentException If an array were given.
		* @throws InvalidArgumentException If an invalid key within the array were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		* @throws Exception If an error occurs while checking for the file existence.
		*/
		public function hasAll(array $keys): bool{
			if ( $keys === NULL || $keys === '' ){
				throw new \InvalidArgumentException('Invalid keys.');
			}
			if ( empty($keys) === true ){
				return array();
			}
			foreach ( $keys as $key => $value ){
				if ( $value === NULL || $value === '' || is_string($value) === true ){
					throw new \InvalidArgumentException('Invalid key found.');
				}
			}
			foreach ( $keys as $key => $value ){
				if ( $this->has($value) === false ){
					return false;
				}
			}
			return true;
		}
		
		/**
		* Increments the value of a given key by a given value, this method is chainable.
		*
		* @param string $key A string containing the key of the element that shall be incremented.
		* @param float $value A floating point number representing the increment delta (positive or negative), note that Memcached doens't support floating point deltas, the default value is 1.
		*
		* @throws InvalidArgumentException If an invalid key were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		*/
		public function increment(string $key, float $value = 1): PHPTinyCacher{
			if ( $key === NULL || $key === '' ){
				throw new \InvalidArgumentException('Key cannot be an empty string.');
			}
			$value = $value === NULL ? 1 : $value;
			if ( $value === 0 ){
				return $this;
			}
			$key = $this->createKey($key);
			$verbose = $this->getVerbose();
			try{
				$this->init();
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				throw new \Exception('An error occurred while initialising the storage.', NULL, $ex);
			}
			switch ( $this->getStrategy() ){
				case self::STRATEGY_SHARED:{
					if ( isset(self::$staticStorage[$key['namespace']][$key['key']]['value']) === true && ( is_int(self::$staticStorage[$key['namespace']][$key['key']]['value']) === true || is_float(self::$staticStorage[$key['namespace']][$key['key']]['value']) === true ) ){
						self::$staticStorage[$key['namespace']][$key['key']]['value'] += $value;
					}
					return $this;
				}break;
				case self::STRATEGY_SESSION:{
					if ( isset($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['value']) === true && ( is_int($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['value']) === true || is_float($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['value']) === true ) ){
						$_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]['value'] += $value;
					}
					return $this;
				}break;
				case self::STRATEGY_REDIS:{
					try{
						$this->redisConnection->incrbyfloat($key['merged'], $value);
						return $this;
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred in Redis transaction.', NULL, $ex);
					}
				}break;
				case self::STRATEGY_MEMCACHED:{
					try{
						$buffer = $value < 0 ? ( floor($value) + 1 ) : floor($value);
						if ( $verbose === true && $buffer !== $value ){
							echo 'php-tiny-cacher: Note that Memcached doesn\'t support floating point numbers as increment delta, your value will be converted into an integer one (' . value . ' => ' . buffer . ').' . PHP_EOL;
						}
						if ( $value < 0 ){
							$this->memcachedConnection->decrement($key['merged'], -$buffer);
							return $this;
						}
						$this->memcachedConnection->increment($key['merged'], $buffer);
						return $this;
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred in Memcached transaction.', NULL, $ex);
					}
				}break;
				case self::STRATEGY_SQLITE3:{
					try{
						$statement = $this->sqliteConnection->prepare('UPDATE cache_storage SET value = value + :value WHERE namespace = :namespace AND key = :key AND numeric = 1;');
						$statement->bindValue('value', $value, \SQLITE3_FLOAT);
						$statement->bindValue('namespace', $key['namespace'], \SQLITE3_TEXT);
						$statement->bindValue('key', $key['key'], \SQLITE3_TEXT);
						$result = $statement->execute();
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred during the transaction with SQLite3.', NULL, $ex);
					}
					if ( $result === false ){
						throw new \Exception('An error occurred during the transaction with SQLite3.');
					}
					return $this;
				}break;
				case self::STRATEGY_FILE:{
					if ( $verbose === true ){
						echo 'php-tiny-cacher: Note that increment and decrement are not supported on files because of performance degradation, consider using an in-memory database, such as Redis, or SQLite instead.' . PHP_EOL;
					}
					return $this;
				}break;
				default:{
					if ( isset($this->storage[$key['namespace']][$key['key']]['value']) === true && ( is_int($this->storage[$key['namespace']][$key['key']]['value']) === true || is_float($this->storage[$key['namespace']][$key['key']]['value']) === true ) ){
						$this->storage[$key['namespace']][$key['key']]['value'] += $value;
					}
					return $this;
				}break;
			}
		}
		
		/**
		* Increments the value of multiple elements by a given value, this method is chainable.
		*
		* @param array $keys A sequential array of strings containing the keys of the elements that will be incremented.
		* @param float $value A floating point number representing the increment delta (positive or negative), note that Memcached doens't support floating point deltas, the default value is 1.
		*
		* @throws InvalidArgumentException If an array were given.
		* @throws InvalidArgumentException If an invalid key within the array were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		*/
		public function incrementMulti(array $keys, float $value = 1): PHPTinyCacher{
			if ( $keys === NULL ){
				throw new \InvalidArgumentException('Invalid keys.');
			}
			if ( empty($keys) === true ){
				return $this;
			}
			foreach ( $keys as $key => $value ){
				if ( $value === NULL || $value === '' || is_string($value) === true ){
					throw new \InvalidArgumentException('Invalid key found.');
				}
			}
			foreach ( $keys as $key => $value ){
				$this->increment($value, $value);
			}
			return $this;
		}
		
		/**
		* Decrements the value of a given key by a given value, this method is chainable.
		*
		* @param string $key A string containing the key of the element that shall be decremented.
		* @param float $value A floating point number representing the increment delta (positive or negative), note that Memcached doens't support floating point deltas, the default value is -1.
		*
		* @throws InvalidArgumentException If an invalid key were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		*/
		public function decrement(string $key, float $value = -1): PHPTinyCacher{
			$this->increment($key, ( $value === NULL ? -1 : -$value ));
			return $this;
		}
		
		/**
		* Decrements the value of multiple elements by a given value, this method is chainable.
		*
		* @param array $keys A sequential array of strings containing the keys of the elements that will be decremented.
		* @param float $value A floating point number representing the increment delta (positive or negative), note that Memcached doens't support floating point deltas., the default value is -1.
		*
		* @throws InvalidArgumentException If an array were given.
		* @throws InvalidArgumentException If an invalid key within the array were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		*/
		public function decrementMulti(array $keys, float $value = -1){
			$this->incrementMulti($keys, ( $value === NULL ? -1 : -$value ));
			return $this;
		}
		
		/**
		* Removes a given entry from the cache, this method is chainable.
		*
		* @param string key A string representin the identifier of the value that will be removed.
		*
		* @throws InvalidArgumentException If an invalid key were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		* @throws Exception If an error occurs while removing the file where the data is stored in, for more information enable verbose mode.
		*/
		public function remove(string $key): PHPTinyCacher{
			if ( $key === NULL || $key === '' ){
				throw new \InvalidArgumentException('Key cannot be an empty string.');
			}
			$key = $this->createKey($key);
			$verbose = $this->getVerbose();
			try{
				$this->init();
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				throw new \Exception('An error occurred while initialising the storage.', NULL, $ex);
			}
			switch ( $this->getStrategy() ){
				case self::STRATEGY_SHARED:{
					unset(self::$staticStorage[$key['namespace']][$key['key']]);
				}break;
				case self::STRATEGY_SESSION:{
					unset($_SESSION['php-tiny-cacher'][$key['namespace']][$key['key']]);
				}break;
				case self::STRATEGY_REDIS:{
					try{
						$this->redisConnection->delete($key['merged']);
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred in Redis transaction.', NULL, $ex);
					}
				}break;
				case self::STRATEGY_MEMCACHED:{
					try{
						$result = $this->memcachedConnection->delete($key['merged']);
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred in Memcached transaction.', NULL, $ex);
					}
					if ( $result === false ){
						throw new \Exception('An error occurred in Memcached transaction.');
					}
				}break;
				case self::STRATEGY_SQLITE3:{
					try{
						$statement = $this->sqliteConnection->prepare('DELETE FROM cache_storage WHERE namespace = :namespace AND key = :key;');
						$statement->bindValue('namespace', $key['namespace'], \SQLITE3_TEXT);
						$statement->bindValue('key', $key['key'], \SQLITE3_TEXT);
						$result = $statement->execute();
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred during the transaction with SQLite3.', NULL, $ex);
					}
					if ( $result === false ){
						throw new \Exception('An error occurred during the transaction with SQLite3.');
					}
				}break;
				case self::STRATEGY_FILE:{
					if ( unlink($this->storagePath . '/' . $key['namespace'] . '/' . $key['key'] . '.cache') === false ){
						throw new \Exception('An error occurred while removing the file.');
					}
				}break;
				default:{
					unset($this->storage[$key['namespace']][$key['key']]);
				}break;
			}
			return $this;
		}
		
		/**
		* Removes multiple elements from the cache.
		*
		* @param array $keys A sequential array of strings containing the keys of the elements that will be removed.
		*
		* @throws InvalidArgumentException If an array were given.
		* @throws InvalidArgumentException If an invalid key within the array were given.
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		* @throws Exception If an error occurs while removing the file where the data is stored in, for more information enable verbose mode.
		*/
		public function removeMulti(array $keys): PHPTinyCacher{
			if ( $keys === NULL ){
				throw new \InvalidArgumentException('Invalid keys.');
			}
			if ( empty($keys) === true ){
				return $this;
			}
			foreach ( $keys as $key => $value ){
				if ( $value === NULL || $value === '' || is_string($value) === true ){
					throw new \InvalidArgumentException('Invalid key found.');
				}
			}
			foreach ( $keys as $key => $value ){
				$this->remove($value);
			}
			return $this;
		}
		
		/**
		* Drops all entries from the cache.
		*
		* @param bool $all If set to "true", all entries created by this class will be removed from cache, otherwise, dy default, only elements in the namespace that has been set will be removed.
		*
		* @throws Exception If an error occurs during storage initialisation.
		* @throws Exception If an error occurs in Redis transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in Memcached transaction, for more information enable verbose mode.
		* @throws Exception If an error occurs in transaction with the SQLite3 database, for more information enable verbose mode.
		* @throws Exception If an error occurs while removing the directory where the cache is stored in.
		*/
		public function invalidate(bool $all = false): PHPTinyCacher{
			$key = $this->createKey(NULL);
			$verbose = $this->getVerbose();
			try{
				$this->init();
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				throw new \Exception('An error occurred while initialising the storage.', NULL, $ex);
			}
			switch ( $this->getStrategy() ){
				case self::STRATEGY_SHARED:{
					if ( $all === true ){
						self::$staticStorage = array();
						return $this;
					}
					self::$staticStorage[$key['namespace']] = array();
				}break;
				case self::STRATEGY_SESSION:{
					if ( $all === true ){
						$_SESSION['php-tiny-cacher'] = array();
						return $this;
					}
					$_SESSION['php-tiny-cacher'][$key['namespace']] = array();
				}break;
				case self::STRATEGY_REDIS:{
					try{
						$elements = $this->redisConnection->keys(( $all === true ? 'php-tiny-cacher:*' : 'php-tiny-cacher:' . $key['namespace'] . ':*' ));
						if ( isset($elements[0]) === true ){
							$this->redisConnection->delete($elements);
						}
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred in Redis transaction.', NULL, $ex);
					}
				}break;
				case self::STRATEGY_MEMCACHED:{
					try{
						$elements = $this->memcachedConnection->getAllKeys();
						if ( isset($elements[0]) === true ){
							$pattern = $all === true ? 'php-tiny-cacher:' : ( 'php-tiny-cacher:' . $key['namespace'] . ':' );
							foreach ( $elements as $key => $value ){
								if ( strpos($value, $pattern) !== 0 ){
									unset($elements[$key]);
								}
							}
							$elements = $this->memcachedConnection->deleteMulti($elements);
						}
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred in Memcached transaction.', NULL, $ex);
					}
					if ( $elements === false ){
						throw new \Exception('An error occurred in Memcached transaction.');
					}
					foreach ( $elements as $key => $value ){
						if ( $value === false ){
							throw new \Exception('An error occurred in Memcached transaction.');
						}
					}
				}break;
				case self::STRATEGY_SQLITE3:{
					if ( $all === true ){
						try{
							$result = $this->sqliteConnection->exec('DELETE FROM cache_storage;');
						}catch(\Exception $ex){
							if ( $verbose === true ){
								echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
							}
							throw new \Exception('An error occurred during the transaction with SQLite3.', NULL, $ex);
						}
						if ( $result === false ){
							throw new \Exception('An error occurred during the transaction with SQLite3.');
						}
					}
					try{
						$statement = $this->sqliteConnection->prepare('DELETE FROM cache_storage WHERE namespace = :namespace;');
						$statement->bindValue('namespace', $key['namespace'], \SQLITE3_TEXT);
						$result = $statement->execute();
					}catch(\Exception $ex){
						if ( $verbose === true ){
							echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
						}
						throw new \Exception('An error occurred during the transaction with SQLite3.', NULL, $ex);
					}
					if ( $result === false ){
						throw new \Exception('An error occurred during the transaction with SQLite3.');
					}
				}break;
				case self::STRATEGY_FILE:{
					$path = $all === true ? ( $this->storagePath ) : ( $this->storagePath . '/' . $key['namespace'] );
					$iterator = new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS);
					$elements = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);
					foreach ( $elements as $key => $value ){
						if ( $value->isDir() === true ){
							rmdir($value->getRealPath());
							continue;
						}
						unlink($value->getRealPath());
					}
				}break;
				default:{
					if ( $all === true ){
						$this->storage = array();
						return $this;
					}
					$this->storage[$key['namespace']] = array();
				}break;
			}
			return $this;
		}
		
		/**
		* Establishes a connection with Redis, this method is chainable.
		*
		* @param string $contactPoint A string containing the hostname or the UNIX socket path where the Redis server is listening to.
		* @param int $port An integer number greater than zero and lower or equal than 65535, by default 6379 is used, this parameter is ignored by the client when using an UNIX socket as contact point.
		* @param int $index An integer number greater or equal than zero and lower or equal than 16 representing the database number.
		* @param string $password A string containing an optional password, note that the password will be trasmitted to the server as plain text.
		*
		* @throws BadMethodCallException If the driver for Redis has not been installed.
		* @throws Exception If an error occurrs during connection with Redis.
		*/
		public function connectToRedis(string $contactPoint = NULL, int $port = 6379, int $index = 0, string $password = NULL): PHPTinyCacher{
			if ( in_array('redis', get_loaded_extensions(false)) === false || class_exists('Redis') === false ){
				throw new \BadMethodCallException('The Redis driver has not been installed, run "pecl install redis" first.');
			}
			try{
				if ( $contactPoint === NULL || $contactPoint === '' ){
					$contactPoint = '127.0.0.1';
				}
				if ( $port <= 0 || $port > 65535 ){
					$contactPoint = 6379;
				}
				if ( $index < 0 ){
					$index = 0;
				}
				$verbose = $this->getVerbose();
				$this->ready = false;
				$redisConnection = new \Redis();
				$result = $redisConnection->connect($contactPoint, $port);
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				$this->ready = true;
				throw new \Exception('Unable to connect to Redis.', NULL, $ex);
			}
			if ( $result === false ){
				$this->ready = true;
				throw new \Exception('Unable to connect to Redis.');
			}
			if ( $password !== NULL && $password !== '' ){
				try{
					$result = $redisConnection->auth($password);
				}catch(\Exception $ex){
					if ( $verbose === true ){
						echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
					}
					$this->ready = true;
					throw new \Exception('Unable to connect to Redis.', NULL, $ex);
				}
			}
			if ( $result === false ){
				$this->ready = true;
				throw new \Exception('Unable to connect to Redis.');
			}
			try{
				$result = $redisConnection->select($index);
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				$this->ready = true;
				throw new \Exception('Unable to connect to Redis.', NULL, $ex);
			}
			if ( $result === false ){
				$this->ready = true;
				throw new \Exception('Unable to connect to Redis.');
			}
			$this->redisConnection = $redisConnection;
			$this->ready = true;
			return $this;
		}
		
		/**
		* Checks if a connection with Redis has been established or not.
		*
		* @param bool $probe If set to "true", the connection will be probed by sending a sample command to the server and then waiting for its response, by default no probe is made.
		*
		* @return bool If the connection is up will be returned "true", otherwise "false".
		*/
		public function redisConnected(bool $probe = false): bool{
			try{
				$verbose = $this->getVerbose();
				return $this->redisConnection === NULL || !( $this->redisConnection instanceof \Redis ) ? false : ( $probe === true && $this->redisConnection->ping() !== '+PONG' ? false : true );
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				return false;
			}
		}
		
		/**
		* Establishes a connection with Memcached, this method is chainable.
		*
		* @param array $servers An array that contains contains the connection infromation for each server, if not set, a connection attempt to a local Memcached server will be made, more information on configuration params here: http://php.net/manual/en/memcached.addservers.php
		*
		* @throws BadMethodCallException If the driver for Memcached has not been installed.
		* @throws Exception If an error occurrs during connection with Memcached.
		*/
		public function connectToMemcached(array $servers = NULL): PHPTinyCacher{
			if ( in_array('memcached', get_loaded_extensions(false)) === false || class_exists('Memcached') === false ){
				throw new \BadMethodCallException('The Memcached driver has not been installed, run "pecl install memcached" first.');
			}
			try{
				$this->ready = false;
				$verbose = $this->getVerbose();
				$memcachedConnection = new \Memcached();
				if ( $servers !== NULL && isset($servers[0]) === true ){
					$memcachedConnection->addServers($servers);
				}
				$memcachedConnection->setOption(\Memcached::OPT_BINARY_PROTOCOL, false);
				$this->memcachedConnection = $memcachedConnection;
				$this->ready = true;
				return $this;
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				$this->ready = true;
				throw new \Exception('Unable to connect to Memcached.', NULL, $ex);
			}
		}
		
		/**
		* Checks if a connection with Memcached has been established or not.
		*
		* @param bool $probe If set to "true", the connection will be probed by sending a sample command to the server and then waiting for its response, by default no probe is made.
		*
		* @return bool If the connection is up will be returned "true", otherwise "false".
		*/
		public function memcachedConnected(bool $probe = false): bool{
			try{
				$verbose = $this->getVerbose();
				return $this->memcachedConnection === NULL || ! ( $this->memcachedConnection instanceof \Memcached ) ? false : ( $probe === true && is_array($this->memcachedConnection->getVersion()) === false ? false : true );
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				return false;
			}
		}
		
		/**
		* Establishes a connection with a SQLite3 database, this method is chainable.
		*
		* @param string $path A string containing the location of the database file, use the keyword ":memory:" to create an in-memory database.
		* @param int $mode An integer number greater than zero representing the connection mode, you can use one or more of the constants shipped with the SQLite3 extension, more information can be found here: http://php.net/manual/it/sqlite3.constants.php
		*
		* @throws InvalidArgumentException If an invalid path were given.
		* @throws Exception If the driver for SQLite3 has not been installed.
		* @throws Exception If an error occurrs during connection with the SQLite3 database.
		* @throws Exception If an error occurrs during database initialisation.
		*/
		public function connectToSQLite(string $path, int $mode = NULL): PHPTinyCacher{
			if ( $path === NULL || $path === '' ){
				throw new \InvalidArgumentException('Invalid path.');
			}
			if ( in_array('sqlite3', get_loaded_extensions(false)) === false || class_exists('SQLite3') === false ){
				throw new \BadMethodCallException('The SQLite3 driver has not been installed.');
			}
			$this->ready = false;
			$verbose = $this->getVerbose();
			$result = false;
			try{
				$sqliteConnection = new \SQLite3($path, ( $mode === NULL ? ( \SQLITE3_OPEN_READWRITE | \SQLITE3_OPEN_CREATE ) : $mode ));
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				$this->ready = true;
				throw new \Exception('An error occurred while trying to connect with SQLite database.', NULL, $ex);
			}
			try{
				$result = $sqliteConnection->exec('CREATE TABLE IF NOT EXISTS cache_storage (namespace TEXT, key TEXT, value TEXT, numeric INTEGER, date DATETIME, expire DATETIME, PRIMARY KEY (namespace, key));');
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				$this->ready = true;
				throw new \Exception('An error occurred during database initialisation.', NULL, $ex);
			}
			if ( $result === false ){
				$this->ready = true;
				throw new \Exception('An error occurred during database initialisation.');
			}
			$this->sqliteConnection = $sqliteConnection;
			$this->ready = true;
			return $this;
		}
		
		/**
		* Checks if a connection with a SQLite3 database has been established or not, if the connection is going to be probed.
		*
		* @param bool $probe If set to "true", the connection will be probed by sending a sample command to the server and then waiting for its response, by default no probe is made.
		*
		* @return bool If the connection is up will be returned "true", otherwise "false".
		*/
		public function SQLite3Connected(bool $probe = false): bool{
			try{
				if ( $this->sqliteConnection === NULL || ! ( $this->sqliteConnection instanceof \SQLite3 ) ){
					return false;
				}
				if ( $probe === true ){
					$verbose = $this->getVerbose();
					$results = $this->sqliteConnection->query('PRAGMA table_info([cache_storage]);');
					$fields = array('namespace', 'key', 'value', 'numeric', 'date', 'expire');
					$found = 0;
					while ( $row = $results->fetchArray() ){
						if ( isset($row['name']) === true && $row['name'] !== '' && is_string($row['name']) === true && in_array($row['name'], $fields) ){
							$found++;
						}
					}
					return $found === 6 ? true : false;
				}
				return true;
			}catch(\Exception $ex){
				if ( $verbose === true ){
					echo '[php-tiny-cacher] Exception (' . $ex->getCode() . '):' . $ex->getMessage() . PHP_EOL;
				}
				return false;
			}
		}
		
		/**
		* Sets the path to the directory where the cached files will be stored in, this method is chainable.
		*
		* @param string $path A string containing the path to the directory.
		*
		* @throws InvalidArgumentException If an invalid path were given.
		* @throws Exception If an error occurs while creating the directory (if it doesn't exist).
		*/
		public function setStorageDirectory(string $path): PHPTinyCacher{
			if ( $path === NULL || $path === '' ){
				throw new \InvalidArgumentException('Invalid path.');
			}
			$this->ready = false;
			if ( file_exists($path) === false ){
				if ( mkdir($path, 0777, true) === false ){
					$this->ready = true;
					throw new \Exception('Cannot create the directory.');
				}
			}
			$this->storagePath = $path;
			$this->ready = true;
			return $this;
		}
		
		/**
		* Returns if the class is ready to be used or not, for example if is currently connecting to Redis.
		*
		* @return bool If the class is ready will be returned "true", otherwise "false".
		*/
		public function isReady(): bool{
			return $this->ready === true ? true : false;
		}
		
		/**
		* Closes the connection with Redis, this method is chainable.
		*/
		public function closeRedisConnection(): PHPTinyCacher{
			if ( $this->redisConnected() === true ){
				$this->redisConnection->close();
			}
			$this->redisConnection = NULL;
			return $this;
		}
		
		/**
		* Closes the connection with Memcached, this method is chainable.
		*/
		public function closeMemcachedConnection(): PHPTinyCacher{
			if ( $this->memcachedConnected() === true ){
				$this->memcachedConnection->quit();
			}
			$this->memcachedConnection = NULL;
			return $this;
		}
		
		/**
		* Closes the connection with the SQLite3 database, this method is chainable.
		*/
		public function closeSQLite3Connection(): PHPTinyCacher{
			if ( $this->SQLite3Connected() === true ){
				$this->sqliteConnection->close();
			}
			$this->sqliteConnection = NULL;
			return $this;
		}
		
		/**
		* Closes all no more used connections, this method is chainable.
		*
		* @param bool $all If set to "true" it will close all connections, despite current strategy require it, otherwise only not used connections will be closed.
		*/
		public function closeConnections(bool $all = false): PHPTinyCacher{
			$strategy = $this->getStrategy();
			if ( $all === true || $strategy !== self::STRATEGY_REDIS ){
				$this->closeRedisConnection();
			}
			if ( $all === true || $strategy !== self::STRATEGY_MEMCACHED ){
				$this->closeMemcachedConnection();
			}
			if ( $all === true || $strategy !== self::STRATEGY_SQLITE3 ){
				$this->SQLite3Connected();
			}
			return $this;
		}
	}
}