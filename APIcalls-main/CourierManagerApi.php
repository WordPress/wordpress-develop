<?php

class CourierManagerApi
{
    const CREATE_AWB_ENDPOINT = "create_awb";
    const PRICE_AWB_ENDPOINT = "get_price";
    const PRINT_AWB_ENDPOINT = "print?pdf=true";
    const INFO_AWB_ENDPOINT = "get_info";
    const CITY_LIST_ENDPOINT = "list_cities";
    const GET_SERVICES_LIST= "list_services?type=";

    const SERVICES_TYPE_MAIN = "main";
    const SERVICES_TYPE_EXTRA = "extra";

    /** @var string */
    private $apiUrl;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $error;

    /**
     * CourierManagerApi constructor.
     * @param string $apiUrl
     * @param string $apiKey
     */
    public function __construct($apiUrl, $apiKey)
    {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function createAwb($data)
    {
        $response = $this->callApi(self::CREATE_AWB_ENDPOINT, $data);
        return json_decode($response);
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function priceAwb($data)
    {
        $response = $this->callApi(self::PRICE_AWB_ENDPOINT, $data);
        return json_decode($response);
    }

    /**
     * @param $awbId
     * @return bool|string
     */
    public function printAwb($awbId)
    {
        return $this->callApi(self::PRINT_AWB_ENDPOINT, array(
            "awbno" => $awbId
        ));
    }

    /**
     * @param $awbId
     * @return mixed
     */
    public function infoAwb($awbId)
    {
        $response = $this->callApi(self::INFO_AWB_ENDPOINT, array(
            "awbno" => $awbId
        ));
        return json_decode($response);
    }

    /**
     * @return mixed
     */
    public function getCityList()
    {
        $response = $this->callApi(self::CITY_LIST_ENDPOINT);
        return json_decode($response);
    }

    /**
     * @param string $type
     * @return mixed
     */
    public function getServicesList($type = self::SERVICES_TYPE_MAIN)
    {
        $response = $this->callApi(self::GET_SERVICES_LIST . $type);
        return json_decode($response);
    }

    /**
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param $endpoint
     * @param array $params
     * @return bool|mixed|string
     */
    private function callApi($endpoint, $params = array())
    {
        $curl = curl_init();

        $apiKeyQuery = "?api_key=" . $this->apiKey;
        if (strpos($endpoint, '?') !== false) {
            $apiKeyQuery = "&api_key=" . $this->apiKey;
        }

        curl_setopt($curl, CURLOPT_URL, $this->apiUrl . "/API/" . $endpoint . $apiKeyQuery);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);

        if (!empty($params)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        // Protocol error
        if ($err) {
            $this->error = "There was an error connecting to the API. Response Error: " . $err;
            error_log($err);
            return false;
        }

        // Error if http code not is 200
        if ($httpCode !== 200) {
            switch ($httpCode) {
                case 404:
                    $this->error = "The API URL seems to be incorrect.";
                    break;
                case 500:
                    $this->error = "There is a server issue at the API level, please try again later.";
                    break;
                default:
                    $this->error = "There was an error connecting to the API. Error code: " . $httpCode;
                    break;
            }

            error_log("API connect error, HTTP status: " . $httpCode);
        }

        // Bad login error
        if (strpos($response, 'BAD_LOGIN') !== false) {
            $response = json_decode($response);
            $this->error = $response->message;
            return false;
        }

        return $response;
    }
}
