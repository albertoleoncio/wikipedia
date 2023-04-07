<?php

/**
 * A trait that provides methods to mock actions by a MediaWiki API endpoint
 */
trait WikiAphpiMock
{
    use WikiAphpiSee;

    /**
     * Generic function to do actions that require POST
     *
     * @param array $params Parameters to be send to the API
     * @return array API's response
     */
    public function do($params)
    {
        $do = $this->performRequest($params, true);
        if (isset($do['error'])) {
            throw new ContentRetrievalException($do['error']);
        }
        return $do;
    }

    /**
     * Edits a page.
     *
     * @param string $text Content of the page
     * @param sting $section Number of section to be edited, "append" to add at the end of page or null to edit the entire page.
     * @param bool $minor True makes the edit be marked as a minor edit and a bot edit.
     * @param type $summary Summary of edit.
     * @param type $page Page to be edited.
     * @return int The revision ID.
     */
    public function edit($text, $section, $minor, $summary, $page)
    {
        $section = var_export($section, true);
        $minor = var_export($minor, true);
        echo '<textarea rows="4" cols="50">'.htmlentities($text).'</textarea><br>';
        echo '<textarea rows="4" cols="50">'.htmlentities($section).'</textarea><br>';
        echo '<textarea rows="4" cols="50">'.htmlentities($minor).'</textarea><br>';
        echo '<textarea rows="4" cols="50">'.htmlentities($summary).'</textarea><br>';
        echo '<textarea rows="4" cols="50">'.htmlentities($page).'</textarea><hr>';
        return random_int('1000000', '9999999');
    }

    /**
     * Deletes a page
     *
     * @param string $page The title of the page to delete.
     * @param string $reason The reason for deleting the page.
     * @return bool True on success, false on failure.
     */
    public function delete($page, $reason)
    {
        return random_int('10000', '99999');
    }


    /**
     * Upload a file
     *
     * @param string $text Content of the associated page.
     * @param string $location Location of the file to upload.
     * @param string $summary Summary of edit.
     * @param string $page Name of file on wiki.
     * @return string Name of file on wiki.
     */
    public function upload($text, $location, $summary, $page)
    {
        return 'File:Test.jpg';
    }
}