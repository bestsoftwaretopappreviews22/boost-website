<?php

require_once(__DIR__.'/../common/code/boost.php');

if (isset($_GET['version'])) {
    try {
        $version = BoostVersion::from($_GET['version']);
    }
    catch (BoostVersion_Exception $e) {
        echo json_encode(Array(
            'error' => $e->getMessage(),
        ));
        exit(0);
    }
} else {
    $version = BoostVersion::current();
}

$libs = BoostLibraries::load();

// TODO: This is just crazy.
function only_released($lib) {
    return $lib['boost-version'];
}
$lib_array = $libs->get_for_version($version, null, 
    $version->is_numbered_release() ? 'only_released' : null);
$version_libs = BoostLibraries::from_array($lib_array,
    array('version' => $version));

header('Content-type: application/json');
echo $version_libs->to_json();
