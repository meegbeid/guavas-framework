<?php

class Request
{
    public static function isAjaxRequest() 
    {
        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
        {    
            return true;
        }
        return false;
    }
    public static function method()
    {
        if(isset($_SERVER['REQUEST_METHOD'])) {
			return strtoupper($_SERVER['REQUEST_METHOD']);
		}
        return false;
    }
    public static function all()
    {
        return GV_INPUT_POST + GV_INPUT_GET;
    }
    public static function postAll()
    {
        return GV_INPUT_POST;
    }
    public static function getAll()
    {
        return GV_INPUT_GET;
    }
    public static function validate(mixed $conditions, $method='REQUEST')
    {
        $formInput = self::all();
        if(strtoupper($method) == 'POST') {
            $formInput = self::postAll();
        }
        $validation = new FormValidation($formInput);
        $validation->validate($conditions);
        return $validation;
    }
    public static function files(mixed $inputFile)
    {
        return new \FileRequest($inputFile);
    }
}