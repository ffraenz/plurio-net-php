<?php

namespace FFraenz\PlurioNet;

class CategoryListing extends Document
{
    /**
     * Default document url
     * @var string
     */
    const DEFAULT_DOCUMENT_URL =
        'http://www.plurio.net/XML/listings/categoriesXML.php';

    /**
     * Default schema url
     * @var string
     */
    const DEFAULT_SCHEMA_URL =
        __DIR__ . '/../assets/schema-category-listing.xsd';

    /**
     * Array of categories
     * @var array
     */
    protected $categories;

    /**
     * Array mapping ids to categories
     * @var array
     */
    protected $categoryMap;

    /**
     * Constructor
     * @param string $documentUrl (optional)
     * @param string $schemaUrl (optional)
     */
    public function __construct(
        $documentUrl = self::DEFAULT_DOCUMENT_URL,
        $schemaUrl = self::DEFAULT_SCHEMA_URL
    ) {
        parent::__construct($documentUrl, $schemaUrl);
    }

    /**
     * Returns an array mapping ids to categories.
     * @return array
     */
    public function getCategoryMap()
    {
        if ($this->categoryMap !== null) {
            return $this->categoryMap;
        }

        $documentNode = $this->getDocumentNode();

        // known languages
        $languages = [
            'de' => 'german',
            'en' => 'english',
            'fr' => 'french',
        ];

        // go through known languages and collect categories
        $categoryMap = [];
        foreach ($languages as $languageCode => $languageNodeName) {
            // visit category nodes inside language
            $categoryNodes = $this->queryNodes($documentNode,
                sprintf('/categories/%s/agenda/category', $languageNodeName));

            foreach ($categoryNodes as $categoryNode) {

                // collect category details
                $id = intval($this->queryValue(
                    $categoryNode, '@id'));
                $nameLevels = $this->queryValues(
                    $categoryNode, ['level1', 'level2', 'level3']);
                $name = array_pop($nameLevels);
                $parentName = array_pop($nameLevels);

                // add category entry to map if not already done yet
                if (!isset($categoryMap[$id])) {
                    $categoryMap[$id] = [
                        'id' => $id,
                        'name' => [],
                        'parentName' => [],
                    ];
                }

                // add name and parent name of current language
                $categoryMap[$id]['name'][$languageCode] = $name;
                $categoryMap[$id]['parentName'][$languageCode] = $parentName;
            }
        }

        $this->categoryMap = $categoryMap;
        return $categoryMap;
    }

    /**
     * Returns an array of categories.
     * @return array
     */
    public function getCategories()
    {
        if ($this->categories === null) {
            $this->categories = array_values($this->getCategoryMap());
        }
        return $this->categories;
    }

    /**
     * Returns a single category by id.
     * @param int $id Category id
     * @return array|null
     */
    public function getCategory($id)
    {
        $categoryMap = $this->getCategoryMap();
        return isset($categoryMap[$id]) ? $categoryMap[$id] : null;
    }
}
