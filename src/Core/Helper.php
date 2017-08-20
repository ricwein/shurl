<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\shurl\Core;

/**
 * provides static helper methods for shurl
 */
class Helper {
    /**
     * @param  callable $callback
     * @param  array    $array
     * @return array
     */
    public static function array_map_recursive(callable $callback, array $array): array {
        $resultArray = is_array($array) ? $array : get_object_vars($array);

        foreach ($resultArray as $key => $val) {
            $resultArray[$key] = is_array($val) || is_object($val) ? self::array_map_recursive($callback, $val) : call_user_func($callback, $val);
        }

        return $resultArray;
    }
}
