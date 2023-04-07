<?php
require_once 'interface.php';
require_once 'exception.php';
require_once 'see.php';
require_once 'do.php';
require_once 'mock.php';

/**
 * Represents a class that implements the WikiAphpiInterface and provides functionality
 * for making requests to a MediaWiki API while being logged out.
 * @see see.php
 * @uses WikiAphpiSee
 */
class WikiAphpiUnlogged implements WikiAphpiInterface
{
    use WikiAphpiSee;

    /** @var string The base URL for API requests. */
    private $endpoint;

    /**
     * Constructor for WikiAphpi class with login.
     *
     * @param string $endpoint The base URL for API requests.
     * @param string $userAPI The username to use for API requests.
     * @param string $passAPI The password to use for API requests.
     */
    public function __construct($endpoint)
    {
        $this->endpoint = $endpoint;
    }

    /**
     * Sends a curl request to the specified endpoint with the provided parameters.
     *
     * @param string $endpoint The endpoint to send the request to.
     * @param array $params The parameters to include in the request.
     * @param bool $isPost Whether the request should be a POST request (true) or a GET request (false).
     * @return array The response from the curl request.
     * @throws Exception if there is an error sending the curl request.
     */
    public function performRequest(array $params, bool $isPost, bool $headers = false): array
    {
        $url = $this->endpoint;
        $ch = curl_init();

        // making sure to request the right format
        $params['format'] = 'php';

        // set the request type and parameters
        if ($isPost) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            $url .= "?" . http_build_query($params);
        }

        // optional for specific headers
        if ($headers !== false) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // set other curl options as needed
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // execute the curl request and handle any errors
        $result = curl_exec($ch);
        if ($result === false) {
            throw new ContentRetrievalException(curl_error($ch));
        }

        // close the curl handle and return the result
        curl_close($ch);
        $result2 = @unserialize($result);
        if ($result2 === false) {
            throw new InvalidArgumentException("Error unserializing. API' format is PHP?");
        }

        return $result2;
    }
}

/**
 * Represents a class that implements the WikiAphpiInterface and provides functionality
 * for making requests to a MediaWiki API while being logged in.
 * @see see.php
 * @see do.php
 * @uses WikiAphpiSee
 * @uses WikiAphpiDo
 */
class WikiAphpiLogged implements WikiAphpiInterface
{
	use WikiAphpiDo;

	/** @var string The base URL for API requests. */
    private $endpoint;

    /** @var string The username to use for API requests. */
    private $userAPI;

    /** @var string The password to use for API requests. */
    private $passAPI;
	
    /**
     * Constructor for WikiAphpi class with login.
     *
     * @param string $endpoint The base URL for API requests.
     * @param string $userAPI The username to use for API requests.
     * @param string $passAPI The password to use for API requests.
     */
    public function __construct($endpoint, $userAPI, $passAPI)
    {
        $this->endpoint = $endpoint;
        $this->userAPI = $userAPI;
        $this->passAPI = $passAPI;

        // perform login in constructor
        $this->login();
    }

	/**
     * Sends a curl request to the specified endpoint with the provided parameters.
     *
     * @param string $endpoint The endpoint to send the request to.
     * @param array $params The parameters to include in the request.
     * @param bool $isPost Whether the request should be a POST request (true) or a GET request (false).
     * @return array The response from the curl request.
     * @throws Exception if there is an error sending the curl request.
     */
    public function performRequest(array $params, bool $isPost, bool $headers = false): array
    {
        $url = $this->endpoint;
        $ch = curl_init();
        $path = posix_getpwuid(posix_getuid())['dir']."/".$this->userAPI.".inc";

        // making sure to request the right format
        $params['format'] = 'php';

        // set the request type and parameters
        if ($isPost) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            $url .= "?" . http_build_query($params);
        }

        // optional for specific headers
        if ($headers !== false) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // set other curl options as needed
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $path);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $path);

        // execute the curl request and handle any errors
        $result = curl_exec($ch);
        if ($result === false) {
            throw new ContentRetrievalException(curl_error($ch));
        }

        // close the curl handle and return the result
        curl_close($ch);
        $result2 = @unserialize($result);
        if ($result2 === false) {
            throw new InvalidArgumentException("Error unserializing. API' format is PHP?");
        }

        return $result2;
    }


    /**
     * Logs in to the API using the provided username and password.
     *
     * @return string The username of the logged-in user.
     */
    private function login()
    {
        //Verifica maxlag e usuário atual com base no cookie armazenado
        $logged_params = [
            "action"    => "query",
            "meta"      => "userinfo",
            "uiprop"    => "rights",
            "format"    => "php",
            "maxlag"    => "5"
        ];
        $logged_api = $this->performRequest($logged_params, false);
        $logged_data = $logged_api["query"]["userinfo"] ?? false;

        if ($logged_data === false) {
            throw new ContentRetrievalException($logged_api);
        }

        //Retorna caso já exista sessão válida
        if (!isset($logged_data["anon"])) {
            return $logged_data["name"];
        }

        //Coleta token para login
        $token_params = [
            "action"    => "query",
            "meta"      => "tokens",
            "type"      => "login",
            "format"    => "php"
        ];
        $token_api = $this->performRequest($token_params, false);
        $token = $token_api["query"]["tokens"]["logintoken"] ?? false;

        //Interrompe script caso ocorra erro na obtenção do token de login
        if ($token === false) {
            throw new ContentRetrievalException($token_api);
        }

        //Executa login
        $login_params = [
            "action"        => "login",
            "lgname"        => $this->userAPI,
            "lgpassword"    => $this->passAPI,
            "lgtoken"       => $token,
            "format"        => "php"
        ];
        $login_api = $this->performRequest($login_params, true);
        $logged_username = $login_api["login"]["lgusername"] ?? false;

        //Interrompe script caso login não tenha ocorrido
        if ($logged_username === false) {
            throw new ContentRetrievalException($login_api);
        }

        //Retorna nome de usuário logado
        return $logged_username;
    }
}

/**
 * Represents a class that implements the WikiAphpiInterface and mocks the functionality
 * for making requests to a MediaWiki API.
 */
class WikiAphpiTest implements WikiAphpiInterface
{
    use WikiAphpiMock;

    /**
     * Constructor for WikiAphpi class with login.
     *
     * @param string $endpoint The base URL for API requests.
     * @param string $userAPI The username to use for API requests.
     * @param string $passAPI The password to use for API requests.
     */
    public function __construct($endpoint, $userAPI, $passAPI)
    {
        $this->endpoint = $endpoint;
    }
	
	/**
     * Sends a curl request to the specified endpoint with the provided parameters.
     *
     * @param string $endpoint The endpoint to send the request to.
     * @param array $params The parameters to include in the request.
     * @param bool $isPost Whether the request should be a POST request (true) or a GET request (false).
     * @return array The response from the curl request.
     * @throws Exception if there is an error sending the curl request.
     */
    public function performRequest(array $params, bool $isPost, bool $headers = false): array
    {
        $url = $this->endpoint;
        $ch = curl_init();

        // making sure to request the right format
        $params['format'] = 'php';

        // set the request type and parameters
        if ($isPost) {
            curl_close($ch);
            return [];
        } else {
            $url .= "?" . http_build_query($params);
        }

        // optional for specific headers
        if ($headers !== false) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // set other curl options as needed
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // execute the curl request and handle any errors
        $result = curl_exec($ch);
        if ($result === false) {
            throw new ContentRetrievalException(curl_error($ch));
        }

        // close the curl handle and return the result
        curl_close($ch);
        $result2 = @unserialize($result);
        if ($result2 === false) {
            throw new InvalidArgumentException("Error unserializing. API' format is PHP?");
        }

        return $result2;
    }
}