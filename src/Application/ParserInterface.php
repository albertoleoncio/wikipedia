<?php

namespace App\Application;

use App\Domain\ReportedCases;

interface ParserInterface
{
	public function parse(ReportedCases $cases): string;
}