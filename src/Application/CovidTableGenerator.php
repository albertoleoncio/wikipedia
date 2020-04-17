<?php

namespace App\Application;

use App\Infrastructure\CovidCsvReader;

class CovidTableGenerator
{
	private $input;
	private $reader;
	private $parser;

	public function __construct(ParserInterface $parser, string $outputFile)
	{
		$this->input = __DIR__ . '/../../data/current.csv';
		$this->output = $outputFile;

		$this->reader = new CovidCsvReader;
		$this->parser = $parser;
	}

	public function execute()
	{
		$reportedCases = $this->reader->read($this->input);

		$contents = $this->parser->parse($reportedCases);

		file_put_contents($this->output, $contents);
	}
}