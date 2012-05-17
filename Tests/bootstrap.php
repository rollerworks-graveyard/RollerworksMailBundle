<?php

require 'vendor/autoload.php';

// Swiftmailer needs a special autoloader to allow
// the lazy loading of the init file (which is expensive)
require_once 'vendor/swiftmailer/swiftmailer/lib/classes/Swift.php';
Swift::registerAutoload('vendor/swiftmailer/swiftmailer/lib/swift_init.php');
