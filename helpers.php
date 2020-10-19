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
        \Illuminate\Container\Container::getInstance()->make('path.storage') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}
