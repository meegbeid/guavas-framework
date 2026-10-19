<?php

class Curl
{
    protected string $url;
    protected array $headers = [];
    protected string $method = 'GET';
    protected mixed $data = null;
    protected int $timeout = 30;
    protected $responseInfo = [];
    protected static string $logFile = '';
    protected static $decodeJson = true;
    protected static $concurrencyLimit = 10;

    public function __construct($url = '')
    {
        if ($url) {
            $this->url = $url;
        }
    }

    // --------------------------
    // Basic configuration
    // --------------------------
    public function setUrl(string $url)
    {
        $this->url = $url;
        return $this;
    }

    public function setMethod(string $method)
    {
        $this->method = strtoupper($method);
        return $this;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
        return $this;
    }

    public function addHeader(string $key, string $value)
    {
        $this->headers[] = "$key: $value";
        return $this;
    }

    public function setData(mixed $data)
    {
        $this->data = $data;
        return $this;
    }

    public function setTimeout(int $seconds)
    {
        $this->timeout = (int)$seconds;
        return $this;
    }

    public function setBearerToken(string $token)
    {
        return $this->addHeader('Authorization', 'Bearer ' . $token);
    }

    public function setBasicAuth(string $username, string $password)
    {
        return $this->addHeader('Authorization', 'Basic ' . base64_encode("$username:$password"));
    }

    // --------------------------
    // Global options
    // --------------------------
    public static function setLogFile(string $path)
    {
        self::$logFile = $path;
    }

    public static function setDecodeJson(bool $bool)
    {
        self::$decodeJson = (bool)$bool;
    }

    public static function setConcurrencyLimit(int $limit)
    {
        self::$concurrencyLimit = max(1, (int)$limit);
    }

    // --------------------------
    // Single request
    // --------------------------
    public function send()
    {
        if (empty($this->url)) {
            throw new \Exception("URL is not set");
        }

        $ch = curl_init();
        $this->prepareHandle($ch);

        $response = curl_exec($ch);
        $this->responseInfo = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);

        $result = [
            'success' => !$error,
            'error'   => $error,
            'info'    => $this->responseInfo,
            'body'    => self::decodeBody($response)
        ];

        self::log($this->url, $result);
        return $result;
    }

    // --------------------------
    // Multi request paralel (batched)
    // --------------------------
    public static function sendMulti(array $requests)
    {
        $results = [];
        $total = count($requests);
        $limit = self::$concurrencyLimit;
        $chunks = array_chunk($requests, $limit);

        foreach ($chunks as $batch) {
            $mh = curl_multi_init();
            $handles = [];

            foreach ($batch as $key => $req) {
                if (!($req instanceof self)) {
                    throw new \Exception("All elements must be instances of CurlRequest");
                }

                $ch = curl_init();
                $req->prepareHandle($ch);
                curl_multi_add_handle($mh, $ch);
                $handles[$key] = ['ch' => $ch, 'req' => $req];
            }

            // Run multi
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh);
            } while ($running > 0);

            // Get results
            foreach ($handles as $key => $item) {
                $ch = $item['ch'];
                $req = $item['req'];

                $error = curl_error($ch);
                $info = curl_getinfo($ch);
                $body = curl_multi_getcontent($ch);

                $result = [
                    'success' => !$error,
                    'error'   => $error,
                    'info'    => $info,
                    'body'    => self::decodeBody($body)
                ];

                $results[] = $result;
                self::log($req->url, $result);

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }

            curl_multi_close($mh);
        }

        return $results;
    }

    // --------------------------
    // Internal helpers
    // --------------------------
    protected function prepareHandle(mixed $ch)
    {
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST  => $this->method
        ]);

        if (!empty($this->headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        }

        if (in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE']) && $this->data !== null) {
            if (is_array($this->data)) {
                $isJson = $this->isJsonHeader();
                curl_setopt($ch, CURLOPT_POSTFIELDS, $isJson ? json_encode($this->data) : http_build_query($this->data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->data);
            }
        }
    }

    protected function isJsonHeader()
    {
        foreach ($this->headers as $header) {
            if (stripos($header, 'Content-Type: application/json') !== false) {
                return true;
            }
        }
        return false;
    }

    protected static function decodeBody(mixed $body)
    {
        if (!self::$decodeJson) return $body;
        $decoded = json_decode($body, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
    }

    protected static function log(string $url, mixed $result)
    {
        if (!self::$logFile) return;

        $log = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $url);
        $log .= "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
        if ($result['error']) {
            $log .= "Error: " . $result['error'] . "\n";
        }
        $log .= "Response: " . (is_string($result['body']) ? $result['body'] : json_encode($result['body'], JSON_UNESCAPED_UNICODE)) . "\n\n";

        @file_put_contents(self::$logFile, $log, FILE_APPEND | LOCK_EX);
    }
}
