<?php declare(strict_types=1);
namespace App\Support;

class Debug
{
    private static function projectRootDir(): string
    {
        static $dir;
        if ($dir === null) {
            $dir = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR;
        }
        return $dir;
    }

    /**
     * Format the trace similar to PHP's builtin facilities but do some smart
     * cleanup regarding the filename to keep them sane and not have them
     * take up too much screen space when viewed interactively.
     */
    public static function prettyPrintTrace(array $frames): string
    {
        $root = self::projectRootDir();

        $trace = "";
        foreach ($frames as $i => $frame) {
            $file = $frame['file'];
            if ($file) {
                // Strip the directory prefix.
                if (str_starts_with($file, $root)) {
                    $file = substr($file, strlen($root));
                }

                // If the trace reaches into third-party code, display the
                // listing in a slightly shortened form.
                if (str_starts_with($file, "vendor" . DIRECTORY_SEPARATOR)) {
                    $file = substr($file, strlen("vendor" . DIRECTORY_SEPARATOR));
                    [$org, $pkg, $file] = explode(DIRECTORY_SEPARATOR, $file, 3);

                    // If the source file is in a `src` dir, as most Laravel
                    // packages are, strip that, why not.
                    $dirEnd = strpos($file, DIRECTORY_SEPARATOR);
                    if ($dirEnd !== false) {
                        $dir = substr($file, 0, $dirEnd);
                        if ($dir === 'src') {
                            $file = substr($file, $dirEnd + 1);
                        }
                    }

                    $file = sprintf('{%s/%s}/%s', $org, $pkg, $file);
                }
            } else {
                $file = "<unknown>";
            }

            $invocation = $frame['function'] ?? "{unknown}";
            if (array_key_exists('class', $frame)) {
                $invocation = $frame['class'] . $frame['type'] . $invocation;
            }
            $trace .= sprintf("#%d %s(%d): %s()\n",
                $i, $file, $frame['line'] ?: 0, $invocation);
        }

        return $trace;
    }
}
