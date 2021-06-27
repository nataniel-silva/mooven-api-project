<?php
namespace App\EventListener;

use Symfony\Component\Console\Event\ConsoleErrorEvent;
use App\Exception\DefaultException;
use App\Exception\BOException;

/**
 *
 * Listener para tratar as exceptions lançadas por aplicações que rodam via console
 *
 */
class ConsoleExceptionHandler {
	public function onConsoleError(ConsoleErrorEvent $event) {
		/**
		 * @var \Exception $e
		 */
		$e = $event->getError();
		if ($e instanceof DefaultException) {
			$msg = $e->getMessage();
			if ($e->getDebugMessage()) {
				$msg .= "\nDebug: ".$e->getDebugMessage();
			}
			if ($e->getCode() != $e->getMessage() && $e->getCode()) {
				$msg .= "\nError ID: ".$e->getCode();
			}
			if ($e instanceof BOException) {
				if ($e->getSubErrors()) {
					$subMsgs = [];
					foreach ($e->getSubErrors() as $sube) {
						$subMsg = $sube->getMessage();
						if ($sube instanceof DefaultException) {
							$subMsg .= ' ('.$sube->getCode().')';
						}
						$subMsgs[] = $subMsg;
					}
					$msg .= "\nSub Errors:\n\t".implode("\n\t", $subMsgs);
				}
			}
			$e->setMessage($msg);
		}
	}
}
