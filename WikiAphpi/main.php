<?php
require_once 'interface.php';
require_once 'exception.php';
require_once 'see.php';
require_once 'do.php';
require_once 'behalf.php';
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
    public function performRequest(array $params, bool $isPost, array $headers = []): array
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
        if (!empty($headers)) {
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
    public function performRequest(array $params, bool $isPost, array $headers = []): array
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
        if (!empty($headers)) {
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
    public function performRequest(array $params, bool $isPost, array $headers = []): array
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
        if (!empty($headers)) {
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
 * for making requests to a MediaWiki API with OAuth authentication.
 *
 * @see see.php
 * @see do.php
 * @uses WikiAphpiSee
 * @uses WikiAphpiDo
 * @uses WikiAphpiBehalf
 */
class WikiAphpiOAuth implements WikiAphpiInterface
{
    use WikiAphpiBehalf;

    /** @var string The base URL for API requests. */
    private $endpoint;

    /** @var string The base URL for OAuth requests. */
    private $endpointOAuth;

    /** @var string The consumer key for OAuth authentication. */
    private $consumerKey;

    /** @var string The secret key for OAuth authentication. */
    private $consumerSecret;

    /** @var string The session key for OAuth authentication. */
    private $sessionKey;

    /** @var string The session secret for OAuth authentication. */
    private $sessionSecret;
    
    /**
     * Constructor for the WikiAphpiOAuth class with setup.
     *
     * Initializes a new instance of the WikiAphpiOAuth class and sets the endpoint, consumer key, and consumer secret.
     * Additionally, it sets the OAuth endpoint for OAuth requests and performs setup to set up the session cookie and
     * fetch access tokens if this is the callback from requesting authorization.
     *
     * @param string $endpoint      The base URL for API requests.
     * @param string $consumerKey   The consumer key to use for API requests.
     * @param string $consumerSecret The secret key to use for API requests.
     */
    public function __construct($endpoint, $consumerKey, $consumerSecret)
    {
        $this->endpoint = $endpoint;
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->endpointOAuth = 'https://meta.wikimedia.org/w/index.php';

        // perform setup in constructor
        $this->setup();
    }

    /**
     * Sends a cURL request to the specified endpoint with the provided parameters.
     *
     * This method sends a cURL request to the specified endpoint with the provided parameters and returns the response
     * in PHP format. It prepares headers, sets the request type and parameters, and handles any errors that occur during
     * the cURL request. Additionally, it unserializes the result to PHP format if possible.
     *
     * @param array $params   The parameters for the API request.
     * @param bool  $isPost   A boolean indicating whether the request is a POST request.
     * @param bool  $headers  A boolean indicating whether headers are needed in the cURL request.
     *
     * @return array The response from the API request in PHP format.
     *
     * @throws ContentRetrievalException If there is an error during the cURL request.
     * @throws InvalidArgumentException If the API response cannot be unserialized to PHP format.
     */
    public function performRequest(array $params, bool $isPost, array $headers = []): array
    {
        $url = $this->endpoint;
        $ch = curl_init();

        // making sure to request the right format
        $params['format'] = 'php';

        // prepare headers
        $oauthHeaders = array(
            'oauth_consumer_key'     => $this->consumerKey,
            'oauth_token'            => $_SESSION['sessionKey'] ?? '',
            'oauth_version'          => '1.0',
            'oauth_nonce'            => bin2hex(random_bytes(16)),
            'oauth_timestamp'        => time(),
            'oauth_signature_method' => 'HMAC-SHA1',
        );
        if (!empty($headers)) $oauthHeaders += $headers;

        $signature = $this->signRequest(
            $isPost ? 'POST' : 'GET', 
            $url,
            $params + $oauthHeaders 
        );
        $oauthHeaders['oauth_signature'] = $signature;
        $authHeader = array();
        foreach ( $oauthHeaders as $k => $v ) {
            $authHeader[] = rawurlencode( $k ) . '="' . rawurlencode( $v ) . '"';
        }
        $authHeader = 'Authorization: OAuth ' . join( ', ', $authHeader );

        // set the request type and parameters
        if ($isPost) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            $url .= "?" . http_build_query($params);
        }

        // set other curl options as needed
        curl_setopt($ch, CURLOPT_HTTPHEADER, [$authHeader]);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // execute the curl request and handle any errors
        $result = curl_exec($ch);
        if ($result === false) {
            throw new ContentRetrievalException(curl_error($ch));
        }

        // close the curl handle and return the result
        curl_close($ch);
        $unserializedResult = unserialize($result) ?? false;
        if ($unserializedResult === false) {
            throw new InvalidArgumentException("Error unserializing. API' format is PHP?");
        }

        return $unserializedResult;
    }

    /**
     * Sets up the session cookie and fetches access tokens if this is the callback from requesting authorization.
     *
     * This method sets up the session cookie with the appropriate parameters. It loads the user token (request or access)
     * from the session and fetches the access token if this is the callback from requesting authorization.
     */
    public function setup()
    {
        // Setup the session cookie
        session_name( str_replace('.php', '', $_SERVER['SCRIPT_NAME']) );
        $params = session_get_cookie_params();
        session_set_cookie_params(
            $params['lifetime'],
            dirname( $_SERVER['SCRIPT_NAME'] )
        );

        // Load the user token (request or access) from the session
        session_start();
        $this->sessionKey = '';
        $this->sessionSecret = '';
        if ( isset( $_SESSION['sessionKey'] ) ) {
            $this->sessionKey = $_SESSION['sessionKey'];
            $this->sessionSecret = $_SESSION['sessionSecret'];
        }
        session_write_close();

        // Fetch the access token if this is the callback from requesting authorization
        if ( isset( $_GET['oauth_verifier'] ) && $_GET['oauth_verifier'] ) {
            $this->gotLogin();
        } elseif ( isset( $_GET['oauth'] ) && $_GET['oauth'] === 'seek' ){
            $this->seekLogin();
        }
    }
}