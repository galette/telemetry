<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

return $config
    ->addPathsToScan(['public/index.php', 'phinx.php'], false)
    ->ignoreErrorsOnPackages([
        'mledoze/countries', // JSON data files, read from vendor directory
        'robmorgan/phinx', // Migrations CLI
    ], [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnExtension('ext-pdo_pgsql', [ErrorType::UNUSED_DEPENDENCY]) // PDO driver used by Eloquent
;
