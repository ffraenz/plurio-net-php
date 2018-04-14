<?php

namespace FFraenz\PlurioNet;

use DOMAttr;
use DomDocument;
use DOMNode;
use DOMXpath;

/**
 * Abstraction layer simplifying access to XML formatted data.
 */
class Document
{
    /**
     * Document location url or path
     * @var string
     */
    protected $documentUrl;

    /**
     * Schema path
     * @var string
     */
    protected $schemaUrl;

    /**
     * Document node
     * @var DomDocument
     */
    protected $documentNode;

    /**
     * XML document Xpath
     * @var DOMXpath
     */
    protected $xpath;

    /**
     * Constructor
     * @param string $documentUrl Remote url or local path to document
     * @param string $schemaUrl Remote url or local path to schema (optional)
     */
    public function __construct($documentUrl, $schemaUrl = null)
    {
        $this->documentUrl = $documentUrl;
        $this->schemaUrl = $schemaUrl;
    }

    /**
     * Queries a single value on given Xpath query and context node.
     * @param DOMNode|null $node Context node
     * @param string $query Xpath query
     * @return string
     */
    public function queryValue($node, $query)
    {
        $values = $this->queryValues($node, $query);
        // only accept a single value
        if (count($values) !== 1) {
            return null;
        }
        $value = $values[0];
        if (empty($value) || $value === '#nodata#') {
            return null;
        }
        return $value;
    }

    /**
     * Queries values based on given Xpath query(s) and context node.
     * @param DOMNode|null $node Context node
     * @param string|string[] $query One or more Xpath queries
     * @return string[] Array of strings
     */
    public function queryValues($node, $query)
    {
        $nodes = $this->queryNodes($node, $query);
        $values = [];

        foreach ($nodes as $node) {
            $value = $node instanceof DOMAttr
                ? $node->value
                : $node->nodeValue;

            if (!empty($value)) {
                $value = trim($value);
            } else {
                $value = null;
            }

            array_push($values, $value);
        }
        return $values;
    }

    /**
     * Queries nodes based on given Xpath query(s) and context node.
     * @param DOMNode|null $node Context node
     * @param string|string[] $query One or more Xpath queries
     * @return DOMNode[] Array of nodes
     */
    public function queryNodes($node, $query)
    {
        // create xpath instance
        if ($this->xpath === null) {
            $this->xpath = new DOMXpath(
                $this->getDocumentNode());
        }

        // collect nodes for each query
        $nodes = [];
        $queries = is_array($query) ? $query : [$query];
        foreach ($queries as $query) {
            $nodeList = $this->xpath->query($query, $node);
            $nodes = array_merge($nodes, iterator_to_array($nodeList));
        }
        return $nodes;
    }

    /**
     * Returns the document node.
     * Lazily fetches, parses and validates it.
     * @return DomDocument
     */
    public function getDocumentNode()
    {
        if ($this->documentNode !== null) {
            return $this->documentNode;
        }

        // fetch document body
        $documentBody = $this->fetchFileContents($this->documentUrl);

        // parse document
        libxml_use_internal_errors(true);
        $document = new DomDocument();
        if (@$document->loadXML($documentBody) === false) {
            throw new Exception(sprintf(
                "Unable to parse document at '%s'. Errors: %s",
                $this->documentUrl,
                json_encode(libxml_get_errors())
            ));
        }

        if ($this->schemaUrl !== null) {
            // fetch schema body
            $schemaBody = $this->fetchFileContents($this->schemaUrl);

            // validate document using schema
            libxml_clear_errors();
            if (!@$document->schemaValidateSource($schemaBody)) {
                throw new Exception(sprintf(
                    "Unable to validate document at '%s' " .
                    "with schema at '%s'. Errors: %s",
                    $this->documentUrl,
                    $this->schemaUrl,
                    json_encode(libxml_get_errors())
                ));
            }
        }

        $this->documentNode = $document;
        return $document;
    }

    /**
     * Fetches file contents from remote url or local path.
     * @param string $url Url or path
     * @return string|null
     */
    protected function fetchFileContents($url)
    {
        // read file if it is located locally
        if (parse_url($url, PHP_URL_HOST) === null) {
            $content = file_get_contents($url);
            if ($content === false) {
                throw new Exception(sprintf(
                    "Unable to read file at '%s'.",
                    $url
                ));
            }
            return $content;
        }

        // request file
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
        ]);

        $content = curl_exec($curl);
        $error = curl_error($curl);
        if ($error) {
            throw new Exception(sprintf(
                "Unable to fetch file at '%s': %s",
                $url,
                $error
            ));
        }

        // check status code
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($statusCode !== 200) {
            throw new Exception(sprintf(
                "Received status code %d while fetching '%s'.",
                $statusCode,
                $url
            ));
        }

        curl_close($curl);
        return $content;
    }
}
