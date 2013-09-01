FileUpload
==========

An elegant, easy to configure and flexible one-class PHP library to handle uploaded files.

No fancy and unneccessary validator classes with 10-line files, or complicated object-based configuration.
The classic PHP file uploading is an old, simple, legacy technique, it should be easy, and quick. With FileUpload, it IS.

## Basic usage

...can be really simple:
```php
$ok = (new FileUpload('fileFieldName'))->save('to/dir/');
```
and easily configured:
```php
$ok = (new FileUpload('fileFieldName'))->saveName('myName')->save('to/dir/');
```
allowing more advanced settings:
```php
$fu = new FileUpload( 'fileFieldName', array(
	'saveName:full' => '\md5',
	'allowMime' => 'application, !x-executable, '!octet-stream',
	'sizeLimit' => '3M' ) );
if( !$fu->save('to/dir/') )
	{ echo $fu->getErrorMessage(); }
```

## Configuration

You can use the config argument of the constructor, or the similarly named chainable configuration methods.

- **saveName** <i>(string|callable)</i>: Save the file with this name. The original extension will be appended.
	Callable signature: `function( $fileObj ): string`.  
	If a string is prefixed with a backslash (eg.: `"\md5"`), it is treated as a callable function.  
	Default is to save the file with the original name and extension.
- **saveName:full** <i>(string|callable)</i>: Same as *saveName*, but the name is treated as a full file name, and no extension is appended.
- **saveDir** <i>(string|callable)</i>: Save the file to this destination directory. Trailing directory separator is optional.
	Callable signature: `function( $fileObj ): string`.  
	If a string is prefixed with a backslash (eg.: `"\md5"`), it is treated as a callable function.  
	There is no default, this must be given.
- **sizeLimit** <i>(string|array)</i>: Size limits for the file. A *limit* can be integer bytes or a string of a float + metric suffix.
	eg.: "3.4MB" or "500k". The min and max limits can be given as:
	- a single value: a single *limit*
	- in an array: `array( 500, "2M" ); array( NULL, "4.5g" ); array( "20k", NULL )`, `NULL` meaning no limit.
	- with a string: a dash ("-") separating the min - max *limits*: `"50k-20Mb"`, `"- 1500KB"`, `"20k-"`
	 
	Default limits: `array( 1, NULL );`
- **allowMime** <i>(string|array)</i>: an array or a comma-separated list of MIME types with optional charset.
	The items are treated as regular expressions (passed to preg_match like `"#$mime#i"`). 
	If a mime is prefixed with an exclamation mark, that mime will be on the deny list. 
	These are all valid: `"application"`, `"/x-executable"`, `"/vnd.+;charset=utf-?8"`, `"image/"`.
- **allowExt** <i>(string|array)</i>: an array or a comma-separated list of extensions (without leading dot).
	The list is treated similarly as *allowMime*.
- **validator** <i>(callable)</i>: Callable signature: `function( $fileObj, &$message ): bool`
	A validator callback. A validator is passed the FileUpload object and an optional `$message` variable,
	and must return true or false. If false is returned, the file check is terminated and the file won't be saved.
	A custom error message can be provided with the `$message` argument.
- **checkIsUploaded** <i>(boolean)</i>: If true, the file is checked if it's a valid PHP-uploaded file. (`is_uploaded_file()`). Default is true.
- **overwrite** <i>(boolean)</i>: If true, destination file is overwritten if already exists. Default is false.
- **noThrow** <i>(boolean)</i>: If true, no exceptions are thrown, errors can be checked and retrieved with getError() and getErrorMessage().
	Default is true.
  
For the configs allowing multiple items ( *validator*, *allowMime*, *allowExt* ), an additional accessor method is available,
prefixed with *add*: `addAllowMime()`, `addAllowExt()`, `addValidator()`, appending a single item to the existing list.

## Actions

You may use the following actions:
- config(): Set all config as an array.
- check(): Check the file.
- save(): Move the file to destination.

If you would like to handle an arbitrary file (not in $_FILES), pass a $_FILE - like array to the constructor, like:

```php
$fu = new FileUpload( array(
	'tmp_name' => 'path/to/file.ext' ) );
$fu->save('to/dest/');
```
The only required key is `tmp_name`, the rest is calculated.

*checkIsUploaded* is automatically set to false when an explicit file is given this way.

## Examples, usage

```php
$success = (new FileUpload('fileFieldName'))->saveNameFull('\md5')->save('to/dir/');
```

```php
$fu = new FileUpload('fileFieldName', array(
	'saveDir' => array( $this, 'getDestinationDir' ),
	'saveName:full' => function($file) { return 'custom.name'; } ) );
try { $fu->noThrow(false)->addValidator( array($this,'validateFile') )->save(); }
catch( \Exception $e )
{
	log($e);
	echo $fu->getErrorMessage();
}
```

## Requirements

- PHP >= 5.3.0

--------------------------------------
Conforming to PSR-0 and PSR-1.
Feel free to change the namespace to suit your framework.