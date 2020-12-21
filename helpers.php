<?php


if (!function_exists('drewlabs_packages_dompdf_storage_path')) {
    /**
     * Get the path to the storage folder.
     *
     * @param  string  $path
     * @return string
     */
    function drewlabs_packages_dompdf_storage_path($path = '')
    {
        if (function_exists('storage_path')) {
            return call_user_func('storage_path', $path);
        }
        if (class_exists('\\Illuminate\\Container\\Container')) {
            return call_user_func_array(array('\\Illuminate\\Container\\Container', 'getInstance'), [])->make('path.storage') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
        }
        throw new \RuntimeException(sprintf('%s call requires %s package', __FUNCTION__, 'illuminate/container'));
    }
}
