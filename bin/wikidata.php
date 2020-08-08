<?php

class SPARQLQueryDispatcher
{
    private $endpointUrl;

    public function __construct(string $endpointUrl)
    {
        $this->endpointUrl = $endpointUrl;
    }

    public function query(string $sparqlQuery): array
    {

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/sparql-results+json',
                    'User-Agent: WDQS-example PHP/' . PHP_VERSION, // TODO adjust this; see https://w.wiki/CX6
                ],
            ],
        ];
        $context = stream_context_create($opts);

        $url = $this->endpointUrl . '?query=' . urlencode($sparqlQuery);
        $response = file_get_contents($url, false, $context);
        return json_decode($response, true);
    }
}

$endpointUrl = 'https://query.wikidata.org/sparql';
$sparqlQueryString = <<< 'SPARQL'
SELECT DISTINCT ?pop ?itemLabel 
WHERE 
{   
  {?item wdt:P31  wd:Q3624078} 
    UNION 
  {?item wdt:P31/wdt:P279* wd:Q1763527}
    UNION
  {?item wdt:P31  wd:Q15239622}
    UNION
  {?item wdt:P31  wd:Q46395}
    UNION
  {?item wdt:P31  wd:Q15634554}
    UNION
  {?item wdt:P31  wd:Q185086}
    UNION
  {?item wdt:P31  wd:Q719487}
    UNION
  {?item wdt:P31  wd:Q783733}
    UNION
  {?item wdt:P31  wd:Q26934845}
    UNION
  {?item wdt:P31  wd:Q779415}
    UNION
  {?item wdt:P31  wd:Q3648563}
    UNION
  {?item wdt:P361  wd:Q203396}

  ?item wdt:P1082 ?pop
  SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],en". }
}
SPARQL;

$queryDispatcher = new SPARQLQueryDispatcher($endpointUrl);
$queryResult = $queryDispatcher->query($sparqlQueryString);

$popresult = array();
foreach ($queryResult['results']['bindings'] as $key) {
    $popresult[$key['itemLabel']['value']] = $key['pop']['value'];
}


//Rename (Wiki-en name different form Wikidata)
$popresult['Sint Maarten']          = $popresult['Sint Maarten'];
$popresult['Bahamas']               = $popresult['The Bahamas'];
$popresult['Donetsk PR']            = $popresult["Donetsk People's Republic"];
$popresult['Northern Cyprus']       = $popresult['Turkish Republic of Northern Cyprus'];
$popresult['Luhansk PR']            = $popresult["Luhansk People's Republic"];
$popresult['DR Congo']              = $popresult['Democratic Republic of the Congo'];
$popresult['Congo']                 = $popresult['Republic of the Congo'];
$popresult['Saint Vincent']         = $popresult['Saint Vincent and the Grenadines'];
$popresult['Palestine']             = $popresult['State of Palestine'];
$popresult['United States']         = $popresult['United States of America'];
$popresult['China']                 = $popresult["People's Republic of China"];
$popresult['U.S. Virgin Islands']   = $popresult['United States Virgin Islands'];
$popresult['Bermudas']              = $popresult['Bermuda'];

//Ships (ref:https://en.wikipedia.org/wiki/COVID-19_pandemic_on_cruise_ships)
$popresult['Diamond Princess']      = 3711;
$popresult['MS Zaandam']            = 1829;
$popresult['Greg Mortimer']         = 217;
$popresult['Costa Atlantica']       = 623;
$popresult['Coral Princess']        = 1898;

//Territories from countries
$popresult['China'] = $popresult['China'] 
- $popresult['Macau'] 
- $popresult['Hong Kong'];

$popresult['United Kingdom'] = $popresult['United Kingdom'] 
- $popresult['Anguilla'] 
- $popresult['British Virgin Islands'] 
- $popresult['Bermudas'] 
- $popresult['British Virgin Islands'] 
- $popresult['Cayman Islands'] 
- $popresult['Falkland Islands'] 
- $popresult['Gibraltar'] 
- $popresult['Guernsey'] 
- $popresult['Isle of Man'] 
- $popresult['Jersey'] 
- $popresult['Montserrat'] 
- $popresult['Turks and Caicos Islands'];

$popresult['Netherlands'] = $popresult['Netherlands'] 
- $popresult['Aruba'] 
- $popresult['Bonaire'] 
- $popresult['Curaçao'] 
- $popresult['Sint Eustatius'] 
- $popresult['Sint Maarten'];

$popresult['Denmark'] = $popresult['Denmark'] 
- $popresult['Faroe Islands'] 
- $popresult['Greenland'];

$popresult['United States'] = $popresult['United States'] 
- $popresult['Guam'] 
- $popresult['Northern Mariana Islands'] 
- $popresult['Puerto Rico'] 
- $popresult['U.S. Virgin Islands'];

$popresult['France'] = $popresult['France'] 
- $popresult['New Caledonia'] 
- $popresult['Saint Pierre and Miquelon'];

//World population (wdt:P1082 wd:Q11188)
//$popresult['World'] = 7650000000;