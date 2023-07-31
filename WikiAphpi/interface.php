<?php

/**
 * Interface WikiAphpiInterface
 * This interface defines the methods to be implemented by classes that interact with the MediaWiki API.
 *
 * @package WikiAphpi
 */
interface WikiAphpiInterface {
	public function performRequest(array $params, bool $isPost, array $headers = []): array;
}
