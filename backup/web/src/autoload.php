<?php
/**
 * Autoload pour TechSuivi avec PHPMailer
 */
if (file_exists(__DIR__ . "/vendor/autoload.php")) {
    require_once __DIR__ . "/vendor/autoload.php";
}

function isPHPMailerAvailable() {
    return class_exists("PHPMailer\\PHPMailer\\PHPMailer");
}
