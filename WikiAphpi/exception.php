<?php

/**
 * Custom exception class for when content cannot be retrieved
 */
class ContentRetrievalException extends Exception
{

    /**
     * Constructor for ContentRetrievalException.
     *
     * @param mixed $resultApi The result of the API request.
     * @return void
     */
    public function __construct($resultApi)
    {
        $message = 'Content retrieval failed: ' . print_r($resultApi, true);
        parent::__construct($message);
    }
}