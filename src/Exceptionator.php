<?php

namespace Dashifen\Exceptionator;

use Dashifen\Database\DatabaseExceptionInterface;
use Dashifen\Request\RequestInterface;
use ErrorException;
use Throwable;

class Exceptionator implements ExceptionatorInterface {
	/**
	 * @var bool
	 */
	protected $displayExceptions = true;
	
	/**
	 * @var RequestInterface
	 */
	protected $request;
	
	public function __construct(RequestInterface $request) {
		$this->request = $request;
	}
	
	/**
	 * engages or disengages error logging.  when engaging, this does so
	 * at the specified error level using the E_* constants defined here:
	 * https://secure.php.net/manual/en/errorfunc.constants.php
	 *
	 * @param bool $engage
	 * @param int  $errorLevel
	 */
	public function handleErrors(bool $engage, int $errorLevel = E_ALL | E_STRICT): void {
		if ($engage) {
			set_error_handler([$this, "errorHandler"], $errorLevel);
		} else {
			restore_error_handler();
		}
	}
	
	/**
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
	public function errorHandler(int $severity, string $message, string $filename, int $line): void {
		throw new ErrorException($message, 0, $severity, $filename, $line);
	}
	
	/**
	 * determines if this object acts as the catcher of last resort for
	 * exceptions not caught elsewhere.  if it is doing so, the $display
	 * bool indicates whether the catch is silent or loud.
	 *
	 * @param bool $engage
	 * @param bool $display
	 */
	public function handleExceptions(bool $engage, bool $display = true): void {
		$this->displayExceptions = $display;
		
		if ($engage) {
			set_exception_handler([$this, "exceptionHandler"]);
		} else {
			restore_exception_handler();
		}
	}
	
	/**
	 * the exception handler that the above method engages (or disengages).
	 *
	 * @param Throwable $exception
	 *
	 * @return void
	 */
	public function exceptionHandler(Throwable $exception): void {
		
		// if this object is handling exceptions and one is thrown but
		// not caught elsewhere, we end up here.  eventually, we want to
		// use this object to log in a psr-3 sort of way, but we're not
		// there yet.  so, if our displayExceptions property is false,
		// we can actually simply return.  this is most likely used to
		// simply silence exceptions in a production environment.  when
		// we add logging capability to this object, then we won't be
		// able to quit this early.
		
		if (!$this->displayExceptions) {
			return;
		}
		
		$messageParts = $this->getMessage($exception);
		$message = $this->getMessageDisplay($messageParts);
		die($message);
	}
	
	protected function getMessage(Throwable $exception): array {
		$message = [
			"File" => $exception->getFile() . ":" . $exception->getLine(),
			"Description" => $exception->getMessage(),
			"Trace" => $this->getTrace($exception)
		];
		
		$session = $this->request->getSessionObj();
		if ($session->isAuthenticated()) {
			$message["User"] = $session->get("USERNAME");
		}
		
		if ($exception instanceof DatabaseExceptionInterface) {
			$message["Query"] = $exception->getQuery();
		}
		
		$post = $this->request->getPost();
		if (sizeof($post) > 0) {
			$message["Post"] = $this->preparePost($post);
		}
		
		$files = $this->request->getFiles();
		if (sizeof($files) > 0) {
			$message["Files"] = $files;
		}
		
		return $message;
	}
	
	protected function getTrace(Throwable $exception): array {
		$traces = [];
		
		foreach ($exception->getTrace() as $trace) {
		
			// if we don't have a file attached to this trace, we'll
			// simply continue onto the next one.
			
			if (!isset($trace["file"]) || empty($trace["file"])) {
				continue;
			}
			
			// we also don't want this class to appear in the trace
			// results because it's unnecessary.
			
			if (is_a($trace["class"], "Exceptionator")) {
				continue;
			}
			
			// now that we know we can reference the file that brought
			// us to this point, we'll want to see if we can find a
			// function or method within that file which did so.
			
			$function = isset($trace["function"])
				? (isset($trace["class"]) ? sprintf("%s::%s", $trace["class"], $trace["function"]) : $trace["function"])
				: "";
			
			// finally, we'll specify the file and line, and then if we
			// found a function, we'll add that, too.
			
			$trace = sprintf("%s:%s", $trace["file"], $trace["line"]);
			if (!empty($function)) {
				$trace .= ", $function";
			}
			
			$traces[] = $trace;
		}
		
		return $traces;
	}
	
	protected function preparePost(array $post): array {
		
		// this is a simple function, but we've added it so that this object
		// can be extended to manipulate other posted values that we might want
		// hidden or removed or whatever.
		
		if (isset($post["password"])) {
			$post["password"] = "************";
		}
		
		return $post;
	}
	
	protected function getMessageDisplay(array $parts): string {
		$message = "<ul>";
		
		foreach ($parts as $field => $value) {
			$message .= "<li><strong>$field</strong>: ";
			$message .= is_array($value) ? $this->getMessageDisplay($value) : $value;
			$message .= "</li>";
		}
		
		return $message . "</ul>";
	}
}
