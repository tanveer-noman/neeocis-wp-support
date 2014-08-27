<?php
/**
 * @author: Tanveer Noman
 * @description: This class is use to implement paypal on any cakephp application 
 * @license http://opensource.org/licenses/mit-license.html MIT
 * @copyright (c) 2010, Tanveer Noman
 * @version 1.0
**/

// test comment 
class HTTPRequest {

    private $host;
    private $path;
    private $method;
    private $port;
    private $rawhost;
    private $header;
    private $content;
    private $parsedHeader;

    function __construct($host, $path, $method = 'POST', $ssl = false, $port = 0) {
        $this->host = $host;
        $this->rawhost = $ssl ? ("ssl://" . $host) : $host;
        $this->path = $path;
        $this->method = strtoupper($method);
        if ($port) {
            $this->port = $port;
        } else {
            if (!$ssl)
                $this->port = 80;
            else
                $this->port = 443;
        }
    }

    public function connect($data = '') {
        $fp = fsockopen($this->rawhost, $this->port);
        if (!$fp)
            return false;
        fputs($fp, "$this->method $this->path HTTP/1.1\r\n");
        fputs($fp, "Host: $this->host\r\n");
        fputs($fp, "Content-length: " . strlen($data) . "\r\n");
        fputs($fp, "Connection: close\r\n");
        fputs($fp, "\r\n");
        fputs($fp, $data);

        $responseHeader = '';
        $responseContent = '';

        do {
            $responseHeader.= fread($fp, 1);
        } while (!preg_match('/\\r\\n\\r\\n$/', $responseHeader));


        if (!strstr($responseHeader, "Transfer-Encoding: chunked")) {
            while (!feof($fp)) {
                $responseContent.= fgets($fp, 128);
            }
        } else {

            while ($chunk_length = hexdec(fgets($fp))) {
                $responseContentChunk = '';

                $read_length = 0;

                while ($read_length < $chunk_length) {
                    $responseContentChunk .= fread($fp, $chunk_length - $read_length);
                    $read_length = strlen($responseContentChunk);
                }

                $responseContent.= $responseContentChunk;

                fgets($fp);
            }
        }

        $this->header = chop($responseHeader);
        $this->content = $responseContent;
        $this->parsedHeader = $this->headerParse();

        $code = intval(trim(substr($this->parsedHeader[0], 9)));

        return $code;
    }

    function headerParse() {
        $h = $this->header;
        $a = explode("\r\n", $h);
        $out = array();
        foreach ($a as $v) {
            $k = strpos($v, ':');
            if ($k) {
                $key = trim(substr($v, 0, $k));
                $value = trim(substr($v, $k + 1));
                if (!$key)
                    continue;
                $out[$key] = $value;
            } else {
                if ($v)
                    $out[] = $v;
            }
        }
        return $out;
    }

    public function getContent() {
        return $this->content;
    }

    public function getHeader() {
        return $this->parsedHeader;
    }

}

class PaypalComponent extends Object {

    var $endpoint;
    var $host;
    var $gate;
    var $returnSuccessUrl;
    var $returnCancelUrl;
    var $paypalUserName;
    var $paypalPassword;
    var $paypalSignature;

    function __construct($real = false) {
        $this->endpoint = '/nvp';
        if ($real) {
            /*
             * Production Credentials
             */
            $this->host = "api-3t.paypal.com";
            $this->gate = 'https://www.paypal.com/cgi-bin/webscr?';
        } else {
            /*
             * Sendbox Credentials for testing purpose
             */
            $this->host = "api-3t.sandbox.paypal.com";
            $this->gate = 'https://www.sandbox.paypal.com/cgi-bin/webscr?';
        }
    }

    /**
     * @return string URL of the "success" page
     */
    function getReturnTo() {
        return $this->returnSuccessUrl;
    }

    /**
     * @return string URL of the "cancel" page
     */
    function getReturnToCancel() {
        return $this->returnCancelUrl;
    }

    /**
     * @return HTTPRequest
     */
    function response($data) {
        $r = new HTTPRequest($this->host, $this->endpoint, 'POST', true);
        $result = $r->connect($data);
        if ($result->paypalUserName)
            $data['PWD'] = $this->paypalPassword;
        $data['SIGNATURE'] = $this->paypalSignature;
        $data['VERSION'] = '51.0';
        if (!function_exists('http_build_query')) {
            $query = $this->http_build_query_php4($data);
        } else {
            $query = http_build_query($data);
        }
        return $query;
    }

    function http_build_query_php4($data, $b = '', $c = 0) {
        if (!is_array($data))
            return false;
        foreach ((array) $data as $key => $value) {
            $pair[] = $key . "=" . urlencode($value);
        }
        return implode("&amp;", $pair);
    }

    /**
     * Main payment function
     *
     * If OK, the customer is redirected to PayPal gateway
     * If error, the error info is returned
     *
     * @param float $amount Amount (2 numbers after decimal point)
     * @param string $desc Item description
     * @param string $invoice Invoice number (can be omitted)
     * @param string $currency 3-letter currency code (USD, GBP, CZK etc.)
     *
     * @return array error info
     */
    function doExpressCheckout($amount, $desc, $invoice = '', $currency = 'USD') {
        $data = array(
            'PAYMENTACTION' => 'Sale',
            'AMT' => $amount,
            'RETURNURL' => $this->getReturnTo(),
            'CANCELURL' => $this->getReturnToCancel(),
            'DESC' => $desc,
            'NOSHIPPING' => "1",
            'ALLOWNOTE' => "1",
            'CURRENCYCODE' => $currency,
            'METHOD' => 'SetExpressCheckout');

        $data['CUSTOM'] = $amount . '|' . $currency . '|' . $invoice;
        if ($invoice)
            $data['INVNUM'] = $invoice;

        $query = $this->buildQuery($data);

        $result = $this->response($query);

        if (!$result)
            return false;
        $response = $result->getContent();
        $return = $this->responseParse($response);
        if ($return['ACK'] == 'Success') {
            header('Location: ' . $this->gate . 'cmd=_express-checkout&amp;useraction=commit&amp;token=' . $return['TOKEN'] . '');
            die();
        }
        return($return);
    }

    function getCheckoutDetails($token) {
        $data = array(
            'TOKEN' => $token,
            'METHOD' => 'GetExpressCheckoutDetails');
        $query = $this->buildQuery($data);

        $result = $this->response($query);

        if (!$result)
            return false;
        $response = $result->getContent();
        $return = $this->responseParse($response);
        return($return);
    }

    function doPayment() {
        $token = $_GET['token'];
        $payer = $_GET['PayerID'];
        $details = $this->getCheckoutDetails($token);
        if (!$details)
            return false;
        list($amount, $currency, $invoice) = explode('|', $details['CUSTOM']);
        $data = array(
            'PAYMENTACTION' => 'Sale',
            'PAYERID' => $payer,
            'TOKEN' => $token,
            'AMT' => $amount,
            'CURRENCYCODE' => $currency,
            'METHOD' => 'DoExpressCheckoutPayment');
        $query = $this->buildQuery($data);

        $result = $this->response($query);

        if (!$result)
            return false;
        $response = $result->getContent();
        $return = $this->responseParse($response);

        /*
         * [AMT] => 10.00
         * [CURRENCYCODE] => USD
         * [PAYMENTSTATUS] => Completed
         * [PENDINGREASON] => None
         * [REASONCODE] => None
         */

        return($return);
    }

    function getScheme() {
        $scheme = 'http';
        if (isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on') {
            $scheme .= 's';
        }
        return $scheme;
    }

    function responseParse($resp) {
        $a = explode("&amp;", $resp);
        $out = array();
        foreach ($a as $v) {
            $k = strpos($v, '=');
            if ($k) {
                $key = trim(substr($v, 0, $k));
                $value = trim(substr($v, $k + 1));
                if (!$key)
                    continue;
                $out[$key] = urldecode($value);
            } else {
                $out[] = $v;
            }
        }
        return $out;
    }

    function doDirectPayment($Order) {

        $Order['METHOD'] = 'DoDirectPayment';

        $query = $this->buildQuery($Order);

        $result = $this->response($query);
        if (!$result)
            return false;
        $response = $result->getContent();
        return $this->responseParse($response);
    }

}

?>
