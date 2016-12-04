<?php 
/**
 * Common debug functions like log, log backtrace, etc
 *
 * @category Ctn
 * @package  Ctn_Debug
 * @author   Constantin Nita <ctinnita@gmail.com>
 */
class Ctn_Debug_Helper_Data extends Mage_Core_Helper_Abstract
{
    const DEBUG_LOG_FILENAME = 'ctn_debug.log';
    const DEBUG_FUNCTION_PADDING = 60;

    public function logBacktrace($message = '')
    {
        $trace = debug_backtrace();
        // drop current call
        array_shift($trace);

        $url = Mage::helper('core/url')->getCurrentUrl();

        $traceLog = '';
        if (0 < strlen($message)) {
            $traceLog = "$message\n$url\n\n";
        } else {
            $traceLog = "$url\n\n";
        }

        foreach ($trace as $call) {
            // prepare 'function' name, meaning simple function name or object method call
            if (isset($call['type']) && 0 < strlen($call['type'])) {
                $function = $call['class'] . $call['type'] . $call['function'];
            } else {
                $function = $call['function'];
            }

            // prepare call arguments
            $textArgs = [];
            foreach ($call['args'] as $arg) {
                if (is_object($arg)) {
                    $text = 'Object';
                } elseif (is_array($arg)) {
                    $text = 'Array';
                } elseif (is_string($arg)) {
                    $text = '\'' . $arg . '\'';
                } else {
                    $text = $arg;
                }

                $textArgs[] = $text;
            }
            $textArgs = implode(', ', $textArgs);

            // sum it up!
            $callLog = sprintf("%s(%s)", $function, $textArgs);
            $callLog = sprintf("%-". self::DEBUG_FUNCTION_PADDING ."s %s(%s)", $callLog, $call['file'], $call['line']);

            $traceLog .= $callLog . "\n";
        }

        $this->log($traceLog);
    }

    public function log($message)
    {
        Mage::log($message, null, self::DEBUG_LOG_FILENAME);
    }    
}