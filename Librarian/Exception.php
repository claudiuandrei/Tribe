<?php
/**
 * This file is part of the Tribe project for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace Tribe\Librarian;

/**
 * Generic package exception.
 *
 * @package Tribe\Librarian
 */
class Exception extends \Exception
{
	/**
	 * Exception thrown when the file for the class or interface is already loaded.
	 *
	 * @const
	 */
	const ALREADY_LOADED = 0;
	
	/**
	 * Exception thrown when the file for the class or interface is not found.
	 *
	 * @const
	 */
	const NOT_READABLE = 1;
	
	/**
	 * Exception thrown when the file is found but the class or interface is not declared.
	 *
	 * @const
	 */
	const NOT_DECLARED = 2;
}