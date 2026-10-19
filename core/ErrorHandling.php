<?php
/**
 * ErrorHandling Class for Guavas-Framework
 * @author      Roywae
 * @category    core
 * @package     ErrorHandling
 * @version     1.0.4
 */

class ErrorHandling {

    /* Error Handling Function */
    public static function addNotice($errNo, $errStr, $errFile, $errLine) {
        $error_msg = "Error Notice : " . $errNo . "\n";
        $error_msg .= "Message : " . $errStr . "\n";
        $error_msg .= "Location : " . $errFile . "\n";
        $error_msg .= "Line Number : " . $errLine . "\n";

        /* Error Logging in General error_log File*/
        error_log($error_msg, 0);
        exit;
    }

    /**
     *   Error 1020 "Access Denied"
     */
    public static function getError1020()
    {
        http_response_code(1020);
        echo "<!DOCTYPE html><html>".
        "<head><title>1020 Website Blocked</title>". 
        "<meta http-equiv=\"Content-Security-Policy\" content=\"default-src 'self';\" /></head>". 
        "<body><center><h2>Temporarily Banned IP Address</h2><hr /><p>This Website is using a security to protect itself from online attacks.<br />". 
        "Your IP Address ".gv_client_ip()." has been banned. Our system detected malicious traffic from your computer network.</p>". 
        "<p>Powered by <b>GWSF v2.0.00</b></p></center>". 
        "</body></html>";
        exit;
    }

    public static function getError500()
    {
        http_response_code(500);
        echo "<!DOCTYPE html><html>".
        "<head><title>500 Internal Server Error</title>". 
        "<meta http-equiv=\"Content-Security-Policy\" content=\"default-src 'self';\" /></head>". 
        "<body><center><h2>HTTP Error 500 - Internal Server Error</h2><hr /><p>We're unable to find out what's happening! We suggest you to </p><p><a href=\"javascript: window.history.back()\">Go Back</a> or visit here later.</p></center>". 
        "</body></html>";
        exit;  
    }

    public static function getError404() 
    {
        http_response_code(404);
        echo "<!DOCTYPE html><html>".
        "<head><title>404 Page Not Found</title>". 
        "<meta http-equiv=\"Content-Security-Policy\" content=\"default-src 'self';\" /></head>". 
        "<body><center><h2>HTTP Error 404 - Page Not Found</h2><hr /><p>The page you are looking for doesn't exist or an other error occured.</p><p><a href=\"javascript: window.history.back()\">Go Back</a></p></center>". 
        "</body></html>";
        exit;
    }

    public static function getError403($message = "You don't have permission to access on this server") 
    {
        http_response_code(403);
        echo "<!DOCTYPE html><html><head><title>403 Forbidden</title><meta http-equiv=\"Content-Security-Policy\" content=\"default-src 'self';\" /></head><body><center><h2>HTTP Error 403 - Forbidden</h2><hr /><p>Forbidden: ".$message."</p></center></body></html>";
        exit;
    }
}
