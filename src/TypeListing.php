<?php

namespace FFraenz\PlurioNet;

class TypeListing extends Document
{
    /**
     * Default document url
     * @var string
     */
    const DEFAULT_DOCUMENT_URL =
        'http://www.plurio.net/XML/listings/typesXML.php';

    /**
     * Default schema url
     * @var string
     */
    const DEFAULT_SCHEMA_URL =
        __DIR__ . '/../assets/schema-type-listing.xsd';

    /**
     * Array of types
     * @var array
     */
    protected $types;

    /**
     * Array mapping ids to types
     * @var array
     */
    protected $typeMap;

    /**
     * Constructor
     * @param string $documentUrl (optional)
     * @param string $schemaUrl (optional)
     */
    public function __construct(
        $documentUrl = self::DEFAULT_DOCUMENT_URL,
        $schemaUrl = self::DEFAULT_SCHEMA_URL)
    {
        parent::__construct($documentUrl, $schemaUrl);
    }

    /**
     * Returns an array of types.
     * @return array
     */
    public function getTypes()
    {
        // lazily fetch and read types
        if ($this->types === null) {
            $typeNodes = $this->queryNodes(
                $this->getDocumentNode(), '/types/type');
            $this->types = array_filter(
                array_map([$this, 'readType'], $typeNodes));
        }
        return $this->types;
    }

    /**
     * Returns an array mapping ids to types.
     * @return array
     */
    public function getTypeMap()
    {
        // lazily create type map
        if ($this->typeMap === null) {
            $this->typeMap = [];
            foreach ($this->getTypes() as $type) {
                $this->typeMap[$type['id']] = $type;
            }
        }
        return $this->typeMap;
    }

    /**
     * Returns a single type by id.
     * @param string $id Type id
     * @return array|null Type array or null if not found
     */
    public function getType($id)
    {
        $typeMap = $this->getTypeMap();
        return isset($typeMap[$id]) ? $typeMap[$id] : null;
    }

    /**
     * Reads type from given node.
     * @param DOMNode $typeNode
     * @return array
     */
    protected function readType($typeNode)
    {
        // read id
        $id = $this->queryValue($typeNode, '@id');

        // get descriptions for known languages
        $languageDescriptionMap = [];
        foreach (['de', 'fr'] as $languageCode) {
            $description = $this->queryValue(
                $typeNode, sprintf('description_%s', $languageCode));
            if (!empty($description)) {
                $languageDescriptionMap[$languageCode] = $description;
            }
        }

        // compose type array
        return [
            'id' => $id,
            'description' => $languageDescriptionMap,
        ];
    }
}
