<?php

namespace Dashifen\Exceptionator;

use Throwable;
use ErrorException;

/**
 * Interface ExceptionatorInterface
 *
 * @package Dashifen\Exceptionator
 */
interface ExceptionatorInterface {
	/**
   * handleErrors
   *
	 * engages or disengages error logging.  when engaging, this does so
	 * at the specified error level using the E_* constants defined here:
	 * https://secure.php.net/manual/en/errorfunc.constants.php
	 *
	 * @param bool $engage
	 * @param int  $errorLevel
	 *
	 * @return void
	 */
	public function handleErrors(bool $engage, int $errorLevel = E_ALL | E_STRICT): void;
	
	/**
   * errorHandler
   *
	 * the error handler that is engaged (or disengaged) by the prior method.
	 *
	 * @param int    $severity
	 * @param string $message
	 * @param string $filename
	 * @param int    $line
	 *
	 * @return void
	 * @throws ErrorException
	 */
	public function errorHandler(int $severity, string $message, string $filename, int $line): void;
	
	/**
   * handleExceptions
   *
	 * determines if this object acts as the catcher of last resort for
	 * exceptions not caught elsewhere.  if it is doing so, the $display
	 * bool indicates whether the catch is silent or loud.
	 *
	 * @param bool $engage
	 * @param bool $display
	 *
	 * @return void
	 */
	public function handleExceptions(bool $engage, bool $display = true): void;

  /**
   * exceptionHandler
   *
   * the exception handler that the above method engages (or disengages).
   *
   * @param Throwable $exception
   * @param bool|null $display
   *
   * @return string
   */
	public function exceptionHandler(Throwable $exception, ?bool $display = null): string;
}
