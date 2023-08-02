<?php

/**
 * A trait that provides methods to perform actions by a MediaWiki API endpoint using OAuth
 */
trait WikiAphpiBehalf
{

    use WikiAphpiDo;

    /**
     * Generates the OAuth signature for the API request.
     *
     * This method generates the OAuth signature for the API request using the specified HTTP method, URL, and parameters.
     * It normalizes the endpoint URL and combines the parameters with the URL's query parameters. The resulting parameters
     * are sorted, and the OAuth signature is calculated using the HMAC-SHA1 algorithm with the consumer secret and session
     * secret as the key. The signature is then base64-encoded and returned as the final result.
     *
     * @param string $method The HTTP method for the API request (GET, POST, etc.).
     * @param string $url    The URL for the API request.
     * @param array  $params An associative array of parameters for the API request.
     *
     * @return string The base64-encoded HMAC-SHA1 signature for the API request.
     */
    public function signRequest($method, $url, $params = array()) {

        // Parse the URL to extract its components
        $parts = parse_url($url);

        // Normalize the endpoint URL
        $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
        $host = isset($parts['host']) ? $parts['host'] : '';
        $port = isset($parts['port']) ? $parts['port'] : ($scheme == 'https' ? '443' : '80');
        $path = isset($parts['path']) ? $parts['path'] : '';
        if (($scheme == 'https' && $port != '443') ||
            ($scheme == 'http' && $port != '80') 
        ) {
            // Only include the port if it's not the default
            $host = "$host:$port";
        }

        // Combine the URL query parameters from the parsed URL and the additional parameters
        $pairs = array();
        parse_str(isset($parts['query']) ? $parts['query'] : '', $query);
        $query += $params;
        unset($query['oauth_signature']); // Remove the existing signature parameter if present
        if ($query) {
            $query = array_combine(
                // rawurlencode follows RFC 3986 since PHP 5.3
                array_map('rawurlencode', array_keys($query)),
                array_map('rawurlencode', array_values($query))
           );
            ksort($query, SORT_STRING);
            foreach ($query as $k => $v) {
                $pairs[] = "$k=$v";
            }
        }

        // Prepare the base string to be signed
        $toSign  = rawurlencode(strtoupper($method));
        $toSign .= '&';
        $toSign .= rawurlencode("$scheme://$host$path");
        $toSign .= '&';
        $toSign .= rawurlencode(join('&', $pairs));

        // Prepare the signing key
        $key  = rawurlencode($this->consumerSecret);
        $key .= '&';
        $key .= rawurlencode($this->sessionSecret);

        // Generate the HMAC-SHA1 signature and return it as a base64-encoded string
        return base64_encode(hash_hmac('sha1', $toSign, $key, true));
    }

    /**
     * Initiates the OAuth login process and redirects the user for authorization.
     *
     * This method initiates the OAuth login process by first fetching a request token from the OAuth endpoint.
     * It then saves the request token in the session for later use. The user is then redirected to the authorization
     * page, where they can grant access to the application. The method redirects the user to the authorization page using
     * the 'Location' header.
     *
     * @throws ContentRetrievalException When there is an error during the request token retrieval or the response is invalid.
     */
    public function seekLogin() {

        // First, we need to fetch a request token.

        // Clear the session secret.
        $this->sessionSecret = '';

        // OAuth parameters required for obtaining a request token.
        // oob: Out-of-band callback for user authentication
        $params = [
            'title'                  => 'Special:OAuth/initiate',
            'format'                 => 'json',
            'oauth_callback'         => 'oob',
            'oauth_consumer_key'     => $this->consumerKey,
            'oauth_version'          => '1.0',
            'oauth_nonce'            => bin2hex(random_bytes(16)),
            'oauth_timestamp'        => time(),
            'oauth_signature_method' => 'HMAC-SHA1'
        ];

        // Build the URL with parameters to request the request token.
        $preurl  = $this->endpointOAuth . '?' . http_build_query($params);
        $url = $preurl .'&'. http_build_query([
            'oauth_signature' => $this->signRequest('GET', $preurl)
        ]);

        // Initialize cURL and set the options for the request.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Execute the cURL request.
        $data = curl_exec($ch);

        // Check for cURL errors.
        if (!$data) {
            throw new ContentRetrievalException(['Curl error: ' . htmlspecialchars(curl_error($ch))]);
        }
        curl_close($ch);

        // Decode the JSON response containing the request token.
        $token = json_decode($data);

        // Check for errors in the response.
        if (is_object($token) && isset($token->error)) {
            throw new ContentRetrievalException(['Error retrieving token: ' . htmlspecialchars($token->error) . '<br>' . htmlspecialchars($token->message)]);
        }
        if (!is_object($token) || !isset($token->key) || !isset($token->secret)) {
            throw new ContentRetrievalException(['Invalid response from token request']);
        }

        // Now we have the request token, we need to save it for later in the session.
        session_start();
        $_SESSION['sessionKey'] = $token->key;
        $_SESSION['sessionSecret'] = $token->secret;
        session_write_close();

        // Then we send the user off to authorize.
        $url  = 'https://meta.wikimedia.org/wiki/Special:OAuth/authorize';
        $url .= '?';
        $url .= http_build_query([
            'oauth_token'           => $token->key,
            'oauth_consumer_key'    => $this->consumerKey,
        ]);

        // Redirect the user to the authorization page.
        header("Location: $url");
    }

    /**
     * Handles the callback after successful OAuth authorization.
     *
     * This method is called when the user has successfully authorized the application during the OAuth process. It retrieves
     * the OAuth verifier from the URL parameters and uses it, along with the consumer key and request token, to request the
     * access token. Once the access token is obtained, it is saved in the session for future use. The method then redirects
     * the user back to the current page to complete the login process.
     *
     * @throws ContentRetrievalException When there is an error during the API request or the response is invalid.
     * @return void
     */
    public function gotLogin() {

        // OAuth parameters required for token request
        $params = [
            'title'                  => 'Special:OAuth/token',
            'format'                 => 'json',
            'oauth_verifier'         => $_GET['oauth_verifier'],
            'oauth_consumer_key'     => $this->consumerKey,
            'oauth_token'            => $this->sessionKey,
            'oauth_version'          => '1.0',
            'oauth_nonce'            => bin2hex(random_bytes(16)),
            'oauth_timestamp'        => time(),
            'oauth_signature_method' => 'HMAC-SHA1',
        ];

        // Build the URL with parameters to request the access token
        $preurl  = $this->endpointOAuth . '?' . http_build_query($params);
        $url = $preurl .'&'. http_build_query(
            ['oauth_signature' => $this->signRequest('GET', $preurl) ]
        );

        // Initialize cURL and set the options for the request
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

        // Execute the cURL request
        $data = curl_exec( $ch );

        // Check for cURL errors
        if ( !$data ) {
            throw new ContentRetrievalException(['Curl error: ' . htmlspecialchars(curl_error($ch))]);
        }
        curl_close( $ch );

        // Decode the JSON response containing the access token
        $token = json_decode( $data );

        // Check for errors in the response
        if ( is_object( $token ) && isset( $token->error ) ) {
            throw new ContentRetrievalException(['Error retrieving token: ' . htmlspecialchars( $token->error ) . '<br>' . htmlspecialchars( $token->message )]);
        }
        if ( !is_object( $token ) || !isset( $token->key ) || !isset( $token->secret ) ) {
            throw new ContentRetrievalException(['Invalid response from token request']);
        }

        // Save the access token and secret in the session for future use
        session_start();
        $_SESSION['sessionKey'] = $this->sessionKey = $token->key;
        $_SESSION['sessionSecret'] = $this->sessionSecret = $token->secret;
        session_write_close();

        // Redirect the user to the current page after successful token retrieval
        header("Location: {$_SERVER['SCRIPT_NAME']}");
    }


    /**
     * Checks if the user is logged in using OAuth.
     *
     * This method verifies if the user is logged in using OAuth by making a request to the MediaWiki API with the provided
     * OAuth parameters. It retrieves the username from the response payload, indicating that the user is authenticated. If
     * the user is not logged in or the request fails, the method returns false.
     *
     * @throws ContentRetrievalException When there is an error during the API request or the response is invalid.
     * @return string|false The username of the logged-in user if authenticated, or false otherwise.
     */
    public function checkLogin() {

        // OAuth parameters needed for the request
        $params = [
            'title'                     => 'Special:OAuth/identify',
            'oauth_consumer_key'        => $this->consumerKey,
            'oauth_token'               => $this->sessionKey,
            'oauth_version'             => '1.0',
            'oauth_nonce'               => bin2hex(random_bytes(16)),
            'oauth_timestamp'           => time(),
            'oauth_signature_method'    => 'HMAC-SHA1',
        ];

        // Build the URL with parameters to make the OAuth request
        $preurl  = str_replace('api', 'index', $this->endpoint) . '?' . http_build_query($params);
        $url = $preurl .'&'. http_build_query(
            ['oauth_signature' => $this->signRequest('GET', $preurl) ]
        );

        // Build the OAuth Authorization header
        $header = array();
        foreach ( $params as $k => $v ) {
            $header[] = rawurlencode( $k ) . '="' . rawurlencode( $v ) . '"';
        }
        $header = 'Authorization: OAuth ' . join( ', ', $header );

        // Initialize cURL and set the options for the request
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array( $header ) );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

        // Execute the cURL request
        $data = curl_exec( $ch );

        // Check for cURL errors
        if ( !$data ) {
            throw new ContentRetrievalException(['Curl error: ' . htmlspecialchars( curl_error( $ch ) )]);
        }
        curl_close($ch);

        // Check if the response indicates unauthorized access
        $err = json_decode( $data );
        if ( 
            is_object( $err ) && 
            isset( $err->error ) && 
            $err->error === 'mwoauthdatastore-access-token-not-found' 
        ) {
            return false;
        }

        // Verify if response is a JWT token, splitting into 
        // three parts: header, payload, and signature
        $fields = explode( '.', $data );
        if ( count( $fields ) !== 3 ) {
            throw new ContentRetrievalException(['Invalid identify response: ' . htmlspecialchars( $data )]);
        }

        // Validate the header. MWOAuth always returns alg "HS256".
        $header = base64_decode( strtr( $fields[0], '-_', '+/' ), true );
        if ( $header !== false ) {
            $header = json_decode( $header );
        }
        if ( 
            !is_object( $header ) || 
            $header->typ !== 'JWT' || 
            $header->alg !== 'HS256' 
        ) {
            throw new ContentRetrievalException(['Invalid header in identify response: ' . htmlspecialchars( $data )]);
        }

        // Verify the signature using the consumer secret
        $sig = base64_decode( strtr( $fields[2], '-_', '+/' ), true );
        $check = hash_hmac( 'sha256', $fields[0] . '.' . $fields[1], $this->consumerSecret, true );
        if ( $sig !== $check ) {
            throw new ContentRetrievalException([
                'JWT signature validation failed: ' . htmlspecialchars( $data ), 
                base64_encode($sig), 
                base64_encode($check)
            ]);
        }

        // Decode the payload and retrieve the username
        $payload = base64_decode( strtr( $fields[1], '-_', '+/' ), true );
        if ( $payload !== false ) {
            $payload = json_decode( $payload, true );
        }
        if ( !is_array( $payload ) ) {
            throw new ContentRetrievalException(['Invalid payload in identify response: ' . htmlspecialchars( $data )]);
        }

        // Return the username retrieved from the payload
        return $payload;
    }
}