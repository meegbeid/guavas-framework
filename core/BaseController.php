<?php

class BaseController
{
    private $_headers = [];

    public function __construct()
    {
        // reset header 
        $this->_headers = [];
    }

    protected function setHeader(string|int $strHeader)
    {
        $this->_headers[$strHeader] = '';
        return $this;
    }

    protected function response(int|array $code, bool $status, mixed $data = [], string $message = ''): string
    {
        if(is_array($code) && sizeof($code) == 2) {
            list($responseCode, $errorCode) = $code;
        } else {
            $responseCode = $code;
            $errorCode = 0;
        }
         
        // if empty message
        if(trim($message) === '') {
            $message = HTTP_MESSAGES[$responseCode] ?? 'Unknown';
        }
        $response = [
            'status'    => $status ? 'success' : 'error',
            'code'      => $responseCode,
            'error_code'=> $errorCode,
            'datetime'  => date('Y-m-d\TH:i:s\Z'),
            'timestamp' => time(),
            'message'   => $message
        ];
        if(!empty($data) || is_array($data)){
            $response['data'] = $data;
        }

        // load http headers
        if (isset($this->_headers) && is_array($this->_headers)) {
            foreach($this->_headers as $key=>$value) {
                header(is_string($key) ? "$key: $value" : $value, true);
            }
        }

        http_response_code($responseCode);
        header('Content-Type: application/json; charset=utf-8');

        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    protected function validate(mixed $inputs, $conditions = []) 
    {
        $validation = new FormValidation($inputs);
        $validation->validate($conditions);
        return $validation;
    }
}