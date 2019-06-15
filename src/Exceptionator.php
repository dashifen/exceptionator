<?php

namespace Dashifen\Exceptionator;

use Dashifen\Database\DatabaseExceptionInterface;
use Dashifen\Request\RequestInterface;
use ErrorException;
use Throwable;

/**
 * Class Exceptionator
 *
 * @package Dashifen\Exceptionator
 */
class Exceptionator implements ExceptionatorInterface {
	/**
	 * @var bool
	 */
	protected $displayExceptions = true;
	
	/**
	 * @var RequestInterface
	 */
	protected $request;

  /**
   * Exceptionator constructor.
   *
   * If this object is being used as a part of Dash's overall framework of
   * tools, then it's likely that it'll receive an object implementing the
   * RequestInterface object here.  But if they or you are not using that
   * framework, you can skip that.
   *
   * @param RequestInterface|null $request
   */
	public function __construct(?RequestInterface $request = null) {
		$this->request = $request;
	}
	
	/**
   * handleErrors
   *
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
	public function errorHandler(int $severity, string $message, string $filename, int $line): void {
		throw new ErrorException($message, 0, $severity, $filename, $line);
	}
	
	/**
   * handleExceptions
   *
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
   * exceptionHandler
   *
   * the exception handler that the above method engages (or disengages).
   *
   * @param Throwable $exception
   * @param bool|null $display
   *
   * @return string
   */
	public function exceptionHandler(Throwable $exception, ?bool $display = null): string {
		
		// the return type hint is sort of a lie.  usually, probably just echos
    // our error message to the screen and dies, but sometimes we want it to
    // return the error message.  that's where the second parameter comes in.
    // if it's null, we do whatever the displayExceptions property tells us
    // to do, but if it's not-null, we do what it specifies.

    $display = $display ?? $this->displayExceptions;
		$messageParts = $this->getMessage($exception);
		$message = $this->getMessageDisplay($messageParts);

		// now, if we're displaying our message, we'll just die here.  but, if
    // we're not, then we'll skip this if block and return.  that's why the
    // type hint is a lie:  sometimes, maybe most of the time, we'll never
    // actually get to the return statement.

    if ($display) {
      die($message);
    }

		return $message;
	}

  /**
   * getMessage
   *
   * Given an exception, extracts pertinent information from it in order
   * to build a message that we can display on-screen or return from our
   * scope.
   *
   * @param Throwable $exception
   *
   * @return array
   */
	protected function getMessage(Throwable $exception): array {
		$message = [
			"File" => $exception->getFile() . ":" . $exception->getLine(),
			"Description" => $exception->getMessage(),
			"Trace" => $this->getTrace($exception)
		];

		// for their own convenience, Dash wrote this as a part of their
    // framework of objects.  thus, if this exception that was thrown
    // was one of their DatabaseExceptions, we can get the query that
    // was being run as follows.

		if ($exception instanceof DatabaseExceptionInterface) {
			$message["Query"] = $exception->getQuery();
		}

		// similarly, they optionally connected this into their request objects.
    // if that connection has been made, we'll do the following.  otherwise,
    // we try to have some reasonable defaults for those of you who are not
    // using that object.

    $isRequestInterface = $this->request instanceof RequestInterface;

    if ($isRequestInterface) {

      // as long as our request property is a RequestInterface object,
      // we'll grab the session object out of it and use that to determine
      // information about the user that was logged in when this exception
      // occurred.

      $session = $this->request->getSessionObj();
      if ($session->isAuthenticated()) {
        $message["User"] = $session->get("USERNAME");
      }
    }

    // if we're connected to a request object, we'll use it to get to our
    // POST and FILES superglobals.  but, if we're not, then we'll just
    // access them directly.

		$post = $isRequestInterface
      ? $this->request->getPost()
      : $this->transformPost($_POST);

		$files = $isRequestInterface
      ? $this->request->getFiles()
      : $this->transformFiles($_FILES);

		// we only want to add the post and files information to our message
    // if there's actually information here.  i.e., if there were no posted
    // data, then we don't want a blank line in our message.  hence, the
    // sizeof() calls here.

		if (sizeof($post) > 0) {
			$message["Post"] = $post;
		}
		
		if (sizeof($files) > 0) {
			$message["Files"] = $files;
		}
		
		return $message;
	}

  /**
   * getTrace
   *
   * Returns an array that contains the trace of the route through our
   * software that this exception took on its way to us.
   *
   * @param Throwable $exception
   *
   * @return array
   */
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
			
			if (is_a(($trace["class"] ?? ""), "Exceptionator")) {
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

  /**
   * transformPost
   *
   * This is sort of a stub, but since the password a person enters is often
   * a part of the $_POST superglobal when posting data, we simply hide it.
   * Other developers may want to hide other data as well.
   *
   * @param array $post
   *
   * @return array
   */
	protected function transformPost(array $post): array {
		
		// this is a simple function, but we've added it so that this object
		// can be extended to manipulate other posted values that we might want
		// hidden or removed or whatever.
		
		if (isset($post["password"])) {
			$post["password"] = "************";
		}
		
		return $post;
	}

  /**
   * transformFiles
   *
   * This one is completely a stub in case extensions need to change the
   * way we display the $_FILES superglobal.
   *
   * @param array $files
   *
   * @return array
   */
  protected function transformFiles (array $files): array {
    return $files;
	}

  /**
   * getMessageDisplay
   *
   * Given all the parts that we want to display as the message about our
   * exception, this one builds it all together as a nested, unordered list.
   * Note:  this method is recursive.
   *
   * @param array $parts
   *
   * @return string
   */
	protected function getMessageDisplay(array $parts): string {
		$message = "<ul>";
		
		foreach ($parts as $field => $value) {
			if (!empty($value)) {
				$message .= "<li><strong>$field</strong>: ";
				$message .= is_array($value) ? $this->getMessageDisplay($value) : $value;
				$message .= "</li>";
			}
		}
		
		return $message . "</ul>";
	}
}
