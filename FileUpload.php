<?php
	/** @file
	 *	@author: okaresz <okaresz@aol.com>
	 *
	 *	https://github.com/okaresz/FileUpload
	 *
	 *	@copyright MIT Licence*/

	namespace HtS\Utility {

	if( !defined('DIRECTORY_SEPARATOR') )
		{ define( 'DIRECTORY_SEPARATOR', '/' ); }

	/** Class to handle classic PHP file uploads.
	 *	Basic Usage:
	 *	~~~~~~~~~~~~~~~~~~~{.php}
	 *	(new FileUpload('fileFieldName'))->save('to/dir/');
	 *	~~~~~~~~~~~~~~~~~~~
	 *	For configuration, you can use the config argument of the constructor, or the similarly named chainable configuration methods.
	 *	@par Configuration
	 *	- **saveName** <i>(string|callable)</i>: Save the file with this name. The original extension will be appended.\n
	 *		Callable signature: `function( $fileObj ): string`.\n
	 *		If a string is prefixed with a backslash (eg.: `"\md5"`), it is treated as a callable function.\n
	 *		Default is to save the file with the original name and extension.
	 *	- **saveName:full** <i>(string|callable)</i>: Same as *saveName*, but the name is treated as a full file name, and no extension is appended.
	 *	- **saveDir** <i>(string|callable)</i>: Save the file to this destination directory. Trailing directory separator is optional.\n
	 *		Callable signature: `function( $fileObj ): string`.\n
	 *		If a string is prefixed with a backslash (eg.: `"\md5"`), it is treated as a callable function.\n
	 *		There is no default, this must be given.
	 *	- **sizeLimit** <i>(string|array)</i>: Size limits for the file. A *limit* can be integer bytes or a string of a float + metric suffix.
	 *		eg.: "3.4MB" or "500k". The min and max limits can be given as:
	 *		- a single value: a single *limit*
	 *		- in an array: `array( 500, "2M" ); array( NULL, "4.5g" ); array( "20k", NULL )`, `NULL` meaning no limit.
	 *		- with a string: a dash ("-") separating the min - max *limits*: `"50k-20Mb"`, `"- 1500KB"`, `"20k-"`
	 *		.
	 *		Default limits: `array( 1, NULL );`
	 *	- **allowMime** <i>(string|array)</i>: an array or a comma-separated list of MIME types with optional charset.\n
	 *		The items are treated as regular expressions (passed to preg match like `"#$mime#i"`).
	 *		If a mime is prefixed with an exclamation mark, that mime will be on the deny list.\n
	 *		These are all valid: `"application"`, `"/x-executable"`, `"/vnd.+;charset=utf-?8"`, `"image/"`.
	 *	- **allowExt** <i>(string|array)</i>: an array or a comma-separated list of extensions (without leading dot).
	 *		The list is treated similarly as *allowMime*.
	 *	- **validator** <i>(callable)</i>: Callable signature: `function( $fileObj, &$message ): bool`\n
	 *		A validator callback. A validator is passed the FileUpload object and an optional `$message` variable,
	 *		and must return true or false. If false is returned, the file check is terminated and the file won't be saved.\n
	 *		A custom error message can be provided with the `$message` argument.
	 *	- **checkIsUploaded** <i>(boolean)</i>: If true, the file is checked if it's a valid PHP-uploaded file. (`is_uploaded_file()`). Default is true.
	 *	- **overwrite** <i>(boolean)</i>: If true, destination file is overwritten if already exists. Default is false.
	 *	- **noThrow** <i>(boolean)</i>: If true, no exceptions are thrown, errors can be checked and retrieved with getError() and getErrorMessage().
	 *		Default is true.
	 *	.
	 *	For the configs allowing multiple items ( *validator*, *allowMime*, *allowExt* ), an additional accessor method is available,
	 *	prefixed with *add*: `addAllowMime()`, `addAllowExt()`, `addValidator()`, appending a single item to the existing list.
	 *
	 *	@par Actions
	 *	You may use the following actions:
	 *	- config(): Set all config as an array.
	 *	- check(): Check the file.
	 *	- save(): Move the file to destination.
	 *
	 *	If you would like to handle an arbitrary file (not in $_FILES), pass a $_FILE - like array to the constructor.
	 *	In this case *checkIsUploaded* is automatically set to false.
	 *
	 *	@par Examples, usage
	 *	~~~~~~~~~~~~~~~~~~~{.php}
	 *	$success = (new FileUpload('fileFieldName'))->saveNameFull('\md5')->save('to/dir/');
	 *	$fu = new FileUpload('fileFieldName', array(
	 *		'saveDir' => 'destination/dir',
	 *		'saveName:full' => function($file) { return 'custom.name'; } ) );
	 *	try { $fu->noThrow(false)->addValidator( array($this,'validateFile') )->save(); }
	 *	catch( \Exception $e )
	 *	{
	 *		log($e);
	 *		echo $fu->getErrorMessage();
	 *	}
	 *	~~~~~~~~~~~~~~~~~~~
	 **/
	class FileUpload
	{
		/// Default configuration.
		protected static $defaults = array(
			'saveName' => NULL,
			'saveName:full' => NULL,
			'saveDir' => NULL,
			'sizeLimit' => array( 1, NULL ),
			'allowMime' => array( 'allow'=>array(), 'deny'=>array() ),
			'allowExt' => array( 'allow'=>array(), 'deny'=>array() ),
			'validator' => array(),
			'checkIsUploaded' => true,
			'overwrite' => false,
			'noThrow' => true );

		/// Object configuration
		protected $config = array();

		/// saveName or saveName:full was set later?
		protected $nameIsFull = false;

		/// Name of th form field which provided the file.
		protected $fileFieldName = '';

		/// The file array. Keys match $_FILE.
		protected $file = array(
			'name' => '',
			'type' => '',
			'size' => 0,
			'tmp_name' => '',
			'error' => UPLOAD_ERR_OK );

		/// Internal finfo instance.
		protected $fInfoInstance = NULL;

		/// Last exception thrown.
		protected $exception = NULL;

		/// Is the file checked already?
		protected $checked = false;

		/// File is saved, we are done.
		protected $done = false;

		/** @name Error constants.
		 *	@{*/

		const E_UPLOAD_ERR_OK = UPLOAD_ERR_OK;					///< The standard UPLOAD_ERR_OK PHP error.
		const E_UPLOAD_ERR_INI_SIZE = UPLOAD_ERR_INI_SIZE;		///< The standard UPLOAD_ERR_INI_SIZE PHP error.
		const E_UPLOAD_ERR_FORM_SIZE = UPLOAD_ERR_FORM_SIZE;	///< The standard UPLOAD_ERR_FORM_SIZE PHP error.
		const E_UPLOAD_ERR_PARTIAL = UPLOAD_ERR_PARTIAL;		///< The standard UPLOAD_ERR_PARTIAL PHP error.
		const E_UPLOAD_ERR_NO_FILE = UPLOAD_ERR_NO_FILE;		///< The standard UPLOAD_ERR_NO_FILE PHP error.
		const E_UPLOAD_ERR_NO_TMP_DIR = UPLOAD_ERR_NO_TMP_DIR;	///< The standard UPLOAD_ERR_NO_TMP_DIR PHP error.
		const E_UPLOAD_ERR_CANT_WRITE = UPLOAD_ERR_CANT_WRITE;	///< The standard UPLOAD_ERR_CANT_WRITE PHP error.
		const E_UPLOAD_ERR_EXTENSION = UPLOAD_ERR_EXTENSION;	///< The standard UPLOAD_ERR_EXTENSION PHP error.
		// leave gap for future extensions
		const E_INVALID_ARG = 17;			///< Invalid argument provided.
		const E_INVALID_CONF = 18;			///< Configuration is invalid.
		const E_CHECK_SIZE_MIN = 19;		///< file size smaller than minimum limit
		const E_CHECK_SIZE_MAX = 20;		///< file size greater than maximum limit
		const E_CHECK_MIME_UNKNOWN = 21;	///< File MIME is unknown
		const E_CHECK_MIME = 22;			///< File mime is not allowed
		const E_CHECK_EXT = 23;				///< File extension is not allowed
		const E_CHECK_VALIDATOR = 24;		///< One of the validator functions denied the file
		const E_CHECK_ISUPLOADED = 25;		///< File is not an uploaded file
		const E_SAVE_DIR = 26;				///< Invalid saveDir.
		const E_SAVE_MOVE = 27;				///< Failed to move file to destination
		const E_SAVE_NAME = 28;				///< Invalid saveName
		const E_SAVE_EXISTS = 29;			///< File already exists at destination

		/// @}

		/** @name Error messages.
		 *	@{*/

		protected static $messages = array(
			self::E_UPLOAD_ERR_OK => 'no error, the file uploaded with success',
			self::E_UPLOAD_ERR_INI_SIZE => 'the uploaded file exceeds the upload_max_filesize directive in php.ini',
			self::E_UPLOAD_ERR_FORM_SIZE => 'the uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
			self::E_UPLOAD_ERR_PARTIAL => 'the uploaded file was only partially uploaded',
			self::E_UPLOAD_ERR_NO_FILE => 'no file was uploaded',
			self::E_UPLOAD_ERR_NO_TMP_DIR => 'missing temporary folder',
			self::E_UPLOAD_ERR_CANT_WRITE => 'failed to write temporary file to disk',
			self::E_UPLOAD_ERR_EXTENSION => 'a PHP extension stopped the file upload',
			self::E_INVALID_ARG => 'invalid argument passed to %s',
			self::E_INVALID_CONF => 'invalid configuration: %s',
			self::E_CHECK_SIZE_MIN => 'file must be larger than %s',
			self::E_CHECK_SIZE_MAX => 'file must be smaller than %s',
			self::E_CHECK_MIME_UNKNOWN => 'unknown file type',
			self::E_CHECK_MIME => 'files are not allowed with type %s',
			self::E_CHECK_EXT => '%s files are not allowed',
			self::E_CHECK_VALIDATOR => 'file is invalid',						// should be given explicitly by validator
			self::E_CHECK_ISUPLOADED => 'not an uploaded file',
			self::E_SAVE_DIR => 'failed to save file to destination',			// don't print any exact server info
			self::E_SAVE_MOVE => 'failed to save file',							// don't print any exact server info
			self::E_SAVE_NAME => 'falied to save file with the current name',	// don't print any exact server info
			self::E_SAVE_EXISTS => 'file already exists on server'
		);

		/// @}

		/// Units for human readable size.
		public static $units = array(
			'k' => 1024,
			'm' => 1048576,
			'g' => 1073741824 );


		/** Create a new object.
		 *	@param string $file Name of the file in the $_FILES superglobal, or an array of file values.
		 *	In case of an array is given, it must contain keys matching the ones in $_FILES. The only required key is *tmp_name*.\n
		 *	*name* defaults to *tmp_name*, *size* is calculated, *type* is not used, *error* defaults to UPLOAD_ERR_OK.
		 *	@param array $config Configuration array. Keys may be omitted, in which case the defaults are used.*/
		public function __construct( $file, array $config = array() )
		{
			$this->reset();

			// always set a default finfo instance
			$this->fInfoInstance = new \finfo();

			/* Set config ASAP.
			 * for example noThrow should be set before any fail() call.
			 * Also, stop on invalid config. Following actions will stop due to $exception.*/
			if( !$this->config( $config ) )
				{ return; }

			if( is_string($file) )
			{
				if( isset($_FILES[$file]) )
				{
					$this->fileFieldName = $file;
					$this->file = $_FILES[$file];
				}
				else
					{ $this->fail( new \Exception( self::$messages[self::E_UPLOAD_ERR_NO_FILE], self::E_UPLOAD_ERR_NO_FILE ) ); }
			}
			else if( is_array($file) )
			{
				if( isset($file['tmp_name']) )
				{
					if( is_file($file['tmp_name']) && is_readable($file['tmp_name']) )
					{
						$this->file = array_intersect_key( $file, $this->file ) + $this->file;

						// if has error, leave it
						if( !$this->file['error'] )
						{
							if( !isset($this->file['name']) || empty($this->file['name']) )
								{ $this->file['name'] = pathinfo( $this->file['tmp_name'], PATHINFO_BASENAME ); }
							if( !isset($this->file['size']) || !$this->file['size'] )
								{ $this->file['size'] = (int)@filesize($this->file['tmp_name']); }

							$this->config['checkIsUploaded'] = false;
						}
					}
					else
						{ $this->file['error'] = UPLOAD_ERR_NO_FILE; }
				}
				else
					{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_ARG],__FUNCTION__), self::E_INVALID_ARG ) ); }
			}
			else
				{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_ARG],__FUNCTION__), self::E_INVALID_ARG ) ); }


			if( $this->file['error'] )
				{ $this->fail( new \Exception( self::$messages[(int)$this->file['error']], (int)$this->file['error'] ) ); }

			if( !$this->exception )
			{
				// set default saveName, if config is empty
				if( $this->nameIsFull )
				{
					if( empty($this->config['saveName:full']) && $this->config['saveName:full'] !== '0' )
						{ $this->saveNameFull( $this->file['name'] ); }
				}
				else
				{
					if( empty($this->config['saveName']) && $this->config['saveName'] !== '0' )
						{ $this->saveName( pathinfo($this->file['name'],PATHINFO_FILENAME) ); }
				}
			}
		}


	// >>> CONFIGURATION <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
		// configs should not rely on any file info, it may be unavailable in config phase.


		/** Set saveName.
		 *	@param string $baseName Name to set.
		 *	@return FileUpload If argument is omitted, this acts as a getter for saveName.
		 *	Otherwise saveName is set and $this is returned.*/
		public function saveName( $baseName = NULL )
		{
			if( is_null($baseName) )
				{ return $this->config['saveName']; }
			else
			{
				if( !empty($baseName) && (is_string($baseName) || is_callable($baseName)) )
				{
					$this->nameIsFull = false;
					$this->config['saveName'] = $baseName;
				}
				else
					{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_CONF],__FUNCTION__), self::E_INVALID_CONF ) ); }
			}
			return $this;
		}

		/** Set saveName:full.
		 *	@param string $fileName Full name to set.
		 *	@return FileUpload If argument is omitted, this acts as a getter for saveName:full.
		 *	Otherwise saveName:full is set and $this is returned.*/
		public function saveNameFull( $fileName = NULL )
		{
			if( is_null($fileName) )
				{ return $this->config['saveName:full']; }
			else
			{
				if( !empty($fileName) && (is_string($fileName) || is_callable($fileName)) )
				{
					$this->nameIsFull = true;
					$this->config['saveName:full'] = $fileName;
				}
				else
					{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_CONF],__FUNCTION__), self::E_INVALID_CONF ) ); }
			}
			return $this;
		}

		/** Set saveDir.
		 *	@param string $dir Directory to set. Trailing directory separator is optional.
		 *	@return FileUpload If argument is omitted, this acts as a getter for saveDir.
		 *	Otherwise saveDir is set and $this is returned.*/
		public function saveDir( $dir = NULL )
		{
			if( is_null($dir) )
				{ return $this->config['saveDir']; }
			else
			{
				if( !empty($dir) && (is_string($dir) || is_callable($dir)) )
					{ $this->config['saveDir'] = $dir; }
				else
					{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_CONF],__FUNCTION__), self::E_INVALID_CONF ) ); }
			}
			return $this;
		}

		/** Set sizeLimit.
		 *	@param mixed $limit Size limit(s) to set.
		 *	@return FileUpload If argument is omitted, this acts as a getter for sizeLimit.
		 *	Otherwise sizeLimit is set and $this is returned.*/
		public function sizeLimit( $limits = NULL )
		{
			if( is_null($limits) )
				{ return $this->config['sizeLimit']; }
			else
			{
				if( $resolvedLimits = self::resolveSizeLimit($limits) )
					{ $this->config['sizeLimit'] = $resolvedLimits; }
				else
					{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_CONF],__FUNCTION__), self::E_INVALID_CONF ) ); }
			}
			return $this;
		}

		/** Set allowMime.
		 *	@param mixed $mimeType MimeType(s) tos set.
		 *	@return FileUpload If argument is omitted, this acts as a getter for allowMime.
		 *	Otherwise allowMime is set and $this is returned.*/
		public function allowMime( $mimeTypes = NULL )
		{
			if( is_null($mimeTypes) )
				{ return $this->config['allowMime']; }
			else
			{
				if( $resolvedMime = self::resolveConfigList($mimeTypes) )
					{ $this->config['allowMime'] = $resolvedMime; }
				else
					{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_CONF],__FUNCTION__), self::E_INVALID_CONF ) ); }
			}
			return $this;
		}

		/** Add a value to allowMime.
		 *	@param string Value to add.
		 *	@return FileUpload $this.*/
		public function addAllowMime( $mimeType )
		{
			if( is_string($mimeType) && $mimeType  )
			{
				if( $mimeType[0] === '!' )
					{ $this->config['allowMime']['deny'][] = substr( $mimeType, 1 ); }
				else
					{ $this->config['allowMime']['allow'][] = $mimeType; }
			}
			else
				{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_CONF],__FUNCTION__), self::E_INVALID_CONF ) ); }
			return $this;
		}

		/** Set validator.
		 *	@param mixed $callable A callable value or an array of them.
		 *	@return FileUpload If argument is omitted, this acts as a getter for validator.
		 *	Otherwise validator is set and $this is returned.*/
		public function validator( $callables = NULL )
		{
			if( is_null($callables) )
				{ return $this->config['validator']; }
			else
			{
				if( !empty($callables) )
				{
					$this->config['validator'] = array();
					$callables = array( $callables );		// watch with the cast!
					foreach( $callables as $c )
					{
						if( is_callable($c) )
							{ $this->config['validator'][] = $c; }
						else
							{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_CONF],__FUNCTION__), self::E_INVALID_CONF ) ); }
					}
				}
				else
					{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_CONF],__FUNCTION__), self::E_INVALID_CONF ) ); }
			}
			return $this;
		}

		/** Add a value to validator.
		 *	@param callable Callable to add. Validators will be called in the order of addition.
		 *	@return FileUpload $this.*/
		public function addValidator( $callable )
		{
			if( is_callable($callable) )
				{ $this->config['validator'][] = $callable; }
			else
				{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_CONF],__FUNCTION__), self::E_INVALID_CONF ) ); }
			return $this;
		}

		/** Set allowExt.
		 *	@param mixed $extension Extension(s) to set.
		 *	@return FileUpload If argument is omitted, this acts as a getter for allowExt.
		 *	Otherwise allowExt is set and $this is returned.*/
		public function allowExt( $extensions = NULL )
		{
			if( is_null($extensions) )
				{ return $this->config['allowMime']; }
			else
			{
				if( $resolvedExt = self::resolveConfigList($extensions) )
					{ $this->config['allowExt'] = $resolvedExt; }
				else
					{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_CONF],__FUNCTION__), self::E_INVALID_CONF ) ); }
			}
			return $this;
		}

		/** Add a value to allowExt.
		 *	@param string Value to add.
		 *	@return FileUpload $this.*/
		public function addAllowExt( $extension )
		{
			if( is_string($extension) && $extension  )
			{
				if( $extension[0] === '!' )
					{ $this->config['allowExt']['deny'][] = substr( $extension, 1 ); }
				else
					{ $this->config['allowExt']['allow'][] = $extension; }
			}
			else
				{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_CONF],__FUNCTION__), self::E_INVALID_CONF ) ); }
			return $this;
		}

		/** Set checkIsUploaded.
		 *	@param bool $val Value to set.
		 *	@return FileUpload If argument is omitted, this acts as a getter for checkIsUploaded.
		 *	Otherwise checkIsUploaded is set and $this is returned.*/
		public function checkIsUploaded( $val = NULL )
		{
			if( is_null($val) )
				{ return $this->config['checkIsUploaded']; }
			else
			{
				if( is_bool($val) )
					{ $this->config['checkIsUploaded'] = $val; }
				else
					{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_CONF],__FUNCTION__), self::E_INVALID_CONF ) ); }
			}
			return $this;
		}

		/** Set overwrite.
		 *	@param bool $val Value to set.
		 *	@return FileUpload If argument is omitted, this acts as a getter for overwrite.
		 *	Otherwise overwrite is set and $this is returned.*/
		public function overwrite( $val = NULL )
		{
			if( is_null($val) )
				{ return $this->config['overwrite']; }
			else
			{
				if( is_bool($val) )
					{ $this->config['overwrite'] = $val; }
				else
					{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_CONF],__FUNCTION__), self::E_INVALID_CONF ) ); }
			}
			return $this;
		}

		/** Set noThrow.
		 *	@param bool $val Value to set.
		 *	@return FileUpload If argument is omitted, this acts as a getter for noThrow.
		 *	Otherwise noThrow is set and $this is returned.*/
		public function noThrow( $val = NULL )
		{
			if( is_null($val) )
				{ return $this->config['noThrow']; }
			else
			{
				if( is_bool($val) )
					{ $this->config['noThrow'] = $val; }
				else
					{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_CONF],__FUNCTION__), self::E_INVALID_CONF ) ); }
			}
			return $this;
		}


	// >>> ACTIONS <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<


		/** Set configurations.
		 *	@note If an error is already occured, this returns false without setting anything.
		 *	@param array $config Configuration array.
		 *	@return True if the configuration is successfully set, false otherwise.*/
		public function config( array $config )
		{
			if( $this->exception )
				{ return false; }
			if( $this->done )
				{ return true; }

			// allow empty $config

			foreach( $config as $confKey => $confVal )
			{
				if( array_key_exists($confKey, $this->config) )
				{
					if( $confKey === 'saveName:full' )
						{ $confKey = 'saveNameFull'; }

					if( is_callable( array($this,$confKey) ) )
						{ $this->$confKey( $confVal ); }
				}
				else
					{ $this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_CONF],$confKey), self::E_INVALID_ARG ) ); }
			}

			if( $this->exception )
			{
				// throw our exception plus the existing message
				$this->fail( new \Exception( sprintf(self::$messages[self::E_INVALID_ARG],__FUNCTION__)."\n ".$this->exception->getMessage(), self::E_INVALID_ARG ) );
				return false;
			}
			else
				{ return true; }
		}

		/** Check and validate the uploaded file.
		 *	@note If an error is already occured, this returns false without setting anything.
		 *	@return True if file fulfills all conditions and all validator returned true. False otherwise.
		 *	@note check() stops and returns false on the first error.*/
		public function check()
		{
			if( $this->exception )
				{ return false; }
			if( $this->done )
				{ return true; }

			$this->checked = true;

			$tmpFileName = $this->file['tmp_name'];

			// just to be sure
			if( is_file($tmpFileName) && is_readable($tmpFileName) )
			{
				// check if uploaded
				if( $this->config['checkIsUploaded'] && !is_uploaded_file($tmpFileName) )
				{
					$this->fail( new \Exception( self::$messages[self::E_CHECK_ISUPLOADED], self::E_CHECK_ISUPLOADED ) );
					return false;
				}

				// check size
				$size = $this->file['size'];
				if( $this->config['sizeLimit'][0] && $size < $this->config['sizeLimit'][0] )
				{
					$this->fail( new \Exception( sprintf( self::$messages[self::E_CHECK_SIZE_MIN], self::prettySize($this->config['sizeLimit'][0]) ), self::E_CHECK_SIZE_MIN ) );
					return false;
				}
				else if( $this->config['sizeLimit'][1] && $this->config['sizeLimit'][1] < $size )
				{
					$this->fail( new \Exception( sprintf( self::$messages[self::E_CHECK_SIZE_MAX], self::prettySize($this->config['sizeLimit'][1]) ), self::E_CHECK_SIZE_MAX ) );
					return false;
				}

				// check mime
				$mime = $this->fInfoInstance->file( $tmpFileName, FILEINFO_MIME );
				if( !is_string($mime) )
				{
					$this->fail( new \Exception( self::$messages[self::E_CHECK_MIME_UNKNOWN], self::E_CHECK_MIME_UNKNOWN ) );
					return false;
				}

				$mimeOk = empty( $this->config['allowMime']['allow'] );
				foreach( $this->config['allowMime']['allow'] as $mimeAllow )
				{
					if( preg_match( "#$mimeAllow#i", $mime ) )
					{
						$mimeOk = true;
						break;
					}
				}
				if( $mimeOk )
				{
					foreach( $this->config['allowMime']['deny'] as $mimeDeny )
					{
						if( preg_match( "#$mimeDeny#i", $mime ) )
						{
							$mimeOk = false;
							break;
						}
					}
				}

				if( !$mimeOk )
				{
					$this->fail( new \Exception( sprintf(self::$messages[self::E_CHECK_MIME],$mime), self::E_CHECK_MIME ) );
					return false;
				}

				// check extension
				$ext = pathinfo( $this->file['name'], PATHINFO_EXTENSION );
				// treat empty string as a valid extension to check

				$extOk = empty( $this->config['allowExt']['allow'] );
				foreach( $this->config['allowExt']['allow'] as $extAllow )
				{
					if( $extAllow === $ext )
					{
						$extOk = true;
						break;
					}
				}
				if( $extOk )
				{
					foreach( $this->config['allowExt']['deny'] as $extDeny )
					{
						if( $extDeny === $ext )
						{
							$extOk = false;
							break;
						}
					}
				}

				if( !$extOk )
				{
					$this->fail( new \Exception( sprintf(self::$messages[self::E_CHECK_EXT],$ext), self::E_CHECK_EXT ) );
					return false;
				}

				// call validators
				foreach( $this->config['validator'] as $validator )
				{
					$message = self::$messages[self::E_CHECK_VALIDATOR];
					//if( !call_user_func_array($validator,array($this,&$message)) )
					if( !$validator($this,$message) )
					{
						$this->fail( new \Exception( $message, self::E_CHECK_VALIDATOR ) );
						return false;
					}
				}

				return true;
			}
			else
			{
				$this->fail( new \Exception( self::$messages[self::E_UPLOAD_ERR_NO_FILE], self::E_UPLOAD_ERR_NO_FILE ) );
				return false;
			}
		}

		/** Save the file.
		 *	@note If an error is already occured, this returns false without setting anything.
		 *	@return True on success, false otherwise.*/
		public function save( $dir = NULL )
		{
			if( $this->done )
				{ return true; }

			if( !$this->checked )
				{ $this->check(); }

			if( $this->exception )
				{ return false; }

			// get filename
			$saveNameDotExt = '.'.pathinfo( $this->file['name'], PATHINFO_EXTENSION );
			$saveName = $this->nameIsFull? $this->config['saveName:full'] : $this->config['saveName'];
			if( (is_string($saveName) && !empty($saveName) && $saveName[0] === '\\') || is_callable($saveName) )
				{ $saveName = $saveName( $this ); }

			if( !$this->nameIsFull )
				{ $saveName .= $saveNameDotExt; }

			if( strlen($saveName) === 0 )
			{
				$this->fail( new \Exception( self::$messages[self::E_SAVE_NAME], self::E_SAVE_NAME ) );
				return false;
			}

			// get dir
			$saveDir = $this->config['saveDir'];
			if( is_null($dir) )
			{
				$saveDir = $this->config['saveDir'];
				if( (is_string($saveDir) && $saveDir[0] === '\\') || is_callable($saveDir) )
					{ $saveDir = $saveDir( $this ); }
			}
			else
				{ $saveDir = $dir; }

			$saveDir = rtrim( $saveDir, DIRECTORY_SEPARATOR ).DIRECTORY_SEPARATOR;

			// check saveDir
			if( is_dir($saveDir) && is_really_writable($saveDir) )
			{
				if( !is_file($saveDir.$saveName) || $this->config['overwrite'] )
				{
					if( $this->moveUploadedFile( $this->file['tmp_name'], $saveDir.$saveName ) )
					{
						$this->done = true;
						return true;
					}
					else
						{ $this->fail( new \Exception( self::$messages[self::E_SAVE_MOVE], self::E_SAVE_MOVE ) ); }
				}
				else
					{ $this->fail( new \Exception( self::$messages[self::E_SAVE_EXISTS], self::E_SAVE_EXISTS ) ); }
			}
			else
				{ $this->fail( new \Exception( self::$messages[self::E_SAVE_DIR], self::E_SAVE_DIR ) ); }

			return false;
		}

		/** Reset state.
		 *	Clear errors, internal state and configuration.
		 *	Only file information, defaults and messages are kept.*/
		public function reset()
		{
			$this->config = self::$defaults;
			$this->nameIsFull = false;
			$this->setFInfo( FILEINFO_NONE );
			$this->exception = NULL;
			$this->checked = false;
			$this->done = false;
		}


	// >>> INFORMATION <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<


		/** Get file info.
		 *	Delegates call to finfo::file on the temporary file with the given options.
		 *	@return Result of finfo::file or NULL if there was an error or the file is already saved (moved form temp dir).*/
		public function fInfo( $options = FILEINFO_NONE, $context = NULL )
		{
			if( !$this->file['tmp_name'] || $this->done )
				{ return NULL; }

			return $this->fInfoInstance->file( $this->file['tmp_name'], $options, $context );
		}

		/** Set options to the internal finfo instance.*/
		public function setFInfo( $options, $magicFile = NULL )
		{
			$this->fInfoInstance = new \finfo( $options, $magicFile );
		}

		/** Get temporary file info.
		 *	@param string $key A $_FILES array key. If omitted, the whole array is returned.
		 *	@return The value of the requested info, or the file array, or NULL if $key does not exist.*/
		public function getFile( $key = NULL )
		{
			if( is_null($key) )
				{ return $this->file; }
			else if( array_key_exists($key, $this->file) )
				{ return $this->file[$key]; }
			else
				{ return NULL; }
		}

		/** Get file name (original, on the client).
		 *	@param bool $withExt If true, the whole fileName is returned with extension. If false, only the basename.
		 *	@return The filename.*/
		public function getName( $withExt = false )
		{
			if( !$this->file['name'] )
				{ return NULL; }

			if( $withExt )
				{ return $this->file['name']; }
			else
				{ return substr( $this->file['name'], 0, strrpos($this->file['name'],'.') ); }
		}

		/** Get the filesize.
		 *	@param bool $pretty If true, a formatted, human-readable size is returned, like: "5.3MB".
		 *	If false, the filesize is returned in bytes, as an integer.
		 *	 @return The filesize, according to $pretty.*/
		public function getSize( $pretty = false )
		{
			if( $pretty )
				{ return self::prettySize( $this->file['size'] ); }
			else
				{ return (int)$this->file['size']; }
		}

		/** Get the temporary path of the file.
		 *	@return The temp path.*/
		public function getTempName()
		{
			return $this->file['tmp_name'];
		}

		/** Get the MIME of the file.
		 *	@param bool $stripEncoding If true, only the MIME type and subtype is returned.
		 *	If false, the encoding is also appended, if available.
		 *	@return MIME type or NULL if there was an error or the file is already saved (moved form temp dir).*/
		public function getMime( $stripEncoding = true )
		{
			if( !$this->file['tmp_name'] || $this->done )
				{ return NULL; }

			if( $stripEncoding )
				{ return $this->fInfoInstance->file( $this->file['tmp_name'], FILEINFO_MIME_TYPE ); }
			else
				{ return $this->fInfoInstance->file( $this->file['tmp_name'], FILEINFO_MIME ); }
		}


	// >>> TOOLS <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<


		/** Convert bytes to human-readably form with metric prefixes.
		 *	Formatted like "5.3MB" or "1021B"
		 *	@param int $bytes Size in bytes.
		 *	@return Formatted size.*/
		public static function prettySize( $bytes )
		{
			$bytes = (int)$bytes;
			if( $bytes < 0 )
				{ $bytes = 0; }

			$unit = '';
			$mult = 1;
			reset(self::$units);
			while( $bytes / $mult >= 1024 )
				{ list( $unit, $mult) = each( self::$units ); }

			$dynSize = $bytes / $mult;
			$sizeStr = (string)round( $dynSize, 2 );
			return $sizeStr.strtoupper($unit).'B';
		}

		/** Convert pretty size to integer bytes.
		 *	@param mixed $prettySize Should be string with a unit suffix. If integer is passed or a numeric string,
		 *	it's treated as bytes and is returned.\n
		 *	Unit suffix is case insensitive, and can be one of the following: k,m,g,kb,mb,gb.
		 *	@return Integer size in bytes or boolean false on error.*/
		public static function prettySizeToBytes( $prettySize )
		{
			if( is_int($prettySize) || (is_numeric($prettySize) && ((int)$prettySize == $prettySize)) )
				{ return (int)$prettySize; }

			if( is_string($prettySize) )
			{
				$prettySize = rtrim( strtolower( trim($prettySize) ), 'b' );

				// check if b was the only suffix
				if( is_numeric($prettySize) && ((int)$prettySize == $prettySize) )
					{ return (int)$prettySize; }

				$unit = $prettySize[strlen($prettySize)-1];
				if( array_key_exists($unit, self::$units) )
				{
					$sizeStr = rtrim( $prettySize, $unit );
					if( is_numeric($sizeStr) )
						{ return (int)( floatval($sizeStr) * self::$units[$unit] ); }
				}
			}

			return false;
		}

		/** Set default configuration.
		 *	All new objects will be created with this configuration
		 *	@param array $config Can contain a subset of keys in @ref $defaults with custom values.*/
		public static function setDefaults( array $config )
			{ self::$defaults = array_intersect_key( $config, self::$defaults ) + self::$defaults; }

		/** Set custom message texts.
		 *	@param array $messages Can contain a subset of keys in @ref $messages with custom values.
		 *	sprintf() placeholders may be used where they are present in the default messages.*/
		public static function setCustomMessages( array $messages )
			{ self::$messages = array_intersect_key( $messages, self::$messages ) + self::$messages; }

		/// Provide the original filename.
		public function __toString()
			{ return $this->file['name']; }


	// >>> ERRORS <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<


		/** Get last error code.
		 *	@return Error code, or. Use the error constants in this class to compare.
		 *	Return 0 (UPLOAD_ERR_OK) if there's no error.*/
		public function getError()
		{
			if( $this->exception )
				{ return $this->exception->getCode(); }
			else
				{ return self::E_UPLOAD_ERR_OK; }
		}

		/** Get message of the last error.
		 *	See setCustomMessages() to override the default ones.
		 *	@return Message string, or an empty string if there's no error.*/
		public function getErrorMessage()
		{
			if( $this->exception )
				{ return $this->exception->getMessage(); }
			else
				{ return ''; }
		}


	// >>> PROTECTED <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<


		/** Handle internal exceptions.
		 *	@param \Exception $exception.*/
		protected function fail( \Exception $exception )
		{
			$this->exception = $exception;
			if( !$this->config['noThrow'] )
				{ throw $exception; }
			// returning false is the responsibility of the caller
		}

		/** Move file to final destination.
		 *	Several methods are tried. if moving the file fails, try to copy.
		 *	(Uploaded files are removed from temp dir when the request is closed anyway.)
		 *	@param string $filePath File to move.
		 *	@param string $destination Destination path.
		 *	@return True on success, false otherwise.*/
		protected function moveUploadedFile( $filePath, $destination )
		{
			$moved = false;

			if( $this->config['checkIsUploaded'] )
				{ $moved = @move_uploaded_file( $filePath, $destination ); }
			else
				{ $moved = @rename( $filePath, $destination ); }

			// fall back to copy. isUploaded was already checked in check(), so safe to use copy.
			if( !$moved )
				{ $moved = @copy( $filePath, $destination ); }

			return $moved;
		}

		/** Resolve sizeLimit syntax to integer min-max byte limits.
		 *	@param mixed $limit Limit to resolve.
		 *	@return The limits array on success, false if $limit cannot be resolved.*/
		protected function resolveSizeLimit( $limit )
		{
			$min = 1;
			$max = false;

			if( empty($limit) )
				{ return false; }

			if( is_numeric($limit) && (int)$limit == $limit )
				{ $max = (int)$limit; }
			else if( is_string($limit) || is_array($limit) )
			{
				// convert to array if string syntax
				if( is_string($limit) )
					{ $limit = explode( '-', $limit ); }

				// parse array
				if( count($limit) === 1 )
				{
					if( !($max = self::prettySizeToBytes($limit[0])) )
						{ return false; }
				}
				else if( count($limit) === 2 )
				{
					if( trim($limit[0]) )
					{
						if( ($min = self::prettySizeToBytes($limit[0])) === false )	// allow 0
							{ return false; }
					}	// empty, no limit for min

					if( trim($limit[1]) )
					{
						if( !($max = self::prettySizeToBytes($limit[1])) )
							{ return false; }
					}	// empty, no limit for max
				}
				else
					{ return false; }
			}
			else
				{ return false; }

			if( $min < 0 || $max < 0 || $min > $max )
				{ return false; }
			else
				{ return array( $min, $max ); }
		}

		/** Resolve a config list.
		 *	@param mixed $list The comma-separated list or array to resolve.
		 *	@return An array with "allow" and "deny" keys, holding the lists of items, or false if resolve failed.*/
		protected function resolveConfigList( $list )
		{
			$resolved = array(
				'allow' => array(),
				'deny' => array() );

			if( empty($list) )
				{ return false; }

			if( is_string($list) || is_array($list) )
			{
				if( is_string($list) )
					{ $list = explode( ',', $list ); }

				foreach( $list as $item )
				{
					$item = trim($item);
					// allow empty items
					if( strlen($item) > 0 && $item[0] === '!' )		// use strlen. empty is confusing ('0' and such)
						{ $resolved['deny'][] = substr( $item, 1 ); }
					else
						{ $resolved['allow'][] = $item; }
				}

				return $resolved;
			}
			else
				{ return false; }
		}
	}

	}

	namespace {

	if( !function_exists('is_really_writable') )
	{
		/// @todo implement
		function is_really_writable( $file )
		{
			return is_writable( $file );
		}
	}

	}
