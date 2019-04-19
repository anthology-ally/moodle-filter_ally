<?php
/**
 * Serves 3rd party js files.
 * (c) Guy Thomas 2018
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function filter_ally_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    $pluginpath = __DIR__.'/';

    if ($filearea === 'vendorjs') {
        // Typically CDN fall backs would go in vendorjs.
        $path = $pluginpath.'vendorjs/'.implode('/', $args);
        send_file($path, basename($path));
        return true;
    } else if ($filearea === 'vue') {
        // Vue components.
        $jsfile = array_pop ($args);
        $compdir = basename($jsfile, '.js');
        $umdfile = $compdir.'.umd.js';
        $args[] = $compdir;
        $args[] = 'dist';
        $args[] = $umdfile;
        $path = $pluginpath.'vue/'.implode('/', $args);
        send_file($path, basename($path));
        return true;
    } else {
        die('unsupported file area');
    }
    die;
}
?>