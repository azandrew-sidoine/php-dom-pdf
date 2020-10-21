<?php


if (!function_exists('storage_path')) {
    /**
     * Get the path to the storage folder.
     *
     * @param  string  $path
     * @return string
     */
    function storage_path($path = '')
    {
        if (class_exists('\\Illuminate\\Container\\Container')) {
            return forward_static_call(array('\\Illuminate\\Container\\Container', 'getInstance'))->make('path.storage') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
        }
        throw new \RuntimeException(sprintf('%s call requires %s package', __FUNCTION__, 'illuminate/container'));
    }
}
